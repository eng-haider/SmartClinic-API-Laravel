<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicBookingController;
use App\Http\Requests\StoreBookingRequestRequest;
use App\Models\BookingRequest;
use App\Models\ClinicSetting;
use App\Repositories\BookingRequestRepository;
use App\Repositories\ClinicSettingRepository;
use App\Services\BookingSlotService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The public booking page lists the next days that still have free times, each
 * with its times, from the same BookingSlotService as the WhatsApp bot.
 */
class PublicBookingSlotsTest extends TestCase
{
    /** booking_time_mode setting value (null: never saved) */
    private ?string $timeMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = $this->createMock(ClinicSettingRepository::class);
        $settings->method('getByKey')->willReturnCallback(function (string $key) {
            if ($key !== 'booking_time_mode' || $this->timeMode === null) {
                return null;
            }
            $setting = $this->createMock(ClinicSetting::class);
            $setting->method('getValue')->willReturn($this->timeMode);

            return $setting;
        });
        $this->app->instance(ClinicSettingRepository::class, $settings);
    }

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
        $this->assertSame('time', $data['data']['time_mode']);
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

    public function test_reports_period_mode_from_the_setting(): void
    {
        $this->timeMode = 'period';
        $slots = $this->createMock(BookingSlotService::class);
        $slots->method('freeTimes')->willReturn([]);
        $this->app->instance(BookingSlotService::class, $slots);

        $data = $this->app->make(PublicBookingController::class)->slots()->getData(true);

        $this->assertSame('period', $data['data']['time_mode']);
    }

    public function test_a_chosen_period_is_written_at_the_top_of_the_note(): void
    {
        Carbon::setTestNow('2026-11-22 10:00:00');
        $saved = null;
        $repo = $this->createMock(BookingRequestRepository::class);
        $repo->expects($this->once())->method('create')->willReturnCallback(function (array $data) use (&$saved) {
            $saved = $data;

            return new BookingRequest($data);
        });
        $this->app->instance(BookingRequestRepository::class, $repo);

        $request = StoreBookingRequestRequest::create('/', 'POST', [
            'name' => 'زينب', 'phone' => '07701112222', 'preferred_date' => '2026-11-23',
            'preferred_period' => 'evening', 'note' => 'أول زيارة',
        ]);
        $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        $this->app->make(PublicBookingController::class)->store($request);

        $this->assertSame("الفترة المفضلة: مساءً\nأول زيارة", $saved['note']);
        $this->assertArrayNotHasKey('preferred_period', $saved);
        $this->assertNull($saved['preferred_time'] ?? null);
    }

    public function test_an_unknown_period_is_rejected(): void
    {
        $validator = validator(['preferred_period' => 'night'], (new StoreBookingRequestRequest())->rules());

        $this->assertTrue($validator->errors()->has('preferred_period') || $validator->fails());
        $this->assertArrayHasKey('preferred_period', $validator->errors()->toArray());
    }
}
