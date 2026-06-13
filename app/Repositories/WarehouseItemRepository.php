<?php

namespace App\Repositories;

use App\Models\WarehouseItem;
use App\Models\WarehouseTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WarehouseItemRepository
{
    /**
     * Get the base query builder instance.
     */
    protected function query(): Builder
    {
        return WarehouseItem::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters, sorts and includes.
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(WarehouseItem::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('clinic_expense_category_id'),
                AllowedFilter::scope('low_stock', 'lowStock'),
            ])
            ->allowedSorts([
                'id',
                'name',
                'quantity',
                'min_quantity',
                'cost_price',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'category',
                'transactions',
                'creator',
                'updator',
                'caseCategories',
            ])
            ->defaultSort('name');
    }

    /**
     * Get all items with filters and pagination.
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->queryBuilder()->paginate($perPage)->appends(request()->query());
    }

    /**
     * Get an item by ID — respects ?include= query parameter.
     */
    public function getById(int $id): ?WarehouseItem
    {
        return $this->queryBuilder()
            ->with(['category'])
            ->find($id);
    }

    /**
     * Create a new warehouse item.
     */
    public function create(array $data): WarehouseItem
    {
        return $this->query()->create($data);
    }

    /**
     * Update a warehouse item. Stock quantity is not changed here — use the
     * WarehouseService (restock/adjust) so the ledger stays consistent.
     */
    public function update(int $id, array $data): WarehouseItem
    {
        $item = $this->query()->findOrFail($id);

        // Quantity is mutated only through stock movements, never a plain update.
        unset($data['quantity']);

        $item->update($data);

        return $item->fresh(['category']);
    }

    /**
     * Soft delete a warehouse item.
     */
    public function delete(int $id): bool
    {
        return (bool) $this->query()->findOrFail($id)->delete();
    }

    /**
     * Items at or below their low-stock threshold.
     */
    public function getLowStock(): Collection
    {
        return $this->query()
            ->lowStock()
            ->with(['category', 'caseCategories'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Stock movement history for an item.
     */
    public function getTransactions(int $id, int $perPage = 20): LengthAwarePaginator
    {
        return WarehouseTransaction::query()
            ->where('warehouse_item_id', $id)
            ->with(['doctor', 'source'])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }
}
