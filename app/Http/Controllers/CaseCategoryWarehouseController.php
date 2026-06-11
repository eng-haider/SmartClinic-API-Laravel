<?php

namespace App\Http\Controllers;

use App\Http\Resources\WarehouseItemResource;
use App\Models\CaseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manage the default warehouse "kit" (bill of materials) for a case category.
 * Cases created under a category consume this kit automatically unless an
 * explicit warehouse_items list is provided.
 */
class CaseCategoryWarehouseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view-warehouse')->only(['index']);
        $this->middleware('permission:edit-warehouse')->only(['sync']);
    }

    /**
     * List the default kit for a case category.
     */
    public function index(int $id): JsonResponse
    {
        $category = CaseCategory::with('warehouseItems')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Case category not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Default kit retrieved successfully',
            'data' => $category->warehouseItems->map(fn ($item) => [
                'item' => new WarehouseItemResource($item),
                'quantity' => (int) $item->pivot->quantity,
            ])->values(),
        ]);
    }

    /**
     * Replace the default kit for a case category.
     */
    public function sync(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'present|array',
            'items.*.warehouse_item_id' => 'required|integer|exists:warehouse_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $category = CaseCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Case category not found',
            ], 404);
        }

        // Build the [warehouse_item_id => ['quantity' => n]] map for sync().
        $syncData = collect($validated['items'])
            ->mapWithKeys(fn ($row) => [(int) $row['warehouse_item_id'] => ['quantity' => (int) $row['quantity']]])
            ->all();

        $category->warehouseItems()->sync($syncData);

        return response()->json([
            'success' => true,
            'message' => 'Default kit updated successfully',
            'data' => $category->load('warehouseItems')->warehouseItems->map(fn ($item) => [
                'item' => new WarehouseItemResource($item),
                'quantity' => (int) $item->pivot->quantity,
            ])->values(),
        ]);
    }
}
