<?php

namespace App\Modules\Orthodontics\Analysis;

use App\Modules\Orthodontics\Analysis\Geometry as G;

/**
 * Facial and smile measurements from named soft-tissue landmarks on a photo.
 * Points may come from MediaPipe Face Landmarker (mapped to these names in the
 * browser) and are always editable by the doctor.
 *
 * Input `data`:
 *   view: frontal | smile | profile
 *   landmarks: { "PupR": {x, y}, ... }  image pixels, y down. R/L = patient's side.
 *   calibration: { p1, p2, known_mm }    optional ruler in the photo plane
 *
 * Photos have perspective and lens distortion: ratios and angles are the
 * primary output, and lengths are only given in mm after a ruler calibration
 * (and are still approximate).
 */
final class FaceAnalysisCalculator
{
    public const VERSION = '1.0.0';

    public const LANDMARKS = [
        'Tr' => 'Trichion', 'G' => 'Glabella', 'Ns' => 'Soft tissue nasion', 'Prn' => 'Pronasale',
        'Cm' => 'Columella', 'Sn' => 'Subnasale', 'Ls' => 'Labrale superius', 'Sto' => 'Stomion',
        'Li' => 'Labrale inferius', 'PogS' => 'Soft tissue pogonion', 'MeS' => 'Soft tissue menton',
        'PupR' => 'Pupil (right)', 'PupL' => 'Pupil (left)', 'ExR' => 'Exocanthion (right)', 'ExL' => 'Exocanthion (left)',
        'EnR' => 'Endocanthion (right)', 'EnL' => 'Endocanthion (left)', 'AlR' => 'Alare (right)', 'AlL' => 'Alare (left)',
        'ChR' => 'Cheilion (right)', 'ChL' => 'Cheilion (left)', 'ZyR' => 'Zygion (right)', 'ZyL' => 'Zygion (left)',
        'GoR' => 'Soft tissue gonion (right)', 'GoL' => 'Soft tissue gonion (left)',
        'DM' => 'Upper dental midline', 'UIE' => 'Upper incisor edge', 'UIG' => 'Upper incisor gingival margin',
        'Stms' => 'Upper lip lower border (smile)', 'DR' => 'Visible dentition (right end)', 'DL' => 'Visible dentition (left end)',
    ];

    private const PAIRS = [
        'pupils' => ['PupR', 'PupL'], 'exocanthion' => ['ExR', 'ExL'], 'endocanthion' => ['EnR', 'EnL'],
        'alare' => ['AlR', 'AlL'], 'cheilion' => ['ChR', 'ChL'], 'zygion' => ['ZyR', 'ZyL'], 'gonion' => ['GoR', 'GoL'],
    ];

    public static function analyze(array $data): array
    {
        $p = [];
        foreach ($data['landmarks'] ?? [] as $code => $pt) {
            if (isset(self::LANDMARKS[$code], $pt['x'], $pt['y']) && is_numeric($pt['x']) && is_numeric($pt['y'])) {
                $p[$code] = [(float) $pt['x'], (float) $pt['y']];
            }
        }
        $cal = CephalometricCalculator::calibration(array_intersect_key($data['calibration'] ?? [], array_flip(['p1', 'p2', 'known_mm'])));
        $mmPerPx = $cal['mm_per_px'];
        $len = fn (?float $px) => $px === null ? null : round($mmPerPx ? $px * $mmPerPx : $px, 2);
        $lenUnit = $mmPerPx ? 'mm' : 'px';
        $view = in_array($data['view'] ?? null, ['frontal', 'smile', 'profile'], true) ? $data['view'] : 'frontal';

        $out = [
            'version' => self::VERSION,
            'view' => $view,
            'calibrated' => (bool) $mmPerPx,
            'length_unit' => $lenUnit,
            'approximate' => true,
        ];

        if ($view === 'profile') {
            return $out + ['profile' => self::profile($p, $len)];
        }

        $midline = self::midline($p);
        $out['midline'] = $midline ? ['defined_by' => $midline['defined_by']] : null;
        $out['thirds'] = self::thirds($p, $midline, $len);
        $out['symmetry'] = self::symmetry($p, $midline, $len);
        $out['midline_deviation'] = self::deviations($p, $midline, $len, ['Sn', 'Ls', 'Li', 'PogS', 'MeS', 'DM']);
        $out['proportions'] = self::proportions($p);

        if ($view === 'smile') {
            $out['smile'] = self::smile($p, $midline, $len);
        }

        return $out;
    }

    /**
     * Facial midline: through the midpoint between the pupils (or the inner
     * eye corners), perpendicular to the interpupillary line.
     */
    private static function midline(array $p): ?array
    {
        foreach ([['PupR', 'PupL', 'pupils'], ['EnR', 'EnL', 'endocanthion'], ['ExR', 'ExL', 'exocanthion']] as [$r, $l, $name]) {
            if (isset($p[$r], $p[$l]) && G::distance($p[$r], $p[$l]) > 1e-9) {
                $origin = G::midpoint($p[$r], $p[$l]);
                $across = G::sub($p[$l], $p[$r]); // towards the patient's left
                $n = G::norm($across);
                $acrossU = [$across[0] / $n, $across[1] / $n];
                // Down the face: perpendicular to `across`, pointing to +y.
                $down = [-$acrossU[1], $acrossU[0]];
                if ($down[1] < 0) {
                    $down = [-$down[0], -$down[1]];
                }

                return ['origin' => $origin, 'across' => $acrossU, 'down' => $down, 'defined_by' => $name];
            }
        }

        return null;
    }

    private static function thirds(array $p, ?array $m, callable $len): array
    {
        $down = $m['down'] ?? [0.0, 1.0];
        $h = fn (string $a, string $b) => isset($p[$a], $p[$b]) ? abs(G::dot(G::sub($p[$b], $p[$a]), $down)) : null;
        $upper = $h('Tr', 'G');
        $middle = $h('G', 'Sn');
        $lower = $h('Sn', 'MeS');
        $total = ($upper !== null && $middle !== null && $lower !== null) ? $upper + $middle + $lower : null;
        $upperLip = $h('Sn', 'Sto');
        $lowerLipChin = $h('Sto', 'MeS');

        return [
            'upper' => $len($upper), 'middle' => $len($middle), 'lower' => $len($lower),
            'upper_pct' => $total ? round($upper / $total * 100, 1) : null,
            'middle_pct' => $total ? round($middle / $total * 100, 1) : null,
            'lower_pct' => $total ? round($lower / $total * 100, 1) : null,
            // Middle : lower, available without the hairline point.
            'middle_to_lower' => ($middle && $lower) ? round($middle / $lower, 3) : null,
            // Sn-Sto : Sto-Me' (the classical canon is about 1 : 2).
            'upper_lip_to_lower_face' => ($upperLip && $lowerLipChin) ? round($upperLip / $lowerLipChin, 3) : null,
            'reference' => 'Classical canon: equal thirds; Sn–Sto about one third of Sn–Me′. Comparison only.',
        ];
    }

    /**
     * Distance of each side of a pair to the midline and the asymmetry index
     * |dR - dL| / (dR + dL) x 100, plus the tilt (cant) of the pair line
     * against the interpupillary line.
     */
    private static function symmetry(array $p, ?array $m, callable $len): array
    {
        if (!$m) {
            return [];
        }
        $out = [];
        foreach (self::PAIRS as $name => [$r, $l]) {
            if (!isset($p[$r], $p[$l])) {
                continue;
            }
            $dR = abs(G::dot(G::sub($p[$r], $m['origin']), $m['across']));
            $dL = abs(G::dot(G::sub($p[$l], $m['origin']), $m['across']));
            $pair = G::sub($p[$l], $p[$r]);
            $cant = rad2deg(atan2(G::cross2($m['across'], $pair), G::dot($m['across'], $pair)));
            $out[$name] = [
                'right' => $len($dR),
                'left' => $len($dL),
                'asymmetry_pct' => ($dR + $dL) > 1e-9 ? round(abs($dR - $dL) / ($dR + $dL) * 100, 1) : null,
                // Positive: the patient's left point is lower in the image.
                'cant_deg' => $name === 'pupils' ? 0.0 : round($cant, 2),
            ];
        }

        return $out;
    }

    /**
     * Signed distance from the facial midline; positive towards the patient's left.
     */
    private static function deviations(array $p, ?array $m, callable $len, array $codes): array
    {
        if (!$m) {
            return [];
        }
        $out = [];
        foreach ($codes as $code) {
            if (isset($p[$code])) {
                $out[$code] = $len(G::dot(G::sub($p[$code], $m['origin']), $m['across']));
            }
        }

        return $out;
    }

    private static function proportions(array $p): array
    {
        $d = fn (string $a, string $b) => isset($p[$a], $p[$b]) ? G::distance($p[$a], $p[$b]) : null;
        $ratio = fn (?float $a, ?float $b) => ($a !== null && $b) ? round($a / $b, 3) : null;

        $faceHeight = $d('Ns', 'MeS');
        $faceWidth = $d('ZyR', 'ZyL');

        return [
            // Facial index (Ns-Me' / Zy-Zy x 100).
            'facial_index' => ($faceHeight !== null && $faceWidth) ? round($faceHeight / $faceWidth * 100, 1) : null,
            'intercanthal_to_alar' => $ratio($d('EnR', 'EnL'), $d('AlR', 'AlL')),
            'mouth_to_interpupillary' => $ratio($d('ChR', 'ChL'), $d('PupR', 'PupL')),
            'intercanthal_to_eye_width_right' => $ratio($d('EnR', 'EnL'), $d('ExR', 'EnR')),
        ];
    }

    private static function smile(array $p, ?array $m, callable $len): array
    {
        $width = isset($p['ChR'], $p['ChL']) ? G::distance($p['ChR'], $p['ChL']) : null;
        $dentition = isset($p['DR'], $p['DL']) ? G::distance($p['DR'], $p['DL']) : null;
        $down = $m['down'] ?? [0.0, 1.0];
        $vertical = fn (string $a, string $b) => isset($p[$a], $p[$b]) ? G::dot(G::sub($p[$b], $p[$a]), $down) : null;

        return [
            'smile_width' => $len($width),
            'smile_to_interpupillary' => ($width && isset($p['PupR'], $p['PupL'])) ? round($width / G::distance($p['PupR'], $p['PupL']), 3) : null,
            // (smile width - visible dentition width) / smile width x 100, both sides together.
            'buccal_corridor_pct' => ($width && $dentition !== null) ? round(($width - $dentition) / $width * 100, 1) : null,
            // Upper lip border to the incisal edge (incisor display) and to the
            // gingival margin (negative = gingiva shows).
            'incisor_display' => $len($vertical('Stms', 'UIE')),
            'gingival_display' => isset($p['Stms'], $p['UIG']) ? $len(-$vertical('Stms', 'UIG')) : null,
            'dental_midline_deviation' => isset($p['DM']) && $m ? $len(G::dot(G::sub($p['DM'], $m['origin']), $m['across'])) : null,
        ];
    }

    private static function profile(array $p, callable $len): array
    {
        // Facing: the nose tip is in front of subnasale.
        $facing = isset($p['Prn'], $p['Sn']) ? ($p['Prn'][0] >= $p['Sn'][0] ? 1.0 : -1.0) : 1.0;
        $ant = [$facing, 0.0];

        $nasolabial = isset($p['Cm'], $p['Sn'], $p['Ls']) ? G::angleAt($p['Cm'], $p['Sn'], $p['Ls']) : null;
        $convexity = null;
        if (isset($p['G'], $p['Sn'], $p['PogS'])) {
            $angle = G::angleAt($p['G'], $p['Sn'], $p['PogS']);
            $side = G::signedDistanceToLine2($p['Sn'], $p['G'], $p['PogS'], $ant);
            $convexity = ($side >= 0 ? 1 : -1) * (180 - $angle);
        }
        $eLine = fn (string $code) => isset($p[$code], $p['Prn'], $p['PogS'])
            ? $len(G::signedDistanceToLine2($p[$code], $p['Prn'], $p['PogS'], $ant))
            : null;

        return [
            'facing' => $facing > 0 ? 'right' : 'left',
            'nasolabial_angle' => G::round($nasolabial),
            'nasolabial_norm' => ['mean' => 102, 'sd' => 8, 'source' => 'Legan & Burstone 1980'],
            'facial_convexity' => G::round($convexity),
            'facial_convexity_norm' => ['mean' => 12, 'sd' => 4, 'source' => 'Legan & Burstone 1980 (G–Sn–Pog′)'],
            'upper_lip_E' => $eLine('Ls'),
            'lower_lip_E' => $eLine('Li'),
            'lower_lip_E_norm' => ['mean' => -2, 'sd' => 2, 'source' => 'Ricketts 1968 (adult)'],
        ];
    }
}
