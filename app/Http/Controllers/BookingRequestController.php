<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveBookingRequestRequest;
use App\Http\Resources\BookingRequestResource;
use App\Repositories\BookingRequestRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-facing management of public booking requests (JWT protected).
 *
 * Lists incoming requests and lets staff approve (creates a patient +
 * reservation) or reject them.
 */
class BookingRequestController extends Controller
{
    public function __construct(private BookingRequestRepository $bookingRequests)
    {
    }

    /**
     * List booking requests. Defaults to pending; pass ?status=approved|rejected|all.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');

        $filters = [
            'status' => $status === 'all' ? null : $status,
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
        ];

        $perPage = (int) $request->input('per_page', 15);

        $requests = $this->bookingRequests->getAllWithFilters($filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Booking requests retrieved successfully',
            'data' => BookingRequestResource::collection($requests),
            'pagination' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'from' => $requests->firstItem(),
                'to' => $requests->lastItem(),
            ],
        ]);
    }

    /**
     * Show a single booking request.
     */
    public function show(int $id): JsonResponse
    {
        $bookingRequest = $this->bookingRequests->getById($id);

        if (!$bookingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Booking request not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking request retrieved successfully',
            'data' => new BookingRequestResource($bookingRequest),
        ]);
    }

    /**
     * Approve a booking request: create/link the patient and the reservation.
     *
     * Optional overrides let staff adjust the reservation before it is created.
     */
    public function approve(ApproveBookingRequestRequest $request, int $id): JsonResponse
    {
        $overrides = $request->validated();

        try {
            $bookingRequest = $this->bookingRequests->approve($id, array_filter($overrides, fn ($v) => $v !== null));

            return response()->json([
                'success' => true,
                'message' => 'Booking request approved and reservation created',
                'data' => new BookingRequestResource($bookingRequest),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject a booking request.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        try {
            $bookingRequest = $this->bookingRequests->reject($id, $data['rejection_reason'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Booking request rejected',
                'data' => new BookingRequestResource($bookingRequest),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a booking request.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->bookingRequests->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Booking request deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
