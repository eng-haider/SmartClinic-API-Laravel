<?php

namespace Tests\Unit\Orthodontics;

use App\Modules\Orthodontics\Analysis\CbctMeasurementCalculator;
use App\Modules\Orthodontics\Analysis\FaceAnalysisCalculator;
use PHPUnit\Framework\TestCase;

class FaceAndCbctCalculatorTest extends TestCase
{
    private function p(float $x, float $y): array
    {
        return ['x' => $x, 'y' => $y];
    }

    public function test_frontal_thirds_symmetry_and_midline(): void
    {
        $r = FaceAnalysisCalculator::analyze([
            'view' => 'frontal',
            'landmarks' => [
                'PupR' => $this->p(80, 100), 'PupL' => $this->p(140, 100),   // midline x = 110
                'Tr' => $this->p(110, 0), 'G' => $this->p(110, 60), 'Sn' => $this->p(110, 120), 'Sto' => $this->p(110, 140), 'MeS' => $this->p(110, 180),
                'ChR' => $this->p(85, 140), 'ChL' => $this->p(145, 142),     // right 25, left 35 from midline
                'Ls' => $this->p(113, 132),
            ],
            'calibration' => ['p1' => $this->p(0, 0), 'p2' => $this->p(100, 0), 'known_mm' => 50],
        ]);

        $this->assertTrue($r['calibrated']);
        $this->assertSame('pupils', $r['midline']['defined_by']);
        $this->assertEqualsWithDelta(30.0, $r['thirds']['upper'], 1e-9);      // 60 px * 0.5
        $this->assertEqualsWithDelta(33.3, $r['thirds']['upper_pct'], 0.05);
        $this->assertEqualsWithDelta(1.0, $r['thirds']['middle_to_lower'], 1e-9);
        $this->assertEqualsWithDelta(0.5, $r['thirds']['upper_lip_to_lower_face'], 1e-9);
        $this->assertEqualsWithDelta(16.7, $r['symmetry']['cheilion']['asymmetry_pct'], 0.05); // |25-35|/60
        $this->assertEqualsWithDelta(rad2deg(atan2(2, 60)), $r['symmetry']['cheilion']['cant_deg'], 0.01);
        $this->assertEqualsWithDelta(1.5, $r['midline_deviation']['Ls'], 1e-9); // 3 px to the patient's left
    }

    public function test_profile_angles(): void
    {
        $r = FaceAnalysisCalculator::analyze([
            'view' => 'profile',
            'landmarks' => [
                'Cm' => $this->p(110, 90), 'Sn' => $this->p(100, 100), 'Ls' => $this->p(100, 130),   // 135°
                'G' => $this->p(100, 0), 'PogS' => $this->p(100, 200),                               // straight profile
                'Prn' => $this->p(130, 80), 'Li' => $this->p(100, 160),
            ],
        ]);

        $this->assertSame('right', $r['profile']['facing']);
        $this->assertEqualsWithDelta(135.0, $r['profile']['nasolabial_angle'], 0.01);
        $this->assertEqualsWithDelta(0.0, $r['profile']['facial_convexity'], 0.01);
        $this->assertLessThan(0, $r['profile']['lower_lip_E']); // behind the E-line
        $this->assertSame('px', $r['length_unit']);
    }

    public function test_cbct_measurements_and_implants(): void
    {
        $r = CbctMeasurementCalculator::analyze([
            'measurements' => [
                ['type' => 'distance', 'label' => 'd', 'points' => [[0, 0, 0], [3, 4, 12]]],
                ['type' => 'angle', 'label' => 'a', 'points' => [[1, 0, 0], [0, 0, 0], [0, 1, 0]]],
            ],
            'cross_sections' => [['label' => 'bone height', 'kind' => 'bone_height', 'spacing_mm' => [0.2, 0.25], 'points' => [[10, 10], [10, 58]]]],
            'implants' => [['label' => 'I1', 'position' => [0, 0, 0], 'direction' => [0, 0, -2], 'diameter_mm' => 4, 'length_mm' => 10]],
        ]);

        $this->assertSame(13.0, $r['measurements'][0]['value']);
        $this->assertSame(90.0, $r['measurements'][1]['value']);
        $this->assertSame(12.0, $r['cross_sections'][0]['value']);
        $this->assertSame([0.0, 0.0, -10.0], $r['implants'][0]['apex']);
        $this->assertSame(0.0, $r['implants'][0]['tilt_from_vertical_deg']);
    }
}
