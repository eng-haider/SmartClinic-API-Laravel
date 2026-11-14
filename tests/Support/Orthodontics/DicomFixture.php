<?php

namespace Tests\Support\Orthodontics;

/**
 * Builds tiny synthetic DICOM Part 10 files (no patient data) for tests.
 */
class DicomFixture
{
    public static function make(array $o = []): string
    {
        $implicit = $o['implicit'] ?? false;
        $instance = $o['instance'] ?? 1;
        $study = $o['study'] ?? '1.2.3.4.100';
        $z = $o['z'] ?? (float) $instance;

        $meta = self::el(0x0002, 0x0010, 'UI', self::pad($implicit ? '1.2.840.10008.1.2' : '1.2.840.10008.1.2.1', true), false);
        $metaGroup = self::el(0x0002, 0x0000, 'UL', pack('V', strlen($meta)), false) . $meta;

        $ds = '';
        $ds .= self::el(0x0008, 0x0016, 'UI', self::pad('1.2.840.10008.5.1.4.1.1.2', true), $implicit);
        $ds .= self::el(0x0008, 0x0018, 'UI', self::pad($study . '.1.' . $instance, true), $implicit);
        $ds .= self::el(0x0008, 0x0020, 'DA', '20261101', $implicit);
        $ds .= self::el(0x0008, 0x0060, 'CS', 'CT', $implicit);
        if ($o['sequence'] ?? false) {
            // Undefined-length sequence with one undefined-length item.
            $inner = self::el(0x0008, 0x0100, 'SH', self::pad('X'), $implicit);
            $item = pack('vvV', 0xFFFE, 0xE000, 0xFFFFFFFF) . $inner . pack('vvV', 0xFFFE, 0xE00D, 0);
            $head = $implicit ? pack('vvV', 0x0008, 0x1110, 0xFFFFFFFF) : pack('vv', 0x0008, 0x1110) . 'SQ' . "\0\0" . pack('V', 0xFFFFFFFF);
            $ds .= $head . $item . pack('vvV', 0xFFFE, 0xE0DD, 0);
        }
        $ds .= self::el(0x0020, 0x000D, 'UI', self::pad($study, true), $implicit);
        $ds .= self::el(0x0020, 0x000E, 'UI', self::pad($study . '.1', true), $implicit);
        $ds .= self::el(0x0020, 0x0013, 'IS', self::pad((string) $instance), $implicit);
        $ds .= self::el(0x0020, 0x0032, 'DS', self::pad('0\\0\\' . $z), $implicit);
        $ds .= self::el(0x0020, 0x0037, 'DS', self::pad('1\\0\\0\\0\\1\\0'), $implicit);
        $ds .= self::el(0x0028, 0x0010, 'US', pack('v', 64), $implicit);
        $ds .= self::el(0x0028, 0x0011, 'US', pack('v', 32), $implicit);
        $ds .= self::el(0x0028, 0x0030, 'DS', self::pad('0.25\\0.25'), $implicit);
        $pixels = str_repeat("\0", 64 * 32 * 2);
        $ds .= $implicit ? pack('vvV', 0x7FE0, 0x0010, strlen($pixels)) . $pixels : pack('vv', 0x7FE0, 0x0010) . 'OW' . "\0\0" . pack('V', strlen($pixels)) . $pixels;

        return str_repeat("\0", 128) . 'DICM' . $metaGroup . $ds;
    }

    private static function pad(string $v, bool $uid = false): string
    {
        return strlen($v) % 2 ? $v . ($uid ? "\0" : ' ') : $v;
    }

    private static function el(int $g, int $e, string $vr, string $value, bool $implicit): string
    {
        if ($implicit) {
            return pack('vvV', $g, $e, strlen($value)) . $value;
        }
        $long = in_array($vr, ['OB', 'OW', 'SQ', 'UN', 'UT'], true);

        return pack('vv', $g, $e) . $vr . ($long ? "\0\0" . pack('V', strlen($value)) : pack('v', strlen($value))) . $value;
    }
}
