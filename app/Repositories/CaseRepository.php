<?php

namespace App\Repositories;

use App\Models\CaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class CaseRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return CaseModel::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(CaseModel::query())
            ->allowedFilters([
                'patient_id',
                'doctor_id',
                'case_categores_id',
                'status_id',
                'is_paid',
                'notes',
                'tooth_num',
                AllowedFilter::exact('is_paid'),
                AllowedFilter::exact('status_id'),
                AllowedFilter::exact('patient_id'),
                AllowedFilter::exact('doctor_id'),
                AllowedFilter::exact('case_categores_id'),
                AllowedFilter::scope('paid'),
                AllowedFilter::scope('unpaid'),
                AllowedFilter::partial('notes'),
            ])
            ->allowedSorts([
                'id',
                'patient_id',
                'doctor_id',
                'status_id',
                'price',
                'is_paid',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'patient',
                'doctor',
                'category',
                'status',
                'notes',
                'bills',
            ])
            ->defaultSort('-created_at');
    }

    /**
     * Get all cases with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15, ?int $doctorId = null): LengthAwarePaginator
    {
        $query = $this->queryBuilder();
        
        // Filter by doctor if provided (for doctors to see only their own cases)
        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }
        
        return $query->paginate($perPage);
    }

    /**
     * Get case by ID
     */
    public function getById(int $id, ?int $doctorId = null): ?CaseModel
    {
        $query = $this->query()
            ->with(['patient', 'doctor', 'category', 'status']);
        
        // Filter by doctor if provided (for doctors to see only their own cases)
        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }
        
        return $query->find($id);
    }

    /**
     * Create a new case
     */
    public function create(array $data): CaseModel
    {
        return CaseModel::create($data);
    }

    /**
     * Update case
     */
    public function update(int $id, array $data): CaseModel
    {
        $case = $this->query()->findOrFail($id);
        $case->update($data);
        return $case->fresh(['patient', 'doctor', 'category', 'status']);
    }

    /**
     * Delete case (soft delete)
     */
    public function delete(int $id): bool
    {
        $case = $this->query()->findOrFail($id);
        return $case->delete();
    }

    /**
     * Restore soft deleted case
     */
    public function restore(int $id): bool
    {
        $case = CaseModel::withTrashed()->findOrFail($id);
        return $case->restore();
    }

    /**
     * Force delete case permanently
     */
    public function forceDelete(int $id): bool
    {
        $case = CaseModel::withTrashed()->findOrFail($id);
        return $case->forceDelete();
    }

    /**
     * Get cases by patient ID
     */
    public function getByPatientId(int $patientId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['doctor', 'category', 'status'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get cases by doctor ID
     */
    public function getByDoctorId(int $doctorId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient', 'category', 'status'])
            ->where('doctor_id', $doctorId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get cases by status ID
     */
    public function getByStatusId(int $statusId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient', 'doctor', 'category', 'status'])
            ->where('status_id', $statusId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get cases by category ID
     */
    public function getByCategoryId(int $categoryId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient', 'doctor', 'category', 'status'])
            ->where('case_categores_id', $categoryId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get cases by clinic ID
     * DEPRECATED: clinic_id not used in multi-tenant setup
     * Database is already isolated by tenant
     */
    public function getByClinicId( int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient', 'doctor', 'category', 'status'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get paid cases
     */
    public function getPaidCases(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient', 'doctor', 'category', 'status'])
            ->paid()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get unpaid cases
     */
    public function getUnpaidCases(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient', 'doctor', 'category', 'status'])
            ->unpaid()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Mark case as paid
     */
    public function markAsPaid(int $id): bool
    {
        $case = $this->query()->findOrFail($id);
        return $case->update(['is_paid' => true]);
    }

    /**
     * Mark case as unpaid
     */
    public function markAsUnpaid(int $id): bool
    {
        $case = $this->query()->findOrFail($id);
        return $case->update(['is_paid' => false]);
    }

    /**
     * Update case status
     */
    public function updateStatus(int $id, int $statusId): bool
    {
        $case = $this->query()->findOrFail($id);
        return $case->update(['status_id' => $statusId]);
    }

    /**
     * Get total revenue
     */
    public function getTotalRevenue(): int
    {
        return $this->query()
            ->where('is_paid', true)
            ->sum('price') ?? 0;
    }

    /**
     * Get total unpaid amount
     */
    public function getTotalUnpaidAmount(): int
    {
        return $this->query()
            ->where('is_paid', false)
            ->sum('price') ?? 0;
    }

    /**
     * Get cases with advanced filters using Query Builder
     */
    public function getWithAdvancedFilters(array $filters): QueryBuilder
    {
        $query = $this->queryBuilder();

        // Date range filter
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Price range filter
        if (isset($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        // Search in multiple fields
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                  ->orWhere('tooth_num', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($patientQuery) use ($search) {
                      $patientQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        return $query;
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(array $filters = []): array
    {
        $query = $this->query();

        // Apply date filters if provided
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Apply doctor filter if provided
        if (isset($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        return [
            'total_cases' => $query->count(),
            'paid_cases' => (clone $query)->where('is_paid', true)->count(),
            'unpaid_cases' => (clone $query)->where('is_paid', false)->count(),
            'total_revenue' => (clone $query)->where('is_paid', true)->sum('price') ?? 0,
            'total_outstanding' => (clone $query)->where('is_paid', false)->sum('price') ?? 0,
            'average_case_value' => (clone $query)->avg('price') ?? 0,
        ];
    }

    /**
     * Get cases grouped by status
     */
    public function getCasesByStatus(): array
    {
        return $this->query()
            ->selectRaw('status_id, COUNT(*) as count, SUM(price) as total_price')
            ->with('status:id,name_en,name_ar,color')
            ->groupBy('status_id')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                    'total_price' => $item->total_price,
                ];
            })
            ->toArray();
    }

    /**
     * Get cases grouped by category
     */
    public function getCasesByCategory(): array
    {
        return $this->query()
            ->selectRaw('case_categores_id, COUNT(*) as count, SUM(price) as total_price')
            ->with('category:id,name_en,name_ar')
            ->groupBy('case_categores_id')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category,
                    'count' => $item->count,
                    'total_price' => $item->total_price,
                ];
            })
            ->toArray();
    }

    /**
     * Get revenue by date range
     */
    public function getRevenueByDateRange(string $startDate, string $endDate): array
    {
        $query = $this->query()
            ->selectRaw('DATE(created_at) as date, SUM(price) as revenue, COUNT(*) as cases_count')
            ->where('is_paid', true)
            ->whereBetween('created_at', [$startDate, $endDate]);

        // clinic_id not needed in multi-tenant setup - database already isolated

        return $query->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Bulk update case status
     */
    public function bulkUpdateStatus(array $caseIds, int $statusId): int
    {
        return $this->query()
            ->whereIn('id', $caseIds)
            ->update(['status_id' => $statusId]);
    }

    /**
     * Bulk mark as paid
     */
    public function bulkMarkAsPaid(array $caseIds): int
    {
        return $this->query()
            ->whereIn('id', $caseIds)
            ->update(['is_paid' => true]);
    }

    /**
     * Bulk mark as unpaid
     */
    public function bulkMarkAsUnpaid(array $caseIds): int
    {
        return $this->query()
            ->whereIn('id', $caseIds)
            ->update(['is_paid' => false]);
    }
}
