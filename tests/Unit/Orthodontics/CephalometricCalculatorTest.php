<?php

namespace Tests\Unit\Orthodontics;

use App\Modules\Orthodontics\Analysis\CephalometricCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Landmarks in the fixture are placed by geometric construction (e.g. A on a
 * ray from N at 82° to N-S), so the expected values are known exactly and do
 * not come from the calculator. The frontend twin is tested on the same file.
 */
class CephalometricCalculatorTest extends TestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        $this->fixture = json_decode(file_get_contents(__DIR__ . '/../../Fixtures/orthodontics/ceph_reference.json'), true);
    }

    public function test_reference_fixture_matches_constructed_geometry(): void
    {
        $result = CephalometricCalculator::analyze($this->fixture['input']);
        $m = $result['measurements'];

        foreach ($this->fixture['expected'] as $key => $expected) {
            $this->assertNotNull($m[$key]['value'], "$key should be computed");
            $tolerance = $m[$key]['unit'] === '°' ? $this->fixture['tolerance']['deg'] : $this->fixture['tolerance']['mm'];
            $this->assertEqualsWithDelta($expected, $m[$key]['value'], $tolerance, "$key");
        }

        $this->assertSame('ruler', $result['calibration']['method']);
        $this->assertEqualsWithDelta(0.1, $result['calibration']['mm_per_px'], 1e-12);
        $this->assertSame('right', $result['facing']);
        $this->assertSame('mm', $m['wits']['unit']);
        $this->assertTrue($m['wits']['calibrated']);
    }

    public function test_mirrored_radiograph_gives_the_same_measurements(): void
    {
        $mirrored = $this->fixture['input'];
        foreach ($mirrored['landmarks'] as $code => $p) {
            $mirrored['landmarks'][$code]['x'] = 1000 - $p['x'];
        }

        $a = CephalometricCalculator::analyze($this->fixture['input'])['measurements'];
        $b = CephalometricCalculator::analyze($mirrored)['measurements'];

        $this->assertSame('left', CephalometricCalculator::analyze($mirrored)['facing']);
        foreach ($a as $key => $row) {
            if ($row['value'] !== null) {
                $this->assertEqualsWithDelta($row['value'], $b[$key]['value'], 0.011, "$key changes when the image is mirrored");
            }
        }
    }

    public function test_distances_stay_in_pixels_until_calibrated(): void
    {
        $input = $this->fixture['input'];
        unset($input['calibration']);
        $m = CephalometricCalculator::analyze($input)['measurements'];

        $this->assertSame('px', $m['wits']['unit']);
        $this->assertFalse($m['wits']['calibrated']);
        $this->assertEqualsWithDelta(14.52, $m['wits']['value'], 0.01);
        // Angles never depend on calibration.
        $this->assertTrue($m['SNA']['calibrated']);
        $this->assertEqualsWithDelta(82, $m['SNA']['value'], 0.01);
    }

    public function test_uncalibrated_distances_are_not_compared_with_mm_norms(): void
    {
        $input = $this->fixture['input'];
        unset($input['calibration']);
        $steiner = collect(CephalometricCalculator::analyze($input)['analyses']['steiner'])->keyBy('key');

        $this->assertNull($steiner['U1_NA_mm']['comparison']);
        $this->assertSame('within', $steiner['SNA']['comparison']['status']);
    }

    public function test_pixel_spacing_calibration_applies_magnification(): void
    {
        $cal = CephalometricCalculator::calibration(['mm_per_px' => 0.11, 'magnification' => 1.1]);
        $this->assertEqualsWithDelta(0.1, $cal['mm_per_px'], 1e-12);
        $this->assertSame('pixel_spacing', $cal['method']);
    }

    public function test_missing_landmarks_give_null_and_say_what_is_missing(): void
    {
        $input = $this->fixture['input'];
        unset($input['landmarks']['B']);
        $m = CephalometricCalculator::analyze($input)['measurements'];

        $this->assertNull($m['SNB']['value']);
        $this->assertSame(['B'], $m['SNB']['missing']);
        $this->assertNull($m['ANB']['value']);
        $this->assertNotNull($m['SNA']['value']);
    }

    public function test_reference_comparison_is_numeric(): void
    {
        $this->assertSame(['sd_from_mean' => 1.0, 'status' => 'within'], CephalometricCalculator::compare(84, ['mean' => 82, 'sd' => 2]));
        $this->assertSame('above', CephalometricCalculator::compare(86, ['mean' => 82, 'sd' => 2])['status']);
        $this->assertSame('below', CephalometricCalculator::compare(-0.5, ['min' => 0, 'max' => 1])['status']);
        $this->assertNull(CephalometricCalculator::compare(5, null));
    }

    public function test_unknown_and_malformed_landmarks_are_ignored(): void
    {
        $m = CephalometricCalculator::analyze(['landmarks' => ['S' => ['x' => 'a', 'y' => 1], 'Foo' => ['x' => 1, 'y' => 2]]])['measurements'];
        $this->assertNull($m['SNA']['value']);
        $this->assertContains('S', $m['SNA']['missing']);
    }
}
