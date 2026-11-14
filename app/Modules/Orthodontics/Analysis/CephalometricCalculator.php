<?php

namespace App\Modules\Orthodontics\Analysis;

use App\Modules\Orthodontics\Analysis\Geometry as G;

/**
 * Cephalometric measurements from manually placed (or doctor-confirmed)
 * landmarks on a lateral cephalogram.
 *
 * Input `data`:
 *   landmarks:   { "S": {"x": 412.5, "y": 300.1}, ... }   image pixels, y down
 *   calibration: { "mm_per_px": 0.1 } or
 *                { "p1": {x,y}, "p2": {x,y}, "known_mm": 10 }   ruler on the film
 *                optional "magnification": 1.1 (divides mm values; only for a
 *                detector pixel spacing - a ruler at the midsagittal plane is
 *                already magnified like the anatomy)
 *   facing:      "right" | "left" (optional; otherwise taken from S -> N)
 *
 * Angles do not depend on calibration. Linear values are returned in px until
 * the image is calibrated, and are flagged `calibrated: false`.
 *
 * Every formula is documented in docs/ORTHODONTICS_MODULE.md. Reference
 * values are the published means/ranges of the original authors (mostly
 * adult white samples); they are shown for comparison only and are not a
 * diagnosis. The JavaScript twin (frontend src/modules/orthodontics/analysis/
 * ceph.js) uses the same formulas and is tested against the same fixture.
 */
final class CephalometricCalculator
{
    public const VERSION = '1.0.0';

    public const LANDMARKS = [
        'S' => 'Sella', 'N' => 'Nasion', 'Or' => 'Orbitale', 'Po' => 'Porion',
        'A' => 'Point A (Subspinale)', 'B' => 'Point B (Supramentale)',
        'ANS' => 'Anterior nasal spine', 'PNS' => 'Posterior nasal spine',
        'Pog' => 'Pogonion', 'Gn' => 'Gnathion', 'Me' => 'Menton', 'Go' => 'Gonion',
        'Ar' => 'Articulare', 'Co' => 'Condylion', 'Ba' => 'Basion', 'Pt' => 'Pterygoid point',
        'U1T' => 'Upper incisor tip', 'U1A' => 'Upper incisor root apex',
        'L1T' => 'Lower incisor tip', 'L1A' => 'Lower incisor root apex',
        'U6' => 'Upper first molar mesial cusp', 'L6' => 'Lower first molar mesial cusp',
        'Prn' => 'Pronasale (soft tissue)', 'Sn' => 'Subnasale (soft tissue)',
        'Ls' => 'Labrale superius', 'Li' => 'Labrale inferius', 'PogS' => 'Soft tissue pogonion',
    ];

    /**
     * Which measurements each analysis shows, with the reference values of
     * that analysis' author where they differ from the measurement default.
     */
    public const ANALYSES = [
        'steiner' => [
            'SNA' => null, 'SNB' => null, 'ANB' => null, 'SN_GoGn' => null, 'SN_OP' => null,
            'U1_NA_deg' => null, 'U1_NA_mm' => null, 'L1_NB_deg' => null, 'L1_NB_mm' => null,
            'interincisal' => ['mean' => 131, 'sd' => null, 'source' => 'Steiner 1953'],
        ],
        'downs' => [
            'facial_angle' => null, 'convexity' => null, 'AB_plane' => null,
            'FMA' => ['mean' => 21.9, 'sd' => 3.2, 'source' => 'Downs 1948'],
            'Y_axis' => null, 'OP_cant' => null,
            'interincisal' => ['mean' => 135.4, 'sd' => 5.8, 'source' => 'Downs 1948'],
            'L1_OP' => null,
            'IMPA' => ['mean' => 91.4, 'sd' => 3.8, 'source' => 'Downs 1948 (reported as 1.4 ± 3.8 from 90°)'],
            'U1_APog_mm' => null,
        ],
        'tweed' => ['FMA' => null, 'IMPA' => null, 'FMIA' => null],
        'ricketts' => [
            'facial_axis' => null,
            'facial_angle' => ['mean' => 87, 'sd' => 3, 'source' => 'Ricketts (facial depth, age 9; +0.3°/year)'],
            'FMA' => ['mean' => 26, 'sd' => 4.5, 'source' => 'Ricketts (age 9; -0.3°/year)'],
            'convexity_mm' => null, 'L1_APog_mm' => null, 'L1_APog_deg' => null, 'lower_lip_E' => null,
            'upper_lip_E' => null,
        ],
        'mcnamara' => [
            'A_Nperp' => null, 'Pog_Nperp' => null, 'Co_A' => null, 'Co_Gn' => null,
            'mm_differential' => null, 'LAFH' => null, 'U1_Avert_mm' => null,
            'FMA' => ['mean' => 22, 'sd' => 4, 'source' => 'McNamara 1984 (adult)'],
            'facial_axis' => null,
        ],
        'wits' => ['wits' => null],
        'bjork_jarabak' => ['saddle' => null, 'articular' => null, 'gonial' => null, 'bjork_sum' => null, 'jarabak_ratio' => null, 'N_Me' => null, 'S_Go' => null],
        'other' => ['U1_SN' => null, 'FH_SN' => null],
    ];

    /**
     * Measurement definitions: unit (deg | len | pct), the landmarks it needs,
     * and its default reference value.
     */
    private static function definitions(): array
    {
        return [
            'SNA' => ['name' => 'SNA', 'unit' => 'deg', 'needs' => ['S', 'N', 'A'], 'norm' => ['mean' => 82, 'sd' => 2, 'source' => 'Steiner 1953']],
            'SNB' => ['name' => 'SNB', 'unit' => 'deg', 'needs' => ['S', 'N', 'B'], 'norm' => ['mean' => 80, 'sd' => 2, 'source' => 'Steiner 1953']],
            'ANB' => ['name' => 'ANB', 'unit' => 'deg', 'needs' => ['S', 'N', 'A', 'B'], 'norm' => ['mean' => 2, 'sd' => 2, 'source' => 'Steiner 1953']],
            'SN_GoGn' => ['name' => 'SN – GoGn', 'unit' => 'deg', 'needs' => ['S', 'N', 'Go', 'Gn'], 'norm' => ['mean' => 32, 'sd' => 5, 'source' => 'Steiner 1953']],
            'SN_OP' => ['name' => 'SN – Occlusal plane', 'unit' => 'deg', 'needs' => ['S', 'N', 'U1T', 'L1T', 'U6', 'L6'], 'norm' => ['mean' => 14, 'sd' => null, 'source' => 'Steiner 1953']],
            'U1_NA_deg' => ['name' => 'U1 – NA (angle)', 'unit' => 'deg', 'needs' => ['N', 'A', 'U1T', 'U1A'], 'norm' => ['mean' => 22, 'sd' => null, 'source' => 'Steiner 1953']],
            'U1_NA_mm' => ['name' => 'U1 – NA (distance)', 'unit' => 'len', 'needs' => ['N', 'A', 'U1T', 'S'], 'norm' => ['mean' => 4, 'sd' => null, 'source' => 'Steiner 1953']],
            'L1_NB_deg' => ['name' => 'L1 – NB (angle)', 'unit' => 'deg', 'needs' => ['N', 'B', 'L1T', 'L1A'], 'norm' => ['mean' => 25, 'sd' => null, 'source' => 'Steiner 1953']],
            'L1_NB_mm' => ['name' => 'L1 – NB (distance)', 'unit' => 'len', 'needs' => ['N', 'B', 'L1T', 'S'], 'norm' => ['mean' => 4, 'sd' => null, 'source' => 'Steiner 1953']],
            'interincisal' => ['name' => 'Interincisal angle', 'unit' => 'deg', 'needs' => ['U1T', 'U1A', 'L1T', 'L1A'], 'norm' => ['mean' => 131, 'sd' => null, 'source' => 'Steiner 1953']],
            'facial_angle' => ['name' => 'Facial angle (FH – NPog)', 'unit' => 'deg', 'needs' => ['Po', 'Or', 'N', 'Pog'], 'norm' => ['mean' => 87.8, 'sd' => 3.6, 'source' => 'Downs 1948']],
            'convexity' => ['name' => 'Angle of convexity (N-A-Pog)', 'unit' => 'deg', 'needs' => ['N', 'A', 'Pog', 'S'], 'norm' => ['mean' => 0, 'sd' => 5.1, 'source' => 'Downs 1948']],
            'AB_plane' => ['name' => 'A-B plane angle', 'unit' => 'deg', 'needs' => ['A', 'B', 'N', 'Pog', 'S'], 'norm' => ['mean' => -4.6, 'sd' => 3.7, 'source' => 'Downs 1948']],
            'FMA' => ['name' => 'FMA (FH – mandibular plane GoMe)', 'unit' => 'deg', 'needs' => ['Po', 'Or', 'Go', 'Me'], 'norm' => ['mean' => 25, 'sd' => 5, 'source' => 'Tweed 1954']],
            'Y_axis' => ['name' => 'Y-axis (FH – SGn)', 'unit' => 'deg', 'needs' => ['Po', 'Or', 'S', 'Gn'], 'norm' => ['mean' => 59.4, 'sd' => 3.8, 'source' => 'Downs 1948']],
            'OP_cant' => ['name' => 'Cant of occlusal plane (FH – OP)', 'unit' => 'deg', 'needs' => ['Po', 'Or', 'U1T', 'L1T', 'U6', 'L6', 'S', 'N'], 'norm' => ['mean' => 9.3, 'sd' => 3.8, 'source' => 'Downs 1948']],
            'L1_OP' => ['name' => 'L1 – occlusal plane (from 90°)', 'unit' => 'deg', 'needs' => ['L1T', 'L1A', 'U1T', 'U6', 'L6'], 'norm' => ['mean' => 14.5, 'sd' => 3.5, 'source' => 'Downs 1948']],
            'IMPA' => ['name' => 'IMPA (L1 – mandibular plane)', 'unit' => 'deg', 'needs' => ['L1T', 'L1A', 'Go', 'Me'], 'norm' => ['mean' => 90, 'sd' => 5, 'source' => 'Tweed 1954']],
            'FMIA' => ['name' => 'FMIA (180 − FMA − IMPA)', 'unit' => 'deg', 'needs' => ['Po', 'Or', 'Go', 'Me', 'L1T', 'L1A'], 'norm' => ['mean' => 65, 'sd' => null, 'source' => 'Tweed 1954']],
            'U1_APog_mm' => ['name' => 'U1 – APog (distance)', 'unit' => 'len', 'needs' => ['U1T', 'A', 'Pog', 'S', 'N'], 'norm' => ['mean' => 2.7, 'sd' => 1.8, 'source' => 'Downs 1948']],
            'facial_axis' => ['name' => 'Facial axis (BaN – PtGn)', 'unit' => 'deg', 'needs' => ['Ba', 'N', 'Pt', 'Gn'], 'norm' => ['mean' => 90, 'sd' => 3.5, 'source' => 'Ricketts 1981']],
            'convexity_mm' => ['name' => 'Convexity (A – NPog)', 'unit' => 'len', 'needs' => ['A', 'N', 'Pog', 'S'], 'norm' => ['mean' => 2, 'sd' => 2, 'source' => 'Ricketts (age 9; -0.2 mm/year)']],
            'L1_APog_mm' => ['name' => 'L1 – APog (distance)', 'unit' => 'len', 'needs' => ['L1T', 'A', 'Pog', 'S', 'N'], 'norm' => ['mean' => 1, 'sd' => 2, 'source' => 'Ricketts 1981']],
            'L1_APog_deg' => ['name' => 'L1 – APog (angle)', 'unit' => 'deg', 'needs' => ['L1T', 'L1A', 'A', 'Pog'], 'norm' => ['mean' => 22, 'sd' => 4, 'source' => 'Ricketts 1981']],
            'lower_lip_E' => ['name' => 'Lower lip – E-line', 'unit' => 'len', 'needs' => ['Li', 'Prn', 'PogS', 'S', 'N'], 'norm' => ['mean' => -2, 'sd' => 2, 'source' => 'Ricketts 1968 (adult)']],
            'upper_lip_E' => ['name' => 'Upper lip – E-line', 'unit' => 'len', 'needs' => ['Ls', 'Prn', 'PogS', 'S', 'N'], 'norm' => null],
            'A_Nperp' => ['name' => 'A – N perpendicular', 'unit' => 'len', 'needs' => ['A', 'N', 'Po', 'Or'], 'norm' => ['min' => 0, 'max' => 1, 'source' => 'McNamara 1984 (adult)']],
            'Pog_Nperp' => ['name' => 'Pog – N perpendicular', 'unit' => 'len', 'needs' => ['Pog', 'N', 'Po', 'Or'], 'norm' => null],
            'Co_A' => ['name' => 'Midface length (Co – A)', 'unit' => 'len', 'needs' => ['Co', 'A'], 'norm' => null],
            'Co_Gn' => ['name' => 'Mandibular length (Co – Gn)', 'unit' => 'len', 'needs' => ['Co', 'Gn'], 'norm' => null],
            'mm_differential' => ['name' => 'Maxillomandibular differential', 'unit' => 'len', 'needs' => ['Co', 'A', 'Gn'], 'norm' => null],
            'LAFH' => ['name' => 'Lower anterior face height (ANS – Me)', 'unit' => 'len', 'needs' => ['ANS', 'Me'], 'norm' => null],
            'U1_Avert_mm' => ['name' => 'U1 – A vertical', 'unit' => 'len', 'needs' => ['U1T', 'A', 'Po', 'Or', 'N'], 'norm' => ['min' => 4, 'max' => 6, 'source' => 'McNamara 1984']],
            'wits' => ['name' => 'Wits appraisal (AO – BO)', 'unit' => 'len', 'needs' => ['A', 'B', 'U1T', 'L1T', 'U6', 'L6', 'S', 'N'], 'norm' => ['mean' => null, 'sd' => null, 'source' => 'Jacobson 1975: about 0 mm (female), about −1 mm (male)']],
            'saddle' => ['name' => 'Saddle angle (N-S-Ar)', 'unit' => 'deg', 'needs' => ['N', 'S', 'Ar'], 'norm' => ['mean' => 123, 'sd' => 5, 'source' => 'Björk / Jarabak']],
            'articular' => ['name' => 'Articular angle (S-Ar-Go)', 'unit' => 'deg', 'needs' => ['S', 'Ar', 'Go'], 'norm' => ['mean' => 143, 'sd' => 6, 'source' => 'Björk / Jarabak']],
            'gonial' => ['name' => 'Gonial angle (Ar-Go-Me)', 'unit' => 'deg', 'needs' => ['Ar', 'Go', 'Me'], 'norm' => ['mean' => 130, 'sd' => 7, 'source' => 'Björk / Jarabak']],
            'bjork_sum' => ['name' => 'Björk sum', 'unit' => 'deg', 'needs' => ['N', 'S', 'Ar', 'Go', 'Me'], 'norm' => ['mean' => 396, 'sd' => 6, 'source' => 'Björk 1969']],
            'jarabak_ratio' => ['name' => 'Jarabak ratio (S-Go / N-Me)', 'unit' => 'pct', 'needs' => ['S', 'Go', 'N', 'Me'], 'norm' => ['min' => 62, 'max' => 65, 'source' => 'Jarabak 1972']],
            'N_Me' => ['name' => 'Anterior face height (N – Me)', 'unit' => 'len', 'needs' => ['N', 'Me'], 'norm' => null],
            'S_Go' => ['name' => 'Posterior face height (S – Go)', 'unit' => 'len', 'needs' => ['S', 'Go'], 'norm' => null],
            'U1_SN' => ['name' => 'U1 – SN', 'unit' => 'deg', 'needs' => ['U1T', 'U1A', 'S', 'N'], 'norm' => ['mean' => 103, 'sd' => null, 'source' => 'Riedel / Steiner']],
            'FH_SN' => ['name' => 'FH – SN', 'unit' => 'deg', 'needs' => ['Po', 'Or', 'S', 'N'], 'norm' => ['mean' => 7, 'sd' => null, 'source' => 'Commonly cited mean']],
        ];
    }

    /**
     * @return array{version: string, calibration: array, facing: string|null, measurements: array, analyses: array}
     */
    public static function analyze(array $data): array
    {
        $p = self::landmarks($data['landmarks'] ?? []);
        $calibration = self::calibration($data['calibration'] ?? []);
        $facing = self::facing($data['facing'] ?? null, $p);
        $ant = [$facing === 'left' ? -1.0 : 1.0, 0.0];

        $mmPerPx = $calibration['mm_per_px'];
        $len = fn (?float $px) => $px === null ? null : ($mmPerPx ? $px * $mmPerPx : $px);

        // Anterior-up frame: x towards the face, y up.
        $f = $ant[0];
        $frame = fn (array $v) => [$f * $v[0], -$v[1]];
        // Inclination of a downward / upward pointing vector, measured from
        // straight down / up towards anterior (degrees).
        $fromDown = function (array $v) use ($frame) {
            $w = $frame($v);

            return rad2deg(atan2($w[0], -$w[1]));
        };
        $fromUp = function (array $v) use ($frame) {
            $w = $frame($v);

            return rad2deg(atan2($w[0], $w[1]));
        };

        $op = fn () => isset($p['U1T'], $p['L1T'], $p['U6'], $p['L6'])
            ? ['ant' => G::midpoint($p['U1T'], $p['L1T']), 'post' => G::midpoint($p['U6'], $p['L6'])]
            : null;

        $values = [];
        $compute = [
            'SNA' => fn () => G::angleAt($p['S'], $p['N'], $p['A']),
            'SNB' => fn () => G::angleAt($p['S'], $p['N'], $p['B']),
            'ANB' => fn () => G::angleAt($p['S'], $p['N'], $p['A']) - G::angleAt($p['S'], $p['N'], $p['B']),
            'SN_GoGn' => fn () => G::angleBetween(G::sub($p['N'], $p['S']), G::sub($p['Gn'], $p['Go'])),
            'SN_OP' => function () use ($p, $op) {
                $o = $op();

                return G::angleBetween(G::sub($p['N'], $p['S']), G::sub($o['ant'], $o['post']));
            },
            'U1_NA_deg' => fn () => $fromDown(G::sub($p['U1T'], $p['U1A'])) - $fromDown(G::sub($p['A'], $p['N'])),
            'U1_NA_mm' => fn () => $len(G::signedDistanceToLine2($p['U1T'], $p['N'], $p['A'], $ant)),
            'L1_NB_deg' => fn () => $fromUp(G::sub($p['L1T'], $p['L1A'])) - $fromUp(G::sub($p['N'], $p['B'])),
            'L1_NB_mm' => fn () => $len(G::signedDistanceToLine2($p['L1T'], $p['N'], $p['B'], $ant)),
            'interincisal' => fn () => G::angleBetween(G::sub($p['U1A'], $p['U1T']), G::sub($p['L1A'], $p['L1T'])),
            'facial_angle' => fn () => G::angleBetween(G::sub($p['Po'], $p['Or']), G::sub($p['Pog'], $p['N'])),
            'convexity' => function () use ($p, $ant) {
                $side = G::signedDistanceToLine2($p['A'], $p['N'], $p['Pog'], $ant);
                $angle = G::angleAt($p['N'], $p['A'], $p['Pog']);
                if ($side === null || $angle === null) {
                    return null;
                }

                return ($side >= 0 ? 1 : -1) * (180 - $angle);
            },
            'AB_plane' => fn () => $fromDown(G::sub($p['B'], $p['A'])) - $fromDown(G::sub($p['Pog'], $p['N'])),
            'FMA' => fn () => G::angleBetween(G::sub($p['Or'], $p['Po']), G::sub($p['Me'], $p['Go'])),
            'Y_axis' => fn () => G::angleBetween(G::sub($p['Or'], $p['Po']), G::sub($p['Gn'], $p['S'])),
            'OP_cant' => function () use ($p, $op, $frame) {
                $o = $op();
                $fh = $frame(G::sub($p['Or'], $p['Po']));
                $occ = $frame(G::sub($o['ant'], $o['post']));

                // Positive when the occlusal plane descends anteriorly relative to FH.
                return rad2deg(atan2($fh[1], $fh[0])) - rad2deg(atan2($occ[1], $occ[0]));
            },
            'L1_OP' => function () use ($p, $op) {
                $o = $op();

                return G::angleBetween(G::sub($p['L1T'], $p['L1A']), G::sub($o['post'], $o['ant'])) - 90;
            },
            'IMPA' => fn () => G::angleBetween(G::sub($p['L1T'], $p['L1A']), G::sub($p['Go'], $p['Me'])),
            'FMIA' => fn () => 180
                - G::angleBetween(G::sub($p['Or'], $p['Po']), G::sub($p['Me'], $p['Go']))
                - G::angleBetween(G::sub($p['L1T'], $p['L1A']), G::sub($p['Go'], $p['Me'])),
            'U1_APog_mm' => fn () => $len(G::signedDistanceToLine2($p['U1T'], $p['A'], $p['Pog'], $ant)),
            'facial_axis' => fn () => G::angleBetween(G::sub($p['Ba'], $p['N']), G::sub($p['Gn'], $p['Pt'])),
            'convexity_mm' => fn () => $len(G::signedDistanceToLine2($p['A'], $p['N'], $p['Pog'], $ant)),
            'L1_APog_mm' => fn () => $len(G::signedDistanceToLine2($p['L1T'], $p['A'], $p['Pog'], $ant)),
            'L1_APog_deg' => fn () => G::angleBetween(G::sub($p['L1T'], $p['L1A']), G::sub($p['A'], $p['Pog'])),
            'lower_lip_E' => fn () => $len(G::signedDistanceToLine2($p['Li'], $p['Prn'], $p['PogS'], $ant)),
            'upper_lip_E' => fn () => $len(G::signedDistanceToLine2($p['Ls'], $p['Prn'], $p['PogS'], $ant)),
            'A_Nperp' => fn () => $len(self::distanceToNPerp($p['A'], $p['N'], $p['Po'], $p['Or'], $ant)),
            'Pog_Nperp' => fn () => $len(self::distanceToNPerp($p['Pog'], $p['N'], $p['Po'], $p['Or'], $ant)),
            'Co_A' => fn () => $len(G::distance($p['Co'], $p['A'])),
            'Co_Gn' => fn () => $len(G::distance($p['Co'], $p['Gn'])),
            'mm_differential' => fn () => $len(G::distance($p['Co'], $p['Gn']) - G::distance($p['Co'], $p['A'])),
            'LAFH' => fn () => $len(G::distance($p['ANS'], $p['Me'])),
            // Line through A parallel to N-perpendicular.
            'U1_Avert_mm' => fn () => $len(self::distanceToNPerp($p['U1T'], $p['A'], $p['Po'], $p['Or'], $ant)),
            'wits' => function () use ($p, $op, $len) {
                $o = $op();
                $dir = G::sub($o['ant'], $o['post']);
                $n = G::norm($dir);
                if ($n < 1e-12) {
                    return null;
                }
                $ao = G::projectOnLine($p['A'], $o['post'], $o['ant']);
                $bo = G::projectOnLine($p['B'], $o['post'], $o['ant']);

                return $len(G::dot(G::sub($ao, $bo), G::scale($dir, 1 / $n)));
            },
            'saddle' => fn () => G::angleAt($p['N'], $p['S'], $p['Ar']),
            'articular' => fn () => G::angleAt($p['S'], $p['Ar'], $p['Go']),
            'gonial' => fn () => G::angleAt($p['Ar'], $p['Go'], $p['Me']),
            'bjork_sum' => fn () => G::angleAt($p['N'], $p['S'], $p['Ar']) + G::angleAt($p['S'], $p['Ar'], $p['Go']) + G::angleAt($p['Ar'], $p['Go'], $p['Me']),
            'jarabak_ratio' => function () use ($p) {
                $nMe = G::distance($p['N'], $p['Me']);

                return $nMe < 1e-12 ? null : G::distance($p['S'], $p['Go']) / $nMe * 100;
            },
            'N_Me' => fn () => $len(G::distance($p['N'], $p['Me'])),
            'S_Go' => fn () => $len(G::distance($p['S'], $p['Go'])),
            'U1_SN' => fn () => G::angleBetween(G::sub($p['U1T'], $p['U1A']), G::sub($p['S'], $p['N'])),
            'FH_SN' => fn () => G::angleBetween(G::sub($p['Or'], $p['Po']), G::sub($p['N'], $p['S'])),
        ];

        foreach (self::definitions() as $key => $def) {
            $missing = array_values(array_filter($def['needs'], fn ($code) => !isset($p[$code])));
            $value = $missing ? null : ($compute[$key])();
            $unit = match ($def['unit']) {
                'deg' => '°',
                'pct' => '%',
                default => $mmPerPx ? 'mm' : 'px',
            };

            $values[$key] = [
                'key' => $key,
                'name' => $def['name'],
                'value' => $value === null ? null : round($value, 2),
                'unit' => $unit,
                // Angles and ratios never depend on the scale.
                'calibrated' => $def['unit'] !== 'len' || (bool) $mmPerPx,
                'missing' => $missing,
                'norm' => $def['norm'],
            ];
        }

        $analyses = [];
        foreach (self::ANALYSES as $group => $keys) {
            foreach ($keys as $key => $override) {
                $m = $values[$key];
                $norm = $override ?? $m['norm'];
                // A px distance cannot be compared with a mm reference value.
                $comparable = $m['value'] !== null && $m['calibrated'];
                $analyses[$group][] = [
                    'key' => $key,
                    'norm' => $norm,
                    'comparison' => $comparable ? self::compare($m['value'], $norm) : null,
                ];
            }
        }

        return [
            'version' => self::VERSION,
            'calibration' => $calibration,
            'facing' => $facing,
            'measurements' => $values,
            'analyses' => $analyses,
        ];
    }

    /**
     * Where a value sits against a reference: number of SDs from the mean, or
     * inside/outside a min-max range. A numeric comparison, not a diagnosis.
     */
    public static function compare(float $value, ?array $norm): ?array
    {
        if (!$norm) {
            return null;
        }
        if (isset($norm['mean'], $norm['sd']) && $norm['sd'] > 0) {
            $z = ($value - $norm['mean']) / $norm['sd'];

            return ['sd_from_mean' => round($z, 2), 'status' => abs($z) <= 1 ? 'within' : ($z > 0 ? 'above' : 'below')];
        }
        if (isset($norm['min'], $norm['max'])) {
            return ['status' => $value < $norm['min'] ? 'below' : ($value > $norm['max'] ? 'above' : 'within')];
        }

        return null;
    }

    /**
     * mm per pixel from a ruler (two points + known length) or a given value.
     */
    public static function calibration(array $c): array
    {
        $mmPerPx = null;
        $method = null;
        $magnification = isset($c['magnification']) && (float) $c['magnification'] > 0 ? (float) $c['magnification'] : 1.0;

        if (isset($c['p1'], $c['p2'], $c['known_mm']) && (float) $c['known_mm'] > 0) {
            $px = G::distance(self::point($c['p1']), self::point($c['p2']));
            if ($px > 1e-9) {
                $mmPerPx = (float) $c['known_mm'] / $px;
                $method = 'ruler';
                $magnification = 1.0; // the ruler is magnified like the anatomy
            }
        } elseif (isset($c['mm_per_px']) && (float) $c['mm_per_px'] > 0) {
            $mmPerPx = (float) $c['mm_per_px'] / $magnification;
            $method = 'pixel_spacing';
        }

        return [
            'method' => $method,
            'mm_per_px' => $mmPerPx,
            'magnification' => $magnification,
        ];
    }

    private static function facing(?string $requested, array $p): ?string
    {
        if (in_array($requested, ['left', 'right'], true)) {
            return $requested;
        }
        if (isset($p['S'], $p['N'])) {
            return $p['N'][0] >= $p['S'][0] ? 'right' : 'left';
        }

        return null;
    }

    /**
     * Signed distance from P to the line through `origin` perpendicular to
     * Frankfort horizontal (Po-Or); positive anteriorly.
     */
    private static function distanceToNPerp(array $pt, array $origin, array $po, array $or, array $ant): ?float
    {
        $fh = G::sub($or, $po);
        $perpEnd = G::add($origin, [-$fh[1], $fh[0]]);

        return G::signedDistanceToLine2($pt, $origin, $perpEnd, $ant);
    }

    private static function landmarks(array $raw): array
    {
        $out = [];
        foreach ($raw as $code => $point) {
            if (!array_key_exists($code, self::LANDMARKS)) {
                continue;
            }
            $pt = self::point($point);
            if ($pt !== null) {
                $out[$code] = $pt;
            }
        }

        return $out;
    }

    private static function point($point): ?array
    {
        if (is_array($point) && isset($point['x'], $point['y']) && is_numeric($point['x']) && is_numeric($point['y'])) {
            return [(float) $point['x'], (float) $point['y']];
        }
        if (is_array($point) && isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1])) {
            return [(float) $point[0], (float) $point[1]];
        }

        return null;
    }
}
