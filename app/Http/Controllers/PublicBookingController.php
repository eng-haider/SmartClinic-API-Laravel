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
        $bookingRequest = $this->bookingRequests->create($request->validated());

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
                'days' => $days,
            ],
        ]);
    }
}
