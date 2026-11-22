<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequestRequest;
use App\Repositories\ClinicSettingRepository;
use App\Http\Resources\BookingRequestResource;
use App\Repositories\BookingRequestRepository;
use App\Services\BookingSlotService;
use Illuminate\Http\JsonResponse;

/**
 * Public booking endpoint used by a clinic's website.
 *
 * No authentication required. The tenant (clinic) is resolved by the
 * InitializeTenancyByPatientToken middleware via ?clinic=ID or an
 * X-Tenant-ID / X-Clinic-ID header.
 */
class PublicBookingController extends Controller
{
    /** Parts of the day a patient can pick in "period" mode (booking_time_mode setting). */
    public const PERIODS = ['morning' => 'صباحاً', 'afternoon' => 'ظهراً وعصراً', 'evening' => 'مساءً'];

    public function __construct(
        private BookingRequestRepository $bookingRequests,
        private ClinicSettingRepository $clinicSettings,
        private BookingSlotService $slots
    ) {
    }

    /**
     * Submit a booking request from the public clinic website.
     */
    public function store(StoreBookingRequestRequest $request): JsonResponse
    {
        $data = $request->validated();

        // A chosen period (no exact time) is kept at the top of the note, where
        // staff read it when they review the request and pick the time.
        if ($period = $data['preferred_period'] ?? null) {
            $line = 'الفترة المفضلة: ' . self::PERIODS[$period];
            $data['note'] = trim($line . "\n" . ($data['note'] ?? ''));
        }
        unset($data['preferred_period']);

        $bookingRequest = $this->bookingRequests->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Your booking request has been received. The clinic will confirm it shortly.',
            'message_ar' => 'تم استلام طلب الحجز. ستقوم العيادة بتأكيده قريباً.',
            'data' => new BookingRequestResource($bookingRequest),
        ], 201);
    }

    /**
     * Public clinic identity for the booking page header (name, logo, contact).
     *
     * The shared booking link carries these as query parameters, but those are
     * frozen at the moment the link/QR was generated - this endpoint lets the
     * page show the clinic's current branding instead.
     */
    public function clinicInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->clinicSettings->publicIdentity(),
        ]);
    }

    /**
     * Free appointment times for the booking page: the next days that still have
     * a free time (up to 14, within 21 days), each with its free start times ("H:i").
     * Same source as the WhatsApp booking bot, so both offer the same times.
     */
    public function slots(): JsonResponse
    {
        $days = [];

        for ($offset = 0; $offset < 21 && count($days) < 14; $offset++) {
            $day = today()->addDays($offset);
            $times = $this->slots->freeTimes($day);

            if ($times !== []) {
                $days[] = ['date' => $day->toDateString(), 'times' => $times];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'duration' => $this->slots->duration(),
                // How the page asks for the time: an exact time ("time") or a part of the day ("period")
                'time_mode' => $this->clinicSettings->getByKey('booking_time_mode')?->getValue() === 'period' ? 'period' : 'time',
                'days' => $days,
            ],
        ]);
    }
}
