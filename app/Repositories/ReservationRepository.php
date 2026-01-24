<?php

namespace App\Repositories;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class ReservationRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return Reservation::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(Reservation::class)
            ->allowedFilters([
                'patient_id',
                'doctor_id',
                'clinics_id',
                'status_id',
                'reservation_start_date',
                'reservation_end_date',
                'reservation_from_time',
                'reservation_to_time',
                'is_waiting',
                'from_date',
                'to_date',
                AllowedFilter::exact('patient_id'),
                AllowedFilter::exact('doctor_id'),
                AllowedFilter::exact('clinics_id'),
                AllowedFilter::exact('status_id'),
                AllowedFilter::exact('is_waiting'),
                AllowedFilter::scope('waiting'),
                AllowedFilter::callback('from_date', function ($query, $value) {
                    $query->where('reservation_start_date', '>=', $value);
                }),
                AllowedFilter::callback('to_date', function ($query, $value) {
                    $query->where('reservation_start_date', '<=', $value);
                }),
            ])
            ->allowedSorts([
                'id',
                'patient_id',
                'doctor_id',
                'clinics_id',
                'reservation_start_date',
                'reservation_end_date',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'patient',
                'doctor',
                'clinic',
                'status',
            ])
            ->defaultSort('-reservation_start_date');
    }

    /**
     * Get all reservations with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15, ?int $clinicId = null, ?int $doctorId = null): LengthAwarePaginator
    {
        $query = $this->queryBuilder();
        
        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinics_id', $clinicId);
        }
        
        // Filter by doctor if provided
        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }
        
        return $query->paginate($perPage);
    }

    /**
     * Get reservation by ID
     */
    public function getById(int $id, ?int $clinicId = null, ?int $doctorId = null): ?Reservation
    {
        $query = $this->query()
            ->with(['patient', 'doctor', 'clinic', 'status']);
        
        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinics_id', $clinicId);
        }
        
        // Filter by doctor if provided (for doctors to see only their own reservations)
        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }
        
        return $query->find($id);
    }

    /**
     * Create a new reservation
     */
    public function create(array $data): Reservation
    {
        return $this->query()->create($data);
    }

    /**
     * Update reservation
     */
    public function update(int $id, array $data): Reservation
    {
        $reservation = $this->getById($id);

        if (!$reservation) {
            throw new \Exception("Reservation with ID {$id} not found");
        }

        $reservation->update($data);

        return $reservation->fresh();
    }

    /**
     * Delete reservation
     */
    public function delete(int $id): bool
    {
        $reservation = $this->getById($id);

        if (!$reservation) {
            throw new \Exception("Reservation with ID {$id} not found");
        }

        return $reservation->delete();
    }
}
