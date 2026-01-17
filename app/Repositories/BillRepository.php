<?php

namespace App\Repositories;

use App\Models\Bill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class BillRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return Bill::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(Bill::class)
            ->allowedFilters([
                'patient_id',
                'doctor_id',
                'clinics_id',
                'is_paid',
                'use_credit',
                'billable_type',
                AllowedFilter::exact('price'),
                AllowedFilter::scope('paid'),
                AllowedFilter::scope('unpaid'),
                AllowedFilter::scope('by_patient', 'byPatient'),
                AllowedFilter::scope('by_doctor', 'byDoctor'),
                AllowedFilter::scope('by_clinic', 'byClinic'),
            ])
            ->allowedSorts([
                'id',
                'price',
                'is_paid',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'patient',
                'doctor',
                'clinic',
                'creator',
                'updator',
                'billable',
                'notes',
            ])
            ->defaultSort('-created_at');
    }

    /**
     * Get all bills with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15, ?int $clinicId = null): LengthAwarePaginator
    {
        $query = $this->queryBuilder();

        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinics_id', $clinicId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get bill by ID
     */
    public function getById(int $id, ?int $clinicId = null): ?Bill
    {
        $query = $this->query()->with(['patient', 'doctor', 'clinic', 'billable']);

        // Filter by clinic if provided
        if ($clinicId !== null) {
            $query->where('clinics_id', $clinicId);
        }

        return $query->find($id);
    }

    /**
     * Create a new bill
     */
    public function create(array $data): Bill
    {
        return $this->query()->create($data);
    }

    /**
     * Update bill
     */
    public function update(int $id, array $data, ?int $clinicId = null): Bill
    {
        $bill = $this->getById($id, $clinicId);

        if (!$bill) {
            throw new \Exception("Bill with ID {$id} not found");
        }

        $bill->update($data);

        return $bill->fresh(['patient', 'doctor', 'clinic', 'billable']);
    }

    /**
     * Delete bill
     */
    public function delete(int $id, ?int $clinicId = null): bool
    {
        $bill = $this->getById($id, $clinicId);

        if (!$bill) {
            throw new \Exception("Bill with ID {$id} not found");
        }

        return $bill->delete();
    }

    /**
     * Mark bill as paid
     */
    public function markAsPaid(int $id, ?int $clinicId = null): Bill
    {
        $bill = $this->getById($id, $clinicId);

        if (!$bill) {
            throw new \Exception("Bill with ID {$id} not found");
        }

        $bill->markAsPaid();

        return $bill->fresh(['patient', 'doctor', 'clinic', 'billable']);
    }

    /**
     * Mark bill as unpaid
     */
    public function markAsUnpaid(int $id, ?int $clinicId = null): Bill
    {
        $bill = $this->getById($id, $clinicId);

        if (!$bill) {
            throw new \Exception("Bill with ID {$id} not found");
        }

        $bill->markAsUnpaid();

        return $bill->fresh(['patient', 'doctor', 'clinic', 'billable']);
    }

    /**
     * Get bills by patient
     */
    public function getByPatient(int $patientId, int $perPage = 15, ?int $clinicId = null): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['patient', 'doctor', 'clinic', 'billable'])
            ->byPatient($patientId);

        if ($clinicId !== null) {
            $query->where('clinics_id', $clinicId);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get bills by doctor
     */
    public function getByDoctor(int $doctorId, int $perPage = 15, ?int $clinicId = null): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['patient', 'doctor', 'clinic', 'billable'])
            ->byDoctor($doctorId);

        if ($clinicId !== null) {
            $query->where('clinics_id', $clinicId);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get total revenue for a clinic
     */
    public function getTotalRevenue(?int $clinicId = null): int
    {
        $query = $this->query()->paid();

        if ($clinicId !== null) {
            $query->where('clinics_id', $clinicId);
        }

        return $query->sum('price');
    }

    /**
     * Get total outstanding for a clinic
     */
    public function getTotalOutstanding(?int $clinicId = null): int
    {
        $query = $this->query()->unpaid();

        if ($clinicId !== null) {
            $query->where('clinics_id', $clinicId);
        }

        return $query->sum('price');
    }

    /**
     * Get bill statistics
     */
    public function getStatistics(?int $clinicId = null): array
    {
        $query = $this->query();

        if ($clinicId !== null) {
            $query->where('clinics_id', $clinicId);
        }

        $totalBills = $query->count();
        $paidBills = (clone $query)->paid()->count();
        $unpaidBills = (clone $query)->unpaid()->count();
        $totalRevenue = (clone $query)->paid()->sum('price');
        $totalOutstanding = (clone $query)->unpaid()->sum('price');

        return [
            'total_bills' => $totalBills,
            'paid_bills' => $paidBills,
            'unpaid_bills' => $unpaidBills,
            'total_revenue' => $totalRevenue,
            'total_outstanding' => $totalOutstanding,
        ];
    }
}
