<?php

namespace App\Modules\Orthodontics\Analysis;

use App\Modules\Orthodontics\Analysis\Geometry as G;

/**
 * Dental cast (3D model) analysis from points the doctor picked on the mesh.
 *
 * Input `data`:
 *   scale_to_mm: float|null   model units -> mm, ONLY once the doctor confirmed
 *                             the file's units. null keeps results in model units.
 *   teeth: { "11": {"mesial": [x,y,z], "distal": [x,y,z]} | {"manual_mm": 8.5}, ... }
 *                             FDI numbers. manual_mm is a caliper reading.
 *   points: {                 named 3D points on the models (model units)
 *     upper|lower: { incisal, canine_right, canine_left, molar_right, molar_left,
 *                    molar_mesial_right, molar_mesial_left,
 *                    pont_premolar_right, pont_premolar_left, pont_molar_right, pont_molar_left }
 *   }
 *   arch_perimeter: { upper: [[x,y,z], ...], lower: [...] }   ordered, mesial of
 *                             first molar to mesial of first molar
 *   segments: { upper_right: [[..],[..]], ... } available space for C+P1+P2
 *   spee: { incisal: [..], molar_right: [..], molar_left: [..],
 *           cusps_right: [[..],...], cusps_left: [[..],...] }
 *   distances: [{ "label": "...", "a": [..], "b": [..] }]   free measurements
 *
 * Nothing is guessed: a result needs all of its inputs, otherwise it is null
 * and lists what is missing.
 */
final class CastAnalysisCalculator
{
    public const VERSION = '1.0.0';

    public const MAXILLARY_12 = [16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26];

    public const MANDIBULAR_12 = [46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36];

    public const MAXILLARY_6 = [13, 12, 11, 21, 22, 23];

    public const MANDIBULAR_6 = [43, 42, 41, 31, 32, 33];

    public const UPPER_10 = [15, 14, 13, 12, 11, 21, 22, 23, 24, 25];

    public const LOWER_10 = [45, 44, 43, 42, 41, 31, 32, 33, 34, 35];

    // Bolton 1958: means and SDs.
    public const BOLTON_OVERALL = ['mean' => 91.3, 'sd' => 1.91, 'source' => 'Bolton 1958'];

    public const BOLTON_ANTERIOR = ['mean' => 77.2, 'sd' => 1.65, 'source' => 'Bolton 1958'];

    public static function analyze(array $data, ?array $moyersTable = null): array
    {
        $scale = isset($data['scale_to_mm']) && (float) $data['scale_to_mm'] > 0 ? (float) $data['scale_to_mm'] : null;
        $widths = self::toothWidths($data['teeth'] ?? [], $scale);
        $unit = $widths['unit'];
        $w = $widths['widths'];
        $points = self::pointGroups($data['points'] ?? []);
        $toLen = fn (float $modelUnits) => $scale ? $modelUnits * $scale : $modelUnits;
        $lenUnit = $scale ? 'mm' : 'model units';

        $results = [
            'version' => self::VERSION,
            'calibrated' => $scale !== null,
            'scale_to_mm' => $scale,
            'width_unit' => $unit,
            'length_unit' => $lenUnit,
            'tooth_widths' => array_map(fn ($v) => round($v, 2), $w),
            'mixed_units' => $widths['mixed'],
        ];

        // ---- Bolton -------------------------------------------------------
        $results['bolton'] = [
            'overall' => self::bolton($w, self::MAXILLARY_12, self::MANDIBULAR_12, self::BOLTON_OVERALL, $widths['mixed']),
            'anterior' => self::bolton($w, self::MAXILLARY_6, self::MANDIBULAR_6, self::BOLTON_ANTERIOR, $widths['mixed']),
        ];

        // ---- Space analysis / Carey ----------------------------------------
        $space = [];
        foreach (['upper' => self::UPPER_10, 'lower' => self::LOWER_10] as $arch => $teeth) {
            $required = self::sum($w, $teeth);
            $perimeterPts = self::pointList($data['arch_perimeter'][$arch] ?? []);
            $available = count($perimeterPts) >= 2 ? $toLen(G::polylineLength($perimeterPts)) : null;
            $comparable = !$widths['mixed'] && $required !== null && $available !== null && $unit === $lenUnit;

            $space[$arch] = [
                'required' => self::r($required),
                'available' => self::r($available),
                // Negative = crowding, positive = spacing.
                'discrepancy' => $comparable ? self::r($available - $required) : null,
                'missing_teeth' => self::missing($w, $teeth),
                'unit' => $lenUnit,
                'needs_perimeter' => count($perimeterPts) < 2,
            ];
        }
        $results['space_analysis'] = $space;
        // Carey (1949): the mandibular arch perimeter mesial of 6 to mesial of 6
        // against the summed widths of the ten teeth in front of the first molars.
        $results['carey'] = $space['lower'];

        // ---- Arch dimensions ----------------------------------------------
        $arch = [];
        foreach (['upper', 'lower'] as $a) {
            $pts = $points[$a] ?? [];
            $arch[$a] = [
                'intercanine_width' => isset($pts['canine_right'], $pts['canine_left']) ? self::r($toLen(G::distance($pts['canine_right'], $pts['canine_left']))) : null,
                'intermolar_width' => isset($pts['molar_right'], $pts['molar_left']) ? self::r($toLen(G::distance($pts['molar_right'], $pts['molar_left']))) : null,
                // Arch depth: incisal point to the line joining the mesial contact
                // points of the first molars.
                'arch_depth' => isset($pts['incisal'], $pts['molar_mesial_right'], $pts['molar_mesial_left'])
                    ? self::r($toLen(G::distanceToLine3($pts['incisal'], $pts['molar_mesial_right'], $pts['molar_mesial_left'])))
                    : null,
                'arch_perimeter' => $space[$a]['available'],
                'unit' => $lenUnit,
            ];
        }
        $results['arch'] = $arch;

        // ---- Pont (maxilla) -----------------------------------------------
        $results['pont'] = self::pont($w, $points['upper'] ?? [], $scale, $widths['mixed'], $unit);

        // ---- Mixed dentition predictions ----------------------------------
        $results['tanaka_johnston'] = self::tanakaJohnston($w, $unit, $widths['mixed'], $data['segments'] ?? [], $toLen, $lenUnit);
        $results['moyers'] = self::moyers($w, $unit, $widths['mixed'], $moyersTable);

        // ---- Curve of Spee --------------------------------------------------
        $results['curve_of_spee'] = self::curveOfSpee($data['spee'] ?? [], $toLen, $lenUnit);

        // ---- Free distances ----------------------------------------------
        $results['distances'] = [];
        foreach ($data['distances'] ?? [] as $i => $d) {
            $a = self::point3($d['a'] ?? null);
            $b = self::point3($d['b'] ?? null);
            if ($a && $b) {
                $results['distances'][] = [
                    'label' => (string) ($d['label'] ?? ('#' . ($i + 1))),
                    'value' => self::r($toLen(G::distance($a, $b))),
                    'unit' => $lenUnit,
                ];
            }
        }

        return $results;
    }

    /**
     * Bolton ratio = sum of mandibular widths / sum of maxillary widths x 100.
     * The excess is the amount of tooth material that would have to go to
     * bring the ratio to the mean (Bolton 1958 / 1962).
     */
    private static function bolton(array $w, array $max, array $mand, array $norm, bool $mixed): array
    {
        $sumMax = self::sum($w, $max);
        $sumMand = self::sum($w, $mand);
        $out = [
            'maxillary_sum' => self::r($sumMax),
            'mandibular_sum' => self::r($sumMand),
            'ratio' => null,
            'norm' => $norm,
            'comparison' => null,
            'excess_arch' => null,
            'excess' => null,
            'missing_teeth' => self::missing($w, array_merge($max, $mand)),
        ];
        if ($mixed || $sumMax === null || $sumMand === null || $sumMax <= 0) {
            return $out;
        }

        $ratio = $sumMand / $sumMax * 100;
        $out['ratio'] = self::r($ratio);
        $out['comparison'] = CephalometricCalculator::compare($ratio, $norm);
        $k = $norm['mean'] / 100;
        if ($ratio > $norm['mean']) {
            $out['excess_arch'] = 'mandibular';
            $out['excess'] = self::r($sumMand - $sumMax * $k);
        } else {
            $out['excess_arch'] = 'maxillary';
            $out['excess'] = self::r($sumMax - $sumMand / $k);
        }

        return $out;
    }

    /**
     * Pont (1909): ideal maxillary arch width from the summed widths of the
     * four maxillary incisors (SI). Premolar width = SI x 100 / 80 (distal pits
     * of the first premolars); molar width = SI x 100 / 64 (mesial pits of the
     * first molars). Derived from a French sample; population specific.
     */
    private static function pont(array $w, array $pts, ?float $scale, bool $mixed, string $unit): array
    {
        $si = self::sum($w, [12, 11, 21, 22]);
        $out = [
            'sum_incisors' => self::r($si),
            'predicted_premolar_width' => null, 'measured_premolar_width' => null, 'premolar_difference' => null,
            'predicted_molar_width' => null, 'measured_molar_width' => null, 'molar_difference' => null,
            'unit' => 'mm',
            'source' => 'Pont 1909',
            'requires' => [],
        ];
        // The index mixes tooth widths and arch widths, so both must be in mm.
        if ($mixed || $si === null || $unit !== 'mm') {
            $out['requires'][] = 'incisor_widths_mm';

            return $out;
        }
        $out['predicted_premolar_width'] = self::r($si * 100 / 80);
        $out['predicted_molar_width'] = self::r($si * 100 / 64);
        if (!$scale) {
            $out['requires'][] = 'confirmed_model_units';

            return $out;
        }
        if (isset($pts['pont_premolar_right'], $pts['pont_premolar_left'])) {
            $m = G::distance($pts['pont_premolar_right'], $pts['pont_premolar_left']) * $scale;
            $out['measured_premolar_width'] = self::r($m);
            $out['premolar_difference'] = self::r($m - $si * 100 / 80);
        }
        if (isset($pts['pont_molar_right'], $pts['pont_molar_left'])) {
            $m = G::distance($pts['pont_molar_right'], $pts['pont_molar_left']) * $scale;
            $out['measured_molar_width'] = self::r($m);
            $out['molar_difference'] = self::r($m - $si * 100 / 64);
        }

        return $out;
    }

    /**
     * Tanaka & Johnston (1974): predicted width of the unerupted canine and
     * premolars in one quadrant from the four mandibular incisors (SI):
     * mandibular = SI / 2 + 10.5 mm, maxillary = SI / 2 + 11.0 mm.
     */
    private static function tanakaJohnston(array $w, string $unit, bool $mixed, array $segments, callable $toLen, string $lenUnit): array
    {
        $si = self::sum($w, [42, 41, 31, 32]);
        $out = ['sum_mandibular_incisors' => self::r($si), 'predicted_upper' => null, 'predicted_lower' => null, 'segments' => [], 'unit' => 'mm', 'source' => 'Tanaka & Johnston 1974'];
        if ($mixed || $si === null || $unit !== 'mm') {
            return $out;
        }
        $out['predicted_upper'] = self::r($si / 2 + 11.0);
        $out['predicted_lower'] = self::r($si / 2 + 10.5);

        foreach (['upper_right', 'upper_left', 'lower_right', 'lower_left'] as $seg) {
            $pts = self::pointList($segments[$seg] ?? []);
            if (count($pts) < 2 || $lenUnit !== 'mm') {
                continue;
            }
            $available = $toLen(G::polylineLength($pts));
            $predicted = str_starts_with($seg, 'upper') ? $si / 2 + 11.0 : $si / 2 + 10.5;
            $out['segments'][$seg] = [
                'available' => self::r($available),
                'predicted' => self::r($predicted),
                'discrepancy' => self::r($available - $predicted),
            ];
        }

        return $out;
    }

    /**
     * Moyers probability tables. The table is configuration (see
     * config('orthodontics.moyers_table')): it must be entered from a verified
     * printed source before it is used, so none ships with the code.
     */
    private static function moyers(array $w, string $unit, bool $mixed, ?array $table): array
    {
        $si = self::sum($w, [42, 41, 31, 32]);
        $out = ['sum_mandibular_incisors' => self::r($si), 'predicted_upper' => null, 'predicted_lower' => null, 'level' => null, 'table_configured' => (bool) $table, 'source' => $table['source'] ?? null];
        if (!$table || $mixed || $si === null || $unit !== 'mm') {
            return $out;
        }

        $rows = $table['rows'] ?? []; // [[sum_incisors, upper, lower], ...] ascending
        $out['level'] = $table['level'] ?? null;
        $out['predicted_upper'] = self::r(self::interpolate($rows, $si, 1));
        $out['predicted_lower'] = self::r(self::interpolate($rows, $si, 2));

        return $out;
    }

    /**
     * Linear interpolation inside the table only - no extrapolation.
     */
    private static function interpolate(array $rows, float $x, int $col): ?float
    {
        usort($rows, fn ($a, $b) => $a[0] <=> $b[0]);
        for ($i = 0, $n = count($rows); $i < $n; $i++) {
            if (abs($rows[$i][0] - $x) < 1e-9) {
                return (float) $rows[$i][$col];
            }
            if ($i + 1 < $n && $rows[$i][0] < $x && $x < $rows[$i + 1][0]) {
                $t = ($x - $rows[$i][0]) / ($rows[$i + 1][0] - $rows[$i][0]);

                return $rows[$i][$col] + $t * ($rows[$i + 1][$col] - $rows[$i][$col]);
            }
        }

        return null;
    }

    /**
     * Curve of Spee depth: the reference plane passes through the incisal point
     * of the mandibular central incisors and the distobuccal cusp tips of the
     * last molars; the depth on each side is the largest perpendicular distance
     * from the picked buccal cusp tips to that plane.
     */
    private static function curveOfSpee(array $spee, callable $toLen, string $lenUnit): array
    {
        $inc = self::point3($spee['incisal'] ?? null);
        $mr = self::point3($spee['molar_right'] ?? null);
        $ml = self::point3($spee['molar_left'] ?? null);
        $out = ['right' => null, 'left' => null, 'mean' => null, 'unit' => $lenUnit];
        if (!$inc || !$mr || !$ml) {
            return $out;
        }
        foreach (['right', 'left'] as $side) {
            $cusps = self::pointList($spee['cusps_' . $side] ?? []);
            if (!$cusps) {
                continue;
            }
            $max = 0.0;
            foreach ($cusps as $c) {
                $d = G::distanceToPlane($c, $inc, $mr, $ml);
                if ($d !== null) {
                    $max = max($max, $d);
                }
            }
            $out[$side] = self::r($toLen($max));
        }
        if ($out['right'] !== null && $out['left'] !== null) {
            $out['mean'] = self::r(($out['right'] + $out['left']) / 2);
        }

        return $out;
    }

    /**
     * Mesiodistal widths. Caliper readings are already mm; widths taken from
     * mesh points are model units until the units are confirmed.
     */
    private static function toothWidths(array $teeth, ?float $scale): array
    {
        $widths = [];
        $hasManual = false;
        $hasUnscaledPoints = false;
        foreach ($teeth as $fdi => $t) {
            $fdi = (int) $fdi;
            if ($fdi < 11 || $fdi > 48 || !is_array($t)) {
                continue;
            }
            if (isset($t['manual_mm']) && is_numeric($t['manual_mm']) && $t['manual_mm'] > 0) {
                $widths[$fdi] = (float) $t['manual_mm'];
                $hasManual = true;
                continue;
            }
            $m = self::point3($t['mesial'] ?? null);
            $d = self::point3($t['distal'] ?? null);
            if ($m && $d) {
                $dist = G::distance($m, $d);
                if ($scale) {
                    $widths[$fdi] = $dist * $scale;
                } else {
                    $widths[$fdi] = $dist;
                    $hasUnscaledPoints = true;
                }
            }
        }

        return [
            'widths' => $widths,
            'unit' => $hasUnscaledPoints ? 'model units' : 'mm',
            // Caliper mm next to unconfirmed model units cannot be summed.
            'mixed' => $hasManual && $hasUnscaledPoints,
        ];
    }

    private static function sum(array $w, array $teeth): ?float
    {
        $sum = 0.0;
        foreach ($teeth as $t) {
            if (!isset($w[$t])) {
                return null;
            }
            $sum += $w[$t];
        }

        return $sum;
    }

    private static function missing(array $w, array $teeth): array
    {
        return array_values(array_filter($teeth, fn ($t) => !isset($w[$t])));
    }

    private static function pointGroups(array $groups): array
    {
        $out = [];
        foreach (['upper', 'lower'] as $arch) {
            foreach ($groups[$arch] ?? [] as $name => $pt) {
                $p = self::point3($pt);
                if ($p) {
                    $out[$arch][$name] = $p;
                }
            }
        }

        return $out;
    }

    private static function pointList(array $list): array
    {
        return array_values(array_filter(array_map([self::class, 'point3'], $list)));
    }

    public static function point3($p): ?array
    {
        if (is_array($p) && isset($p['x'], $p['y'], $p['z'])) {
            $p = [$p['x'], $p['y'], $p['z']];
        }
        if (is_array($p) && count($p) >= 3 && is_numeric($p[0] ?? null) && is_numeric($p[1] ?? null) && is_numeric($p[2] ?? null)) {
            return [(float) $p[0], (float) $p[1], (float) $p[2]];
        }

        return null;
    }

    private static function r(?float $v): ?float
    {
        return $v === null ? null : round($v, 2);
    }
}
