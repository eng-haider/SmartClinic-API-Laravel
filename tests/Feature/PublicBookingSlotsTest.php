<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicBookingController;
use App\Services\BookingSlotService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The public booking page lists the next days that still have free times, each
 * with its times, from the same BookingSlotService as the WhatsApp bot.
 */
class PublicBookingSlotsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lists_only_days_with_free_times(): void
    {
        Carbon::setTestNow('2026-11-21 10:00:00'); // Saturday

        $slots = $this->createMock(BookingSlotService::class);
        $slots->method('duration')->willReturn(30);
        $slots->method('freeTimes')->willReturnCallback(fn ($day) => match ($day->toDateString()) {
            '2026-11-21' => ['16:00', '16:30'],
            '2026-11-23' => ['09:00'],
            default => [],
        });
        $this->app->instance(BookingSlotService::class, $slots);

        $data = $this->app->make(PublicBookingController::class)->slots()->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame(30, $data['data']['duration']);
        $this->assertSame([
            ['date' => '2026-11-21', 'times' => ['16:00', '16:30']],
            ['date' => '2026-11-23', 'times' => ['09:00']],
        ], $data['data']['days']);
    }

    public function test_stops_at_fourteen_days(): void
    {
        Carbon::setTestNow('2026-11-21 10:00:00');

        $slots = $this->createMock(BookingSlotService::class);
        $slots->method('duration')->willReturn(20);
        $slots->expects($this->exactly(14))->method('freeTimes')->willReturn(['10:00']);
        $this->app->instance(BookingSlotService::class, $slots);

        $days = $this->app->make(PublicBookingController::class)->slots()->getData(true)['data']['days'];

        $this->assertCount(14, $days);
        $this->assertSame('2026-12-04', end($days)['date']);
    }

    public function test_route_is_registered(): void
    {
        $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->uri() === 'api/tenant/public/booking-slots');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }
}
