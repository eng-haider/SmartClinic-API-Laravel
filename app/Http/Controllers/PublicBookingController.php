<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequestRequest;
use App\Models\ClinicSetting;
use App\Repositories\ClinicSettingRepository;
use App\Http\Resources\BookingRequestResource;
use App\Repositories\BookingRequestRepository;
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
        private ClinicSettingRepository $clinicSettings
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
        $keys = ['clinic_name', 'logo', 'phone', 'email', 'address', 'working_hours'];
        $settings = $this->clinicSettings->getByKeys($keys);

        $value = fn (string $key) => $settings->get($key)?->getValue();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $value('clinic_name') ?: null,
                'logo' => ClinicSetting::fileUrl($settings->get('logo')?->setting_value),
                'phone' => $value('phone') ?: null,
                'email' => $value('email') ?: null,
                'address' => $value('address') ?: null,
                'working_hours' => $value('working_hours') ?: null,
            ],
        ]);
    }
}
