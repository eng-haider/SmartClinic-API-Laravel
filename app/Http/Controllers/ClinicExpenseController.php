<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClinicExpenseRequest;
use App\Http\Resources\ClinicExpenseResource;
use App\Repositories\ClinicExpenseRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClinicExpenseController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct(private ClinicExpenseRepository $repository)
    {
        $this->middleware('permission:view-clinic-expenses')->only(['index', 'show', 'statistics']);
        $this->middleware('permission:create-expense')->only(['store']);
        $this->middleware('permission:edit-expense')->only(['update', 'markAsPaid', 'markAsUnpaid']);
        $this->middleware('permission:delete-expense')->only(['destroy']);
    }

    /**
     * Display a listing of expenses.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'filter',
            'sort',
            'include',
        ]);

        $perPage = $request->input('per_page', 15);
        
        // Multi-tenancy: No need for clinic_id filter, database is already isolated by tenant
        $expenses = $this->repository->getAllWithFilters($filters, $perPage, null);
        
        // Calculate summary statistics for the filtered results
        $summary = $this->repository->getFilteredSummary($filters, null);

        return response()->json([
            'success' => true,
            'message' => 'Expenses retrieved successfully',
            'data' => ClinicExpenseResource::collection($expenses),
            'summary' => $summary,
            'pagination' => [
                'total' => $expenses->total(),
                'per_page' => $expenses->perPage(),
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'from' => $expenses->firstItem(),
                'to' => $expenses->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created expense.
     */
    public function store(ClinicExpenseRequest $request): JsonResponse
    {
        try {
            $expense = $this->repository->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Expense created successfully',
                'data' => new ClinicExpenseResource($expense->load(['category', 'doctor', 'creator'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified expense.
     */
    public function show(int $id): JsonResponse
    {
        // Multi-tenancy: No need for clinic_id filter, database is already isolated by tenant
        $expense = $this->repository->getById($id, null);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Expense retrieved successfully',
            'data' => new ClinicExpenseResource($expense),
        ]);
    }

    /**
     * Update the specified expense.
     */
    public function update(ClinicExpenseRequest $request, int $id): JsonResponse
    {
        try {
            $expense = $this->repository->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Expense updated successfully',
                'data' => new ClinicExpenseResource($expense),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified expense.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->repository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Expense deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Mark expense as paid.
     */
    public function markAsPaid(int $id): JsonResponse
    {
        try {
            $expense = $this->repository->markAsPaid($id);

            return response()->json([
                'success' => true,
                'message' => 'Expense marked as paid',
                'data' => new ClinicExpenseResource($expense),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Mark expense as unpaid.
     */
    public function markAsUnpaid(int $id): JsonResponse
    {
        try {
            $expense = $this->repository->markAsUnpaid($id);

            return response()->json([
                'success' => true,
                'message' => 'Expense marked as unpaid',
                'data' => new ClinicExpenseResource($expense),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get expense statistics for a clinic.
     */
    public function statistics(Request $request): JsonResponse
    {
        // Multi-tenancy: No need for clinic_id filter, database is already isolated by tenant
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $statistics = $this->repository->getStatistics(null, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Expense statistics retrieved successfully',
            'data' => $statistics,
        ]);
    }

    /**
     * Get unpaid expenses for a clinic.
     */
    public function unpaid(Request $request): JsonResponse
    {
        // Multi-tenancy: No need for clinic_id filter, database is already isolated by tenant
        $expenses = $this->repository->getUnpaid();

        return response()->json([
            'success' => true,
            'message' => 'Unpaid expenses retrieved successfully',
            'data' => ClinicExpenseResource::collection($expenses),
        ]);
    }

    /**
     * Get expenses by date range.
     */
    public function byDateRange(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Multi-tenancy: No need for clinic_id filter, database is already isolated by tenant
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $expenses = $this->repository->getByDateRange($startDate, $endDate, null);

        return response()->json([
            'success' => true,
            'message' => 'Expenses retrieved successfully',
            'data' => ClinicExpenseResource::collection($expenses),
        ]);
    }

    // Note: getClinicIdByRole() method removed - no longer needed with multi-tenancy
    // Database isolation is handled automatically by the tenancy middleware
}
