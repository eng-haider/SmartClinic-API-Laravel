<?php

namespace App\Repositories;

use App\Models\BookingRequest;
use App\Models\Patient;
use App\Models\Reservation;
use App\Models\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingRequestRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return BookingRequest::query();
    }

    /**
     * Get all booking requests with optional status filter and pagination.
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['patient', 'reservation', 'reviewer'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('preferred_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('preferred_date', '<=', $filters['to_date']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a booking request by ID.
     */
    public function getById(int $id): ?BookingRequest
    {
        return $this->query()
            ->with(['patient', 'reservation', 'reviewer'])
            ->find($id);
    }

    /**
     * Create a new (public) booking request in the pending state.
     */
    public function create(array $data): BookingRequest
    {
        $data['status'] = BookingRequest::STATUS_PENDING;

        return $this->query()->create($data);
    }

    /**
     * Approve a booking request.
     *
     * Finds or creates a patient by phone, creates a real reservation, and
     * links both back to the request. Runs in a transaction so a partial
     * failure never leaves an approved request without a reservation.
     *
     * @param array $overrides Optional staff overrides applied to the reservation
     *                         (doctor_id, status_id, reservation_date,
     *                         reservation_time, notes, is_waiting).
     */
    public function approve(int $id, array $overrides = []): BookingRequest
    {
        $request = $this->query()->find($id);

        if (!$request) {
            throw new \Exception("Booking request with ID {$id} not found");
        }

        if (!$request->isPending()) {
            throw new \Exception("Booking request has already been {$request->status}");
        }

        return DB::transaction(function () use ($request, $overrides) {
            $patient = $this->findOrCreatePatientByPhone($request);

            $date = $overrides['reservation_date']
                ?? $request->preferred_date->format('Y-m-d');

            // A time is required by the schema; fall back to the patient's
            // preferred time, else midnight and mark the reservation as waiting.
            $time = $overrides['reservation_time']
                ?? ($request->preferred_time ?: null);

            $isWaiting = empty($time);
            $time = $time ?: '00:00:00';

            $reservation = Reservation::create([
                'patient_id' => $patient->id,
                'doctor_id' => $overrides['doctor_id'] ?? null,
                'status_id' => $overrides['status_id'] ?? $this->defaultStatusId(),
                'notes' => $overrides['notes'] ?? $request->note,
                'reservation_start_date' => $date,
                'reservation_end_date' => $date,
                'reservation_from_time' => $this->normalizeTime($time),
                'reservation_to_time' => null,
                'is_waiting' => $overrides['is_waiting'] ?? $isWaiting,
            ]);

            $request->update([
                'status' => BookingRequest::STATUS_APPROVED,
                'patient_id' => $patient->id,
                'reservation_id' => $reservation->id,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            return $request->fresh(['patient', 'reservation', 'reviewer']);
        });
    }

    /**
     * Reject a booking request.
     */
    public function reject(int $id, ?string $reason = null): BookingRequest
    {
        $request = $this->query()->find($id);

        if (!$request) {
            throw new \Exception("Booking request with ID {$id} not found");
        }

        if (!$request->isPending()) {
            throw new \Exception("Booking request has already been {$request->status}");
        }

        $request->update([
            'status' => BookingRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $request->fresh(['reviewer']);
    }

    /**
     * Delete a booking request.
     */
    public function delete(int $id): bool
    {
        $request = $this->query()->find($id);

        if (!$request) {
            throw new \Exception("Booking request with ID {$id} not found");
        }

        return (bool) $request->delete();
    }

    /**
     * Find an existing patient by phone, or create a new one from the request.
     */
    private function findOrCreatePatientByPhone(BookingRequest $request): Patient
    {
        $patient = Patient::where('phone', $request->phone)
            ->orWhere('phone2', $request->phone)
            ->first();

        if ($patient) {
            return $patient;
        }

        return Patient::create([
            'name' => $request->name,
            'phone' => $request->phone,
        ]);
    }

    /**
     * Resolve the default reservation status ("New"), falling back to the
     * lowest-id status. Mirrors the logic in ReservationRequest.
     */
    private function defaultStatusId(): ?int
    {
        $status = Status::where('name_en', 'New')
            ->orWhere('name_ar', 'جديد')
            ->first();

        if (!$status) {
            $status = Status::orderBy('id')->first();
        }

        return $status?->id;
    }

    /**
     * Normalize an HH:MM or HH:MM:SS string to HH:MM:SS for the time column.
     */
    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }
}
