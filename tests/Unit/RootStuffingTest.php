<?php

namespace Tests\Unit;

use App\Modules\Dental\RootStuffing;
use PHPUnit\Framework\TestCase;

class RootStuffingTest extends TestCase
{
    public function test_it_keeps_the_old_system_shape(): void
    {
        $json = RootStuffing::encode([
            'access_opening' => [['MB', '21', 'MB cusp', 'K-file 15']],
            'oburation' => [['MB', '30', 'AH Plus', '30/.04']],
        ]);

        $this->assertSame(
            '{"access_opening":[["MB","21","MB cusp","K-file 15"]],"oburation":[["MB","30","AH Plus","30/.04"]]}',
            $json
        );
    }

    public function test_it_trims_pads_and_drops_empty_rows(): void
    {
        $record = json_decode(RootStuffing::encode([
            'access_opening' => [[' D ', '20'], [null, '', ' ', null], ['P', null, null, '25']],
            'oburation' => null,
        ]), true);

        $this->assertSame([['D', '20', '', ''], ['P', '', '', '25']], $record['access_opening']);
        $this->assertSame([], $record['oburation']);
    }

    public function test_nothing_filled_clears_the_record(): void
    {
        $this->assertNull(RootStuffing::encode([]));
        $this->assertNull(RootStuffing::encode([
            'access_opening' => [['', '', '', '']],
            'oburation' => [[null, null, null, null]],
            'note' => '  ',
        ]));
    }

    public function test_it_keeps_free_text_notes_and_arabic(): void
    {
        $record = json_decode(RootStuffing::encode(['note' => ' حشوة قديمة ']), true);

        $this->assertSame('حشوة قديمة', $record['note']);
        $this->assertStringContainsString('حشوة', RootStuffing::encode(['note' => 'حشوة']));
    }
}
