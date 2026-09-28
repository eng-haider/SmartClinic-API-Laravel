<?php

namespace App\Services;

use App\Models\CaseCategory;
use App\Models\CaseModel;
use App\Models\ClinicExpense;
use App\Models\Notification;
use App\Models\WarehouseItem;
use App\Models\WarehouseTransaction;
use App\Repositories\ClinicExpenseRepository;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WarehouseService
 *
 * Owns all stock-mutating logic so the running balance on warehouse_items,
 * the warehouse_transactions ledger, the per-case consumption pivot and the
 * linked clinic_expenses stay consistent. Every public mutation runs inside a
 * DB transaction.
 *
 * Accounting model (per product decision):
 *   - Purchase / restock  -> creates a ClinicExpense (cash out) + increases stock.
 *   - Consumption in case  -> decreases stock + rolls cost into cases.item_cost.
 *     (No second expense is created — purchases already captured the cash out.)
 */
class WarehouseService
{
    public function __construct(
        private ClinicExpenseRepository $clinicExpenses,
        private NotificationService $notifications
    ) {
    }

    /**
     * Restock an item: record the purchase as a clinic expense, increase the
     * stock balance, remember the latest unit cost and log a ledger entry.
     *
     * @param array $expenseOverrides Optional overrides for the created expense
     *                                (clinic_expense_category_id, is_paid, date, doctor_id).
     */
    public function restock(WarehouseItem $item, int $quantity, ?float $unitCost = null, array $expenseOverrides = []): ClinicExpense
    {
        $unitCost = $unitCost ?? (float) $item->cost_price;

        return DB::transaction(function () use ($item, $quantity, $unitCost, $expenseOverrides) {
            // 1. Record the purchase as a clinic expense (reuse existing bill/payment logic).
            $expense = $this->clinicExpenses->create(array_merge([
                'name'                       => $item->name,
                'quantity'                   => $quantity,
                'price'                      => $unitCost,
                'clinic_expense_category_id' => $item->clinic_expense_category_id,
                'date'                       => now()->toDateString(),
                'is_paid'                    => false,
                'doctor_id'                  => null,
            ], $expenseOverrides));

            // 2. Increase stock and remember the latest purchase cost.
            $item->forceFill([
                'quantity'   => (int) $item->quantity + $quantity,
                'cost_price' => $unitCost,
            ])->save();

            // 3. Ledger entry linked to the originating expense.
            $this->recordTransaction(
                $item,
                WarehouseTransaction::TYPE_PURCHASE,
                $quantity,
                $unitCost,
                $expense,
                $expense->doctor_id
            );

            return $expense;
        });
    }

    /**
     * Apply (or re-apply) the warehouse consumption for a case.
     *
     * - When $items is null, the case category's default kit is used.
     * - When $items is provided (even empty), it is the explicit source of truth.
     * Previous consumption for the case is reversed first, then the new list is
     * applied, and cases.item_cost is recalculated from the consumed cost.
     *
     * @param array<int,array{warehouse_item_id:int,quantity:int}>|null $items
     */
    public function syncCaseConsumption(CaseModel $case, ?array $items): void
    {
        // Defensive: on a tenant where the warehouse tables aren't migrated yet,
        // never break the core case flow — simply skip stock handling.
        if (!$this->warehouseReady()) {
            return;
        }

        $consumedItems = DB::transaction(function () use ($case, $items) {
            // 1. Undo whatever this case consumed before (restores stock).
            $this->reverseCaseConsumption($case);

            // 2. Resolve the desired list: explicit wins, else category kit.
            $desired = $items !== null
                ? $items
                : $this->defaultItemsForCategory($case->case_categores_id);

            // 3. Apply the new consumption.
            $totalCost = 0.0;
            $consumed = [];
            foreach ($this->normalizeItems($desired) as $row) {
                $item = WarehouseItem::lockForUpdate()->find($row['warehouse_item_id']);
                if (!$item) {
                    continue;
                }

                $qty = (int) $row['quantity'];
                if ($qty <= 0) {
                    continue;
                }

                $this->assertSufficientStock($item, $qty);

                $unitCost = (float) ($item->cost_price ?? 0);

                $item->decrement('quantity', $qty);

                $case->warehouseItems()->attach($item->id, [
                    'quantity'  => $qty,
                    'unit_cost' => $unitCost,
                ]);

                $this->recordTransaction(
                    $item,
                    WarehouseTransaction::TYPE_CONSUMPTION,
                    -$qty,
                    $unitCost,
                    $case,
                    $case->doctor_id
                );

                $totalCost += $qty * $unitCost;
                $consumed[] = $item;
            }

            // 4. Roll consumed cost into the case for profit reporting.
            $case->forceFill(['item_cost' => (int) round($totalCost)])->saveQuietly();

            return $consumed;
        });

        // After the stock changes have committed, alert on anything that dropped
        // to (or below) its low-stock threshold.
        foreach ($consumedItems as $item) {
            $this->notifyIfLowStock($item->refresh());
        }
    }

    /**
     * Restore stock for everything a case previously consumed and clear the
     * per-case consumption rows. Safe to call when nothing was consumed.
     */
    public function reverseCaseConsumption(CaseModel $case): void
    {
        if (!$this->warehouseReady()) {
            return;
        }

        $existing = $case->warehouseItems()->get();

        foreach ($existing as $item) {
            $qty = (int) $item->pivot->quantity;
            if ($qty > 0) {
                $item->increment('quantity', $qty);

                $this->recordTransaction(
                    $item,
                    WarehouseTransaction::TYPE_CONSUMPTION,
                    $qty, // positive: returning stock
                    $item->pivot->unit_cost,
                    $case,
                    $case->doctor_id,
                    'reversal'
                );
            }
        }

        $case->warehouseItems()->detach();
    }

    /**
     * Manually adjust stock (stock-take correction, breakage, etc.).
     */
    public function adjust(WarehouseItem $item, int $delta, ?string $reason = null): WarehouseTransaction
    {
        $transaction = DB::transaction(function () use ($item, $delta, $reason) {
            if ($delta < 0) {
                $this->assertSufficientStock($item, abs($delta));
            }

            $item->forceFill(['quantity' => (int) $item->quantity + $delta])->save();

            return $this->recordTransaction(
                $item,
                WarehouseTransaction::TYPE_ADJUSTMENT,
                $delta,
                $item->cost_price,
                null,
                null,
                $reason
            );
        });

        // A downward correction can push the item into low stock.
        if ($delta < 0) {
            $this->notifyIfLowStock($item->refresh());
        }

        return $transaction;
    }

    /**
     * Default kit for a case category as a plain consumption list.
     *
     * @return array<int,array{warehouse_item_id:int,quantity:int}>
     */
    public function defaultItemsForCategory(?int $categoryId): array
    {
        if (!$categoryId) {
            return [];
        }

        $category = CaseCategory::with('warehouseItems')->find($categoryId);
        if (!$category) {
            return [];
        }

        return $category->warehouseItems->map(fn (WarehouseItem $i) => [
            'warehouse_item_id' => $i->id,
            'quantity'          => (int) $i->pivot->quantity,
        ])->all();
    }

    /**
     * Normalize a raw items array, dropping malformed rows.
     *
     * @return array<int,array{warehouse_item_id:int,quantity:int}>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $row) {
            $id = (int) ($row['warehouse_item_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);

            if ($id > 0 && $qty > 0) {
                $normalized[] = ['warehouse_item_id' => $id, 'quantity' => $qty];
            }
        }

        return $normalized;
    }

    /**
     * Persist a low-stock alert for all active users when an item is at or below
     * its threshold. De-duplicated: if any user still has an unread low-stock
     * alert for this item, no new batch is created until it is resolved (stock
     * back above threshold or the alert read).
     */
    private function notifyIfLowStock(WarehouseItem $item): void
    {
        if (!$item->is_low) {
            return;
        }

        // Skip while a prior low-stock alert for this item is still unread,
        // so routine consumption doesn't spam the bell.
        $alreadyAlerted = Notification::query()
            ->where('type', Notification::TYPE_ALERT)
            ->where('is_read', false)
            ->where('data->warehouse_item_id', $item->id)
            ->exists();

        if ($alreadyAlerted) {
            return;
        }

        $title = 'Low stock alert';
        $body = "{$item->name} is low: {$item->quantity} {$item->unit} left (minimum {$item->min_quantity}).";

        $this->notifications->sendToAllUsers($title, $body, [
            'type'       => Notification::TYPE_ALERT,
            'priority'   => Notification::PRIORITY_HIGH,
            'action_url' => '/warehouse',
            'data'       => [
                'warehouse_item_id' => $item->id,
                'name'              => $item->name,
                'quantity'          => (int) $item->quantity,
                'min_quantity'      => (int) $item->min_quantity,
                'unit'              => $item->unit,
            ],
        ]);
    }

    /**
     * Write a ledger entry for a stock movement.
     */
    private function recordTransaction(
        WarehouseItem $item,
        string $type,
        int $change,
        ?float $unitCost,
        ?Model $source,
        ?int $doctorId,
        ?string $notes = null
    ): WarehouseTransaction {
        return $item->transactions()->create([
            'type'            => $type,
            'quantity_change' => $change,
            'unit_cost'       => $unitCost,
            'source_type'     => $source ? $source->getMorphClass() : null,
            'source_id'       => $source?->getKey(),
            'doctor_id'       => $doctorId,
            'notes'           => $notes,
        ]);
    }

    /**
     * Whether the warehouse tables exist on the current (tenant) connection.
     * Lets the case lifecycle stay safe before/without the warehouse migrations.
     * Not cached on purpose so a worker started before migration still picks it up.
     */
    private function warehouseReady(): bool
    {
        return Schema::hasTable('warehouse_items')
            && Schema::hasTable('case_warehouse_item')
            && Schema::hasTable('case_category_warehouse_item');
    }

    /**
     * Guard against driving stock negative.
     */
    private function assertSufficientStock(WarehouseItem $item, int $needed): void
    {
        if ((int) $item->quantity < $needed) {
            throw new \RuntimeException(
                "Insufficient stock for '{$item->name}': {$item->quantity} available, {$needed} required."
            );
        }
    }
}
