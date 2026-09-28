<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseItemRequest;
use App\Http\Requests\WarehouseRestockRequest;
use App\Http\Resources\ClinicExpenseResource;
use App\Http\Resources\WarehouseItemResource;
use App\Http\Resources\WarehouseTransactionResource;
use App\Repositories\WarehouseItemRepository;
use App\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseItemController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private WarehouseItemRepository $repository,
        private WarehouseService $warehouse
    ) {
        // $this->middleware('permission:view-warehouse')->only(['index', 'show', 'lowStock', 'transactions']);
        // $this->middleware('permission:create-warehouse')->only(['store']);
        // $this->middleware('permission:edit-warehouse')->only(['update', 'adjust']);
        // $this->middleware('permission:restock-warehouse')->only(['restock']);
        // $this->middleware('permission:delete-warehouse')->only(['destroy']);
    }

    /**
     * Display a listing of warehouse items.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $items = $this->repository->getAllWithFilters($request->only(['filter', 'sort', 'include']), $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse items retrieved successfully',
            'data' => WarehouseItemResource::collection($items),
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created warehouse item.
     */
    public function store(WarehouseItemRequest $request): JsonResponse
    {
        try {
            $item = $this->repository->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Warehouse item created successfully',
                'data' => new WarehouseItemResource($item->load('category')),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified warehouse item.
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->repository->getById($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse item not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Warehouse item retrieved successfully',
            'data' => new WarehouseItemResource($item),
        ]);
    }

    /**
     * Update the specified warehouse item (metadata only; stock is unchanged).
     */
    public function update(WarehouseItemRequest $request, int $id): JsonResponse
    {
        try {
            $item = $this->repository->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Warehouse item updated successfully',
                'data' => new WarehouseItemResource($item),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified warehouse item.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->repository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse item deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Restock an item: increases stock and records a clinic expense (cash out).
     */
    public function restock(WarehouseRestockRequest $request, int $id): JsonResponse
    {
        $item = $this->repository->getById($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse item not found',
            ], 404);
        }

        try {
            $overrides = array_filter([
                'clinic_expense_category_id' => $request->input('clinic_expense_category_id'),
                'is_paid' => $request->has('is_paid') ? $request->boolean('is_paid') : null,
                'date' => $request->input('date'),
            ], fn ($value) => !is_null($value));

            $expense = $this->warehouse->restock(
                $item,
                (int) $request->input('quantity'),
                $request->filled('unit_cost') ? (float) $request->input('unit_cost') : null,
                $overrides
            );

            return response()->json([
                'success' => true,
                'message' => 'Item restocked successfully',
                'data' => [
                    'item' => new WarehouseItemResource($item->fresh('category')),
                    'expense' => new ClinicExpenseResource($expense),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Manually adjust stock (stock-take correction, breakage, etc.).
     */
    public function adjust(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'delta' => 'required|integer|not_in:0',
            'reason' => 'nullable|string|max:255',
        ]);

        $item = $this->repository->getById($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse item not found',
            ], 404);
        }

        try {
            $this->warehouse->adjust($item, (int) $request->input('delta'), $request->input('reason'));

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => new WarehouseItemResource($item->fresh('category')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List items at or below their low-stock threshold.
     */
    public function lowStock(): JsonResponse
    {
        $items = $this->repository->getLowStock();

        return response()->json([
            'success' => true,
            'message' => 'Low stock items retrieved successfully',
            'data' => WarehouseItemResource::collection($items),
        ]);
    }

    /**
     * Stock movement history for an item.
     */
    public function transactions(Request $request, int $id): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $transactions = $this->repository->getTransactions($id, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse transactions retrieved successfully',
            'data' => WarehouseTransactionResource::collection($transactions),
            'pagination' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
        ]);
    }
}
