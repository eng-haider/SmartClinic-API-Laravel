<?php

namespace App\Repositories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Filters\Filter;
use Spatie\QueryBuilder\Sorts\Sort;

class PatientRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return Patient::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(Patient::class)
            ->allowedFilters([
            
                  'name',
                'email',
                'phone',
                'phone2',
                'gender',
                'blood_type',
                'city',
                'state',
                'country',
                'is_active',
                AllowedFilter::scope('has_unpaid_cases', 'hasUnpaidCases'),
                AllowedFilter::scope('all_cases_paid', 'allCasesPaid'),
            ])
            ->allowedSorts([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'date_of_birth',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
            'cases',
            ]);
    }

    /**
     * Get all patients with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryBuilder();

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('phone2', 'like', "%{$search}%")
                    ->orWhere('identifier', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get patient by ID
     */
    public function getById(int $id): ?Patient
    {
        $query = $this->query();
        
        return $query->find($id);
    }

    /**
     * Create a new patient
     */
    public function create(array $data): Patient
    {
        return $this->query()->create($data);
    }

    /**
     * Update patient
     */
    public function update(int $id, array $data): Patient
    {
        $patient = $this->getById($id);

        if (!$patient) {
            throw new \Exception("Patient with ID {$id} not found");
        }

        $patient->update($data);

        return $patient->fresh();
    }

    /**
     * Delete patient
     */
    public function delete(int $id): bool
    {
        $patient = $this->getById($id);

        if (!$patient) {
            throw new \Exception("Patient with ID {$id} not found");
        }

        return $patient->delete();
    }

    /**
     * Get patient by phone
     */
    public function getByPhone(string $phone): ?Patient
    {
        $query = $this->query()->where(function ($q) use ($phone) {
            $q->where('phone', $phone)
              ->orWhere('phone2', $phone);
        });

        return $query->first();
    }

    /**
     * Find the patient behind a phone number, however it happens to be written.
     *
     * Numbers reach us free-form - "07701234567", "+964 770 123 4567",
     * "0770-123-4567" - so an exact string match reads the same person as a new
     * one. The indexed exact match is tried first, then the significant digits
     * are compared so the different shapes resolve to the same patient.
     */
    public function findByPhoneNumber(?string $phone): ?Patient
    {
        $key = $this->phoneKey($phone);

        if ($key === null) {
            return null;
        }

        if ($patient = $this->getByPhone($phone)) {
            return $patient;
        }

        $matchId = null;

        $this->query()
            ->select(['id', 'phone', 'phone2'])
            ->where(fn ($q) => $q->whereNotNull('phone')->orWhereNotNull('phone2'))
            ->orderBy('id')
            ->chunk(500, function ($patients) use ($key, &$matchId) {
                foreach ($patients as $patient) {
                    if ($this->phoneKey($patient->phone) === $key
                        || $this->phoneKey($patient->phone2) === $key) {
                        $matchId = $patient->id;

                        return false;
                    }
                }
            });

        return $matchId ? $this->query()->find($matchId) : null;
    }

    /**
     * Reduce a phone number to the digits that identify its owner.
     *
     * The last 9 digits stay unique within a clinic while dropping the local
     * trunk prefix ("0") and the country code ("+964", "00964"). Shorter
     * numbers - landlines, internal extensions - keep all of their digits, so
     * they only ever match another number written the same way. Returns null
     * when there is nothing to compare.
     */
    private function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }

    /**
     * Get patient by email
     */
    public function getByEmail(string $email): ?Patient
    {
        $query = $this->query()->where('email', $email);
        
        return $query->first();
    }

    /**
     * Check if patient exists by phone
     */
    public function existsByPhone(string $phone, ?int $exceptId = null): bool
    {
        $query = $this->query()->where(function ($q) use ($phone) {
            $q->where('phone', $phone)
              ->orWhere('phone2', $phone);
        });

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * Check if patient exists by email
     */
    public function existsByEmail(string $email, ?int $exceptId = null): bool
    {
        $query = $this->query()->where('email', $email);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }
}
