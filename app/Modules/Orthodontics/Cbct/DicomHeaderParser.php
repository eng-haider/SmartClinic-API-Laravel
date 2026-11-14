<?php

namespace App\Modules\Orthodontics\Cbct;

use App\Modules\Orthodontics\Services\OrthodonticsException;

/**
 * Reads the handful of DICOM header attributes the CBCT workflow needs
 * (UIDs, geometry, sizes) from a Part 10 file, stopping at Pixel Data.
 *
 * Supports Implicit and Explicit VR Little Endian datasets (which covers the
 * compressed transfer syntaxes too, since only their pixel data differs).
 * Explicit VR Big Endian (retired) and deflated datasets are refused.
 * Pixel data is never decoded here - the browser viewer does that.
 */
final class DicomHeaderParser
{
    private const IMPLICIT_LE = '1.2.840.10008.1.2';

    private const EXPLICIT_BE = '1.2.840.10008.1.2.2';

    private const DEFLATED = '1.2.840.10008.1.2.1.99';

    // VRs with a 2-byte reserved field and 4-byte length in explicit VR.
    private const LONG_VRS = ['OB', 'OD', 'OF', 'OL', 'OV', 'OW', 'SQ', 'SV', 'UC', 'UN', 'UR', 'UT', 'UV'];

    // tag => [name, type] for the attributes we keep.
    private const WANTED = [
        '00080016' => ['sop_class_uid', 'str'],
        '00080018' => ['sop_instance_uid', 'str'],
        '00080020' => ['study_date', 'str'],
        '00080060' => ['modality', 'str'],
        '00081030' => ['study_description', 'str'],
        '0008103E' => ['series_description', 'str'],
        '00180050' => ['slice_thickness', 'num'],
        '0020000D' => ['study_instance_uid', 'str'],
        '0020000E' => ['series_instance_uid', 'str'],
        '00200013' => ['instance_number', 'int'],
        '00200032' => ['image_position', 'vec'],
        '00200037' => ['image_orientation', 'vec'],
        '00201041' => ['slice_location', 'num'],
        '00280008' => ['frames', 'int'],
        '00280010' => ['rows', 'us'],
        '00280011' => ['columns', 'us'],
        '00280030' => ['pixel_spacing', 'vec'],
    ];

    private string $buf;

    private int $len;

    private bool $implicit = false;

    private array $out = [];

    /** Bytes read from the start of the file - headers end long before this. */
    private const HEADER_BYTES = 8 * 1024 * 1024;

    public static function parseFile(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            throw new OrthodonticsException('The DICOM file could not be read.', 422, 'invalid_dicom');
        }
        $buf = fread($handle, self::HEADER_BYTES) ?: '';
        fclose($handle);

        return (new self())->parse($buf);
    }

    public function parse(string $buf): array
    {
        $this->buf = $buf;
        $this->len = strlen($buf);
        $this->out = [];

        if ($this->len < 132 || substr($buf, 128, 4) !== 'DICM') {
            throw new OrthodonticsException('Not a DICOM Part 10 file (missing DICM preamble).', 422, 'invalid_dicom');
        }

        // File meta information: always explicit VR little endian.
        $pos = 132;
        $syntax = null;
        while ($pos + 8 <= $this->len) {
            $group = $this->u16($pos);
            if ($group !== 0x0002) {
                break;
            }
            [$tag, $value, $next] = $this->readElement($pos, false);
            if ($tag === '00020010') {
                $syntax = rtrim($value ?? '', "\0 ");
            }
            $pos = $next;
        }

        if ($syntax === self::EXPLICIT_BE || $syntax === self::DEFLATED) {
            throw new OrthodonticsException('Unsupported DICOM transfer syntax: ' . $syntax, 422, 'invalid_dicom');
        }
        $this->implicit = $syntax === self::IMPLICIT_LE;
        $this->out['transfer_syntax'] = $syntax;

        $this->parseDataset($pos, $this->len, 0);

        if (empty($this->out['sop_instance_uid']) || empty($this->out['study_instance_uid'])) {
            throw new OrthodonticsException('The DICOM file has no SOP Instance UID or Study Instance UID.', 422, 'invalid_dicom');
        }

        $this->out['frames'] = max(1, (int) ($this->out['frames'] ?? 1));
        $this->out['slice_position'] = $this->slicePosition();

        return $this->out;
    }

    /**
     * Position of the slice along its normal: dot(ImagePositionPatient,
     * row x column direction cosines). Used to order slices.
     */
    private function slicePosition(): ?float
    {
        $ipp = $this->out['image_position'] ?? null;
        $iop = $this->out['image_orientation'] ?? null;
        if (is_array($ipp) && count($ipp) === 3 && is_array($iop) && count($iop) === 6) {
            $r = array_slice($iop, 0, 3);
            $c = array_slice($iop, 3, 3);
            $n = [$r[1] * $c[2] - $r[2] * $c[1], $r[2] * $c[0] - $r[0] * $c[2], $r[0] * $c[1] - $r[1] * $c[0]];

            return $ipp[0] * $n[0] + $ipp[1] * $n[1] + $ipp[2] * $n[2];
        }

        return isset($this->out['slice_location']) ? (float) $this->out['slice_location'] : null;
    }

    /**
     * Walk elements from $pos to $end. Returns the position after the last
     * element, or -1 once Pixel Data is reached (nothing after it matters).
     */
    private function parseDataset(int $pos, int $end, int $depth): int
    {
        while ($pos + 8 <= $end) {
            $group = $this->u16($pos);
            $element = $this->u16($pos + 2);

            // Item / sequence delimiters end the current nested dataset.
            if ($group === 0xFFFE && ($element === 0xE00D || $element === 0xE0DD)) {
                return $pos + 8;
            }
            if ($depth === 0 && $group === 0x7FE0 && $element === 0x0010) {
                return -1;
            }

            [$tag, $value, $next, $vr, $length, $valueStart] = $this->readElement($pos, $this->implicit);

            $isSequence = $vr === 'SQ' || ($length === 0xFFFFFFFF && ($vr === 'UN' || $vr === null));
            if ($isSequence) {
                $next = $this->skipSequence($valueStart, $length, $depth + 1);
                if ($next < 0) {
                    return -1;
                }
            } elseif ($depth === 0 && isset(self::WANTED[$tag]) && $value !== null) {
                [$name, $type] = self::WANTED[$tag];
                $this->out[$name] = $this->convert($value, $type);
            }

            if ($next <= $pos) {
                break; // malformed - never loop forever
            }
            $pos = $next;
        }

        return $pos;
    }

    private function skipSequence(int $pos, int $length, int $depth): int
    {
        if ($depth > 16) {
            throw new OrthodonticsException('DICOM sequences are nested too deeply.', 422, 'invalid_dicom');
        }
        $end = $length === 0xFFFFFFFF ? $this->len : min($this->len, $pos + $length);

        while ($pos + 8 <= $end) {
            $group = $this->u16($pos);
            $element = $this->u16($pos + 2);
            $itemLength = $this->u32($pos + 4);
            if ($group === 0xFFFE && $element === 0xE0DD) {
                return $pos + 8; // sequence delimitation
            }
            if ($group !== 0xFFFE || $element !== 0xE000) {
                return $end; // not an item - give up on this sequence
            }
            $itemStart = $pos + 8;
            if ($itemLength === 0xFFFFFFFF) {
                $pos = $this->parseDataset($itemStart, $this->len, $depth);
                if ($pos < 0) {
                    return -1;
                }
            } else {
                $pos = $itemStart + $itemLength;
            }
        }

        return $length === 0xFFFFFFFF ? $pos : $end;
    }

    /**
     * @return array{0: string, 1: ?string, 2: int, 3: ?string, 4: int, 5: int}
     *               tag, raw value (null if too long/absent), next position, VR, length, value start
     */
    private function readElement(int $pos, bool $implicit): array
    {
        $tag = sprintf('%04X%04X', $this->u16($pos), $this->u16($pos + 2));
        $vr = null;
        if ($implicit) {
            $length = $this->u32($pos + 4);
            $start = $pos + 8;
        } else {
            $vr = substr($this->buf, $pos + 4, 2);
            if (in_array($vr, self::LONG_VRS, true)) {
                $length = $this->u32($pos + 8);
                $start = $pos + 12;
            } else {
                $length = $this->u16($pos + 6);
                $start = $pos + 8;
            }
        }
        if ($implicit && $tag === '00280010' || $implicit && $tag === '00280011') {
            $vr = 'US';
        }
        if ($length === 0xFFFFFFFF) {
            return [$tag, null, $start, $vr, $length, $start];
        }
        $value = ($length <= 4096 && $start + $length <= $this->len) ? substr($this->buf, $start, $length) : null;

        return [$tag, $value, $start + $length, $vr, $length, $start];
    }

    private function convert(string $raw, string $type)
    {
        return match ($type) {
            'us' => strlen($raw) >= 2 ? unpack('v', $raw)[1] : null,
            'int' => is_numeric(trim($raw, "\0 ")) ? (int) trim($raw, "\0 ") : null,
            'num' => is_numeric(trim($raw, "\0 ")) ? (float) trim($raw, "\0 ") : null,
            'vec' => array_values(array_map('floatval', array_filter(explode('\\', trim($raw, "\0 ")), 'is_numeric'))),
            default => mb_substr(trim($raw, "\0 "), 0, 128),
        };
    }

    private function u16(int $pos): int
    {
        return $pos + 2 <= $this->len ? unpack('v', substr($this->buf, $pos, 2))[1] : 0;
    }

    private function u32(int $pos): int
    {
        return $pos + 4 <= $this->len ? unpack('V', substr($this->buf, $pos, 4))[1] : 0;
    }
}
