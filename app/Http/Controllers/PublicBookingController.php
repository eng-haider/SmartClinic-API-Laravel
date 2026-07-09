<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequestRequest;
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
    public function __construct(private BookingRequestRepository $bookingRequests)
    {
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
}
