<?php

namespace App\Modules\Orthodontics\Analysis;

use App\Modules\Orthodontics\Analysis\Geometry as G;

/**
 * CBCT measurements and implant plans.
 *
 * Points are DICOM patient (world) coordinates, which the standard defines in
 * millimetres (ImagePositionPatient, PixelSpacing), so no extra calibration is
 * needed - but the accuracy is bounded by the voxel size and by the scanner.
 *
 * Input `data`:
 *   measurements:   [{ type: distance|angle, label, points: [[x,y,z], ...] }]
 *   cross_sections: [{ label, spacing_mm: [col, row], points: [[c,r],[c,r]] }]
 *                   distances drawn on a reconstructed cross-section image
 *   implants:       [{ label, tooth, position: [x,y,z], direction: [x,y,z],
 *                      diameter_mm, length_mm }]  position = platform centre
 *   arch_curve:     [[x,y,z], ...]  panoramic curve (stored, not measured)
 */
final class CbctMeasurementCalculator
{
    public const VERSION = '1.0.0';

    public static function analyze(array $data): array
    {
        $measurements = [];
        foreach ($data['measurements'] ?? [] as $i => $m) {
            $pts = array_values(array_filter(array_map([CastAnalysisCalculator::class, 'point3'], $m['points'] ?? [])));
            $type = $m['type'] ?? 'distance';
            $value = null;
            if ($type === 'distance' && count($pts) >= 2) {
                $value = G::distance($pts[0], $pts[1]);
            } elseif ($type === 'angle' && count($pts) >= 3) {
                $value = G::angleAt($pts[0], $pts[1], $pts[2]);
            }
            $measurements[] = [
                'label' => (string) ($m['label'] ?? ('#' . ($i + 1))),
                'type' => $type,
                'value' => G::round($value),
                'unit' => $type === 'angle' ? '°' : 'mm',
            ];
        }

        $sections = [];
        foreach ($data['cross_sections'] ?? [] as $i => $s) {
            $sp = $s['spacing_mm'] ?? null;
            $pts = $s['points'] ?? [];
            $value = null;
            if (is_array($sp) && count($sp) === 2 && count($pts) >= 2 && $sp[0] > 0 && $sp[1] > 0) {
                $dc = ((float) $pts[1][0] - (float) $pts[0][0]) * (float) $sp[0];
                $dr = ((float) $pts[1][1] - (float) $pts[0][1]) * (float) $sp[1];
                $value = sqrt($dc * $dc + $dr * $dr);
            }
            $sections[] = [
                'label' => (string) ($s['label'] ?? ('#' . ($i + 1))),
                'kind' => $s['kind'] ?? 'distance', // bone_height | bone_width | distance
                'position_mm' => isset($s['position_mm']) ? G::round((float) $s['position_mm']) : null,
                'value' => G::round($value),
                'unit' => 'mm',
            ];
        }

        $implants = [];
        foreach ($data['implants'] ?? [] as $i => $im) {
            $pos = CastAnalysisCalculator::point3($im['position'] ?? null);
            $dir = CastAnalysisCalculator::point3($im['direction'] ?? null);
            $length = (float) ($im['length_mm'] ?? 0);
            $diameter = (float) ($im['diameter_mm'] ?? 0);
            $apex = null;
            $tilt = null;
            if ($pos && $dir && G::norm($dir) > 1e-9 && $length > 0) {
                $u = G::scale($dir, 1 / G::norm($dir));
                $apex = array_map(fn ($v) => round($v, 2), G::add($pos, G::scale($u, $length)));
                // Angle between the implant axis and the patient's vertical (z) axis.
                $tilt = G::angleBetween($u, [0, 0, $u[2] >= 0 ? 1 : -1]);
            }
            $implants[] = [
                'label' => (string) ($im['label'] ?? ('Implant ' . ($i + 1))),
                'tooth' => $im['tooth'] ?? null,
                'diameter_mm' => $diameter ?: null,
                'length_mm' => $length ?: null,
                'apex' => $apex,
                'tilt_from_vertical_deg' => G::round($tilt),
            ];
        }

        return [
            'version' => self::VERSION,
            'unit' => 'mm',
            'measurements' => $measurements,
            'cross_sections' => $sections,
            'implants' => $implants,
        ];
    }
}
