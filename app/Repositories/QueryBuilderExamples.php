<?php

namespace App\Repositories;

use App\Models\Patient;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Example: How to use Spatie Query Builder
 * 
 * This file demonstrates all the capabilities of the laravel-query-builder package
 * used in the PatientRepository
 */

class QueryBuilderExamples
{
    /**
     * Example 1: Basic Setup with Filters and Sorts
     * 
     * ```php
     * $builder = QueryBuilder::for(Patient::class)
     *     ->allowedFilters(['gender', 'blood_type', 'city', 'is_active'])
     *     ->allowedSorts(['first_name', 'created_at'])
     *     ->paginate(15);
     * ```
     * 
     * Usage: GET /patients?filter[gender]=male&sort=-created_at&per_page=15
     */
    public static function basicSetup()
    {
        return QueryBuilder::for(Patient::class)
            ->allowedFilters(['gender', 'blood_type', 'city', 'is_active'])
            ->allowedSorts(['first_name', 'created_at'])
            ->paginate(15);
    }

    /**
     * Example 2: Filter by Gender
     * 
     * Automatically converts: filter[gender]=male
     * Into: WHERE gender = 'male'
     * 
     * Usage: GET /patients?filter[gender]=male
     */
    public static function filterByGender($gender)
    {
        return QueryBuilder::for(Patient::class)
            ->allowedFilters('gender')
            ->where('gender', $gender)
            ->get();
    }

    /**
     * Example 3: Filter by Multiple Fields
     * 
     * Usage: GET /patients?filter[gender]=male&filter[blood_type]=O+&filter[city]=Cairo
     * Result: WHERE gender='male' AND blood_type='O+' AND city='Cairo'
     */
    public static function multipleFilters()
    {
        return QueryBuilder::for(Patient::class)
            ->allowedFilters(['gender', 'blood_type', 'city'])
            ->paginate(15);
    }

    /**
     * Example 4: Sorting Examples
     * 
     * Usage:
     * - GET /patients?sort=first_name              (ascending)
     * - GET /patients?sort=-first_name             (descending)
     * - GET /patients?sort=-created_at,first_name  (multiple)
     */
    public static function sortingExamples()
    {
        return QueryBuilder::for(Patient::class)
            ->allowedSorts(['id', 'first_name', 'last_name', 'created_at', 'updated_at'])
            ->paginate(15);
    }

    /**
     * Example 5: Combining Filters and Sorts
     * 
     * Usage: GET /patients?filter[gender]=male&sort=-created_at&per_page=20
     * Result: Males sorted by newest first, 20 per page
     */
    public static function combineFilterSort()
    {
        return QueryBuilder::for(Patient::class)
            ->allowedFilters(['gender', 'blood_type', 'city', 'is_active'])
            ->allowedSorts(['first_name', 'created_at'])
            ->paginate(20);
    }

    /**
     * Example 6: Custom Search (not directly handled by QueryBuilder)
     * 
     * Manually add custom search logic while keeping QueryBuilder filters
     * 
     * Usage: GET /patients?search=john&filter[gender]=male
     */
    public static function customSearchWithFilters($search)
    {
        $builder = QueryBuilder::for(Patient::class)
            ->allowedFilters(['gender', 'blood_type'])
            ->allowedSorts(['first_name', 'created_at']);

        // Custom search logic
        if (!empty($search)) {
            $builder->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $builder->paginate(15);
    }

    /**
     * Example 7: Only Get Specific Columns
     * 
     * Usage: GET /patients?filter[gender]=male
     * Selects only needed columns for better performance
     */
    public static function selectSpecificColumns()
    {
        return QueryBuilder::for(Patient::class)
            ->select(['id', 'first_name', 'last_name', 'email', 'phone'])
            ->allowedFilters(['gender', 'blood_type'])
            ->allowedSorts(['first_name', 'created_at'])
            ->paginate(15);
    }

    /**
     * Example 8: Filter by Active Status
     * 
     * Usage: GET /patients?filter[is_active]=1
     * Result: WHERE is_active = 1
     */
    public static function activePatients()
    {
        return QueryBuilder::for(Patient::class)
            ->allowedFilters('is_active')
            ->where('is_active', true)
            ->paginate(15);
    }

    /**
     * Example 9: Complex Query with All Features
     * 
     * Usage: GET /patients?search=ahmed&filter[gender]=male&filter[city]=Cairo&sort=-created_at&per_page=20&page=2
     * 
     * Result:
     * - Search: first_name, last_name, email, phone LIKE 'ahmed'
     * - Filter: gender='male' AND city='Cairo'
     * - Sort: created_at DESC
     * - Pagination: 20 per page, page 2
     */
    public static function complexQuery($search)
    {
        $builder = QueryBuilder::for(Patient::class)
            ->allowedFilters([
                'gender',
                'blood_type',
                'city',
                'state',
                'country',
                'is_active',
                'email',
                'phone',
                'first_name',
                'last_name',
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
            ]);

        // Add custom search
        if (!empty($search)) {
            $builder->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $builder->paginate(20);
    }

    /**
     * Example 10: Pagination Details
     * 
     * Response includes:
     * - total: Total number of records
     * - per_page: Records per page
     * - current_page: Current page number
     * - last_page: Last page number
     * - from: First record number on page
     * - to: Last record number on page
     * - data: Array of records
     */
    public static function paginationResponse()
    {
        $patients = QueryBuilder::for(Patient::class)
            ->allowedFilters('gender')
            ->allowedSorts('first_name')
            ->paginate(15);

        return [
            'total' => $patients->total(),           // 150
            'per_page' => $patients->perPage(),      // 15
            'current_page' => $patients->currentPage(), // 1
            'last_page' => $patients->lastPage(),    // 10
            'from' => $patients->firstItem(),        // 1
            'to' => $patients->lastItem(),           // 15
            'data' => $patients->items(),            // Array of 15 patients
        ];
    }
}

/**
 * ACTUAL USAGE IN PatientRepository
 * 
 * The actual implementation is in app/Repositories/PatientRepository.php
 * 
 * Key method: getAllWithFilters()
 * 
 * ```php
 * public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
 * {
 *     $builder = $this->queryBuilder();
 *     
 *     // Custom search (not handled by QueryBuilder)
 *     if (!empty($filters['search'])) {
 *         $search = $filters['search'];
 *         $builder->where(function ($query) use ($search) {
 *             $query->where('first_name', 'like', "%{$search}%")
 *                 ->orWhere('last_name', 'like', "%{$search}%")
 *                 ->orWhere('email', 'like', "%{$search}%")
 *                 ->orWhere('phone', 'like', "%{$search}%");
 *         });
 *     }
 *     
 *     // QueryBuilder automatically handles:
 *     // - filter[field]=value
 *     // - sort=field or sort=-field
 *     // - page and per_page
 *     
 *     return $builder->paginate($perPage);
 * }
 * ```
 */

/**
 * QUERY BUILDER FEATURES SUMMARY
 * 
 * ✅ FILTERS - Automatically convert filter[] parameters to WHERE clauses
 * ✅ SORTS - Automatically handle sort parameters (- for descending)
 * ✅ INCLUDES - Include related models (prepare if needed)
 * ✅ APPENDS - Add custom attributes to output
 * ✅ PAGINATION - Built-in pagination support
 * ✅ ALLOWED - Only expose fields you want
 * 
 * BENEFITS
 * 
 * 1. Security - Only exposed fields can be filtered/sorted
 * 2. Cleanliness - No manual filter logic needed
 * 3. Consistency - Standard query format across API
 * 4. Performance - Can optimize with select() and indexes
 * 5. Flexibility - Mix QueryBuilder with custom logic
 */
