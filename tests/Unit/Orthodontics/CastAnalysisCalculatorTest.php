<?php

namespace Tests\Unit\Orthodontics;

use App\Modules\Orthodontics\Analysis\CastAnalysisCalculator;
use PHPUnit\Framework\TestCase;

class CastAnalysisCalculatorTest extends TestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        $this->fixture = json_decode(file_get_contents(__DIR__ . '/../../Fixtures/orthodontics/cast_reference.json'), true);
    }

    public function test_reference_fixture(): void
    {
        $r = CastAnalysisCalculator::analyze($this->fixture['input']);
        $e = $this->fixture['expected'];
        $t = $this->fixture['tolerance'];

        $this->assertEqualsWithDelta($e['bolton_overall_ratio'], $r['bolton']['overall']['ratio'], $t);
        $this->assertSame($e['bolton_overall_excess_arch'], $r['bolton']['overall']['excess_arch']);
        $this->assertEqualsWithDelta($e['bolton_overall_excess'], $r['bolton']['overall']['excess'], $t);
        $this->assertEqualsWithDelta($e['bolton_anterior_ratio'], $r['bolton']['anterior']['ratio'], $t);
        $this->assertSame($e['bolton_anterior_excess_arch'], $r['bolton']['anterior']['excess_arch']);
        $this->assertEqualsWithDelta($e['bolton_anterior_excess'], $r['bolton']['anterior']['excess'], $t);

        $this->assertEqualsWithDelta($e['lower_required'], $r['space_analysis']['lower']['required'], $t);
        $this->assertEqualsWithDelta($e['lower_available'], $r['space_analysis']['lower']['available'], $t);
        $this->assertEqualsWithDelta($e['lower_discrepancy'], $r['space_analysis']['lower']['discrepancy'], $t);
        $this->assertSame($r['space_analysis']['lower'], $r['carey']);

        $this->assertEqualsWithDelta($e['upper_intercanine'], $r['arch']['upper']['intercanine_width'], $t);
        $this->assertEqualsWithDelta($e['upper_arch_depth'], $r['arch']['upper']['arch_depth'], $t);

        $this->assertEqualsWithDelta($e['pont_predicted_premolar'], $r['pont']['predicted_premolar_width'], $t);
        $this->assertEqualsWithDelta($e['pont_predicted_molar'], $r['pont']['predicted_molar_width'], $t);
        $this->assertEqualsWithDelta($e['pont_premolar_difference'], $r['pont']['premolar_difference'], $t);
        $this->assertEqualsWithDelta($e['pont_molar_difference'], $r['pont']['molar_difference'], $t);

        $this->assertEqualsWithDelta($e['tj_upper'], $r['tanaka_johnston']['predicted_upper'], $t);
        $this->assertEqualsWithDelta($e['tj_lower'], $r['tanaka_johnston']['predicted_lower'], $t);

        $this->assertEqualsWithDelta($e['spee_right'], $r['curve_of_spee']['right'], $t);
        $this->assertEqualsWithDelta($e['spee_left'], $r['curve_of_spee']['left'], $t);
        $this->assertEqualsWithDelta($e['spee_mean'], $r['curve_of_spee']['mean'], $t);
    }

    public function test_point_widths_stay_in_model_units_until_units_are_confirmed(): void
    {
        $r = CastAnalysisCalculator::analyze(['teeth' => ['11' => ['mesial' => [0, 0, 0], 'distal' => [6, 8, 0]]]]);
        $this->assertSame('model units', $r['width_unit']);
        $this->assertFalse($r['calibrated']);
        $this->assertSame(10.0, $r['tooth_widths'][11]);

        $scaled = CastAnalysisCalculator::analyze(['scale_to_mm' => 0.5, 'teeth' => ['11' => ['mesial' => [0, 0, 0], 'distal' => [6, 8, 0]]]]);
        $this->assertSame('mm', $scaled['width_unit']);
        $this->assertSame(5.0, $scaled['tooth_widths'][11]);
    }

    public function test_caliper_mm_and_unconfirmed_model_units_are_never_summed(): void
    {
        $teeth = $this->fixture['input']['teeth'];
        $teeth['11'] = ['mesial' => [0, 0, 0], 'distal' => [9, 0, 0]];
        $r = CastAnalysisCalculator::analyze(['teeth' => $teeth]);

        $this->assertTrue($r['mixed_units']);
        $this->assertNull($r['bolton']['overall']['ratio']);
        $this->assertNull($r['pont']['predicted_premolar_width']);
        $this->assertNull($r['tanaka_johnston']['predicted_upper']);
    }

    public function test_missing_teeth_are_reported_instead_of_guessed(): void
    {
        $teeth = $this->fixture['input']['teeth'];
        unset($teeth['26']);
        $r = CastAnalysisCalculator::analyze(['teeth' => $teeth]);

        $this->assertNull($r['bolton']['overall']['ratio']);
        $this->assertSame([26], $r['bolton']['overall']['missing_teeth']);
        $this->assertNotNull($r['bolton']['anterior']['ratio']);
    }

    public function test_moyers_needs_a_configured_table_and_never_extrapolates(): void
    {
        $teeth = $this->fixture['input']['teeth'];
        $none = CastAnalysisCalculator::analyze(['teeth' => $teeth]);
        $this->assertFalse($none['moyers']['table_configured']);
        $this->assertNull($none['moyers']['predicted_lower']);

        // Lower incisors sum to 23.0 in the fixture.
        $table = ['level' => 75, 'source' => 'test table', 'rows' => [[22.0, 22.0, 21.0], [24.0, 23.0, 22.0]]];
        $r = CastAnalysisCalculator::analyze(['teeth' => $teeth], $table);
        $this->assertEqualsWithDelta(22.5, $r['moyers']['predicted_upper'], 1e-9);
        $this->assertEqualsWithDelta(21.5, $r['moyers']['predicted_lower'], 1e-9);

        $outside = CastAnalysisCalculator::analyze(['teeth' => $teeth], ['rows' => [[25.0, 1, 1], [26.0, 2, 2]]]);
        $this->assertNull($outside['moyers']['predicted_upper']);
    }
}
