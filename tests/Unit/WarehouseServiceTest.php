<?php

namespace Tests\Unit;

use App\Models\CaseCategory;
use App\Models\CaseModel;
use App\Models\WarehouseItem;
use App\Models\WarehouseTransaction;
use App\Repositories\ClinicExpenseRepository;
use App\Services\WarehouseService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/**
 * Unit coverage for the warehouse stock engine.
 *
 * The tenant migrations can't run on sqlite (one creates a MySQL trigger), so
 * we build the minimal schema this service touches by hand and exercise the
 * service directly — no HTTP, auth or tenant context required.
 */
class WarehouseServiceTest extends TestCase
{
    private WarehouseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // CaseModel::created dispatches domain events; keep them inert here.
        Event::fake();

        $this->buildSchema();

        $this->service = new WarehouseService(new ClinicExpenseRepository());
    }

    private function buildSchema(): void
    {
        Schema::create('warehouse_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('unit')->nullable();
            $t->integer('quantity')->default(0);
            $t->integer('min_quantity')->default(0);
            $t->decimal('cost_price', 15, 2)->default(0);
            $t->unsignedBigInteger('clinic_expense_category_id')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('creator_id')->nullable();
            $t->unsignedBigInteger('updator_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('warehouse_transactions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('warehouse_item_id');
            $t->string('type');
            $t->integer('quantity_change');
            $t->decimal('unit_cost', 15, 2)->nullable();
            $t->nullableMorphs('source');
            $t->unsignedBigInteger('doctor_id')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('creator_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('case_warehouse_item', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('case_id');
            $t->unsignedBigInteger('warehouse_item_id');
            $t->integer('quantity');
            $t->decimal('unit_cost', 15, 2)->default(0);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('case_category_warehouse_item', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('case_category_id');
            $t->unsignedBigInteger('warehouse_item_id');
            $t->integer('quantity')->default(1);
            $t->timestamps();
        });

        Schema::create('cases', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('patient_id')->nullable();
            $t->unsignedBigInteger('doctor_id')->nullable();
            $t->unsignedBigInteger('case_categores_id')->nullable();
            $t->unsignedBigInteger('status_id')->nullable();
            $t->text('notes')->nullable();
            $t->bigInteger('price')->nullable();
            $t->text('tooth_num')->nullable();
            $t->bigInteger('item_cost')->default(0);
            $t->text('root_stuffing')->nullable();
            $t->boolean('is_paid')->default(false);
            $t->dateTime('case_date')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('case_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category_type')->nullable();
            $t->integer('order')->nullable();
            $t->integer('item_cost')->nullable();
            $t->boolean('without_detect_tooth')->default(false);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('clinic_expense_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('clinic_expenses', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->bigInteger('quantity')->nullable();
            $t->unsignedBigInteger('clinic_expense_category_id')->nullable();
            $t->date('date')->nullable();
            $t->decimal('price', 15, 2);
            $t->boolean('is_paid')->default(true);
            $t->unsignedBigInteger('doctor_id')->nullable();
            $t->unsignedBigInteger('creator_id')->nullable();
            $t->unsignedBigInteger('updator_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('bills', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('patient_id')->nullable();
            $t->nullableMorphs('billable');
            $t->boolean('is_paid')->default(true);
            $t->bigInteger('price');
            $t->unsignedBigInteger('doctor_id')->nullable();
            $t->boolean('use_credit')->default(false);
            $t->dateTime('bill_date')->nullable();
            $t->unsignedBigInteger('creator_id')->nullable();
            $t->unsignedBigInteger('updator_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
    }

    private function makeItem(array $overrides = []): WarehouseItem
    {
        return WarehouseItem::create(array_merge([
            'name' => 'Anesthetic',
            'unit' => 'vial',
            'quantity' => 0,
            'min_quantity' => 5,
            'cost_price' => 500,
        ], $overrides));
    }

    private function makeCase(?int $categoryId = null): CaseModel
    {
        return CaseModel::create([
            'patient_id' => 1,
            'doctor_id' => 1,
            'case_categores_id' => $categoryId,
            'status_id' => 2,
        ]);
    }

    public function test_restock_increases_stock_logs_ledger_and_records_expense(): void
    {
        $item = $this->makeItem(['quantity' => 10, 'cost_price' => 400]);

        $expense = $this->service->restock($item, 100, 500);

        $item->refresh();
        $this->assertSame(110, $item->quantity, 'stock should increase by restocked qty');
        $this->assertSame('500.00', (string) $item->cost_price, 'cost_price should update to latest purchase cost');

        // Expense (cash out) is recorded for the purchase.
        $this->assertDatabaseHas('clinic_expenses', [
            'id' => $expense->id,
            'name' => 'Anesthetic',
            'quantity' => 100,
            'price' => '500.00',
        ]);

        // Ledger entry linked back to the expense.
        $this->assertDatabaseHas('warehouse_transactions', [
            'warehouse_item_id' => $item->id,
            'type' => WarehouseTransaction::TYPE_PURCHASE,
            'quantity_change' => 100,
            'source_type' => \App\Models\ClinicExpense::class,
            'source_id' => $expense->id,
        ]);
    }

    public function test_restock_defaults_unit_cost_to_item_cost_price(): void
    {
        $item = $this->makeItem(['quantity' => 0, 'cost_price' => 750]);

        $expense = $this->service->restock($item, 4, null);

        $this->assertSame('750.00', (string) $expense->price);
        $this->assertSame(4, $item->fresh()->quantity);
    }

    public function test_consumption_decrements_stock_and_sets_case_item_cost(): void
    {
        $item = $this->makeItem(['quantity' => 20, 'cost_price' => 500]);
        $case = $this->makeCase();

        $this->service->syncCaseConsumption($case, [
            ['warehouse_item_id' => $item->id, 'quantity' => 3],
        ]);

        $this->assertSame(17, $item->fresh()->quantity);
        $this->assertSame(1500, (int) $case->fresh()->item_cost, 'item_cost = qty * unit_cost');

        $this->assertDatabaseHas('case_warehouse_item', [
            'case_id' => $case->id,
            'warehouse_item_id' => $item->id,
            'quantity' => 3,
            'unit_cost' => '500.00',
        ]);
        $this->assertDatabaseHas('warehouse_transactions', [
            'warehouse_item_id' => $item->id,
            'type' => WarehouseTransaction::TYPE_CONSUMPTION,
            'quantity_change' => -3,
        ]);
    }

    public function test_resync_reverses_previous_then_applies_new_list(): void
    {
        $item = $this->makeItem(['quantity' => 20, 'cost_price' => 500]);
        $case = $this->makeCase();

        // First consumption: 3 used → 17 left.
        $this->service->syncCaseConsumption($case, [
            ['warehouse_item_id' => $item->id, 'quantity' => 3],
        ]);
        $this->assertSame(17, $item->fresh()->quantity);

        // Edit to 5 used → previous 3 returned then 5 taken → 15 left.
        $this->service->syncCaseConsumption($case, [
            ['warehouse_item_id' => $item->id, 'quantity' => 5],
        ]);

        $this->assertSame(15, $item->fresh()->quantity);
        $this->assertSame(2500, (int) $case->fresh()->item_cost);
        // Exactly one active consumption row remains for the case.
        $this->assertSame(1, $case->fresh()->warehouseItems()->count());
    }

    public function test_null_items_consumes_category_default_kit(): void
    {
        $item = $this->makeItem(['quantity' => 30, 'cost_price' => 100]);
        $category = CaseCategory::create(['name' => 'Root Canal', 'category_type' => 'dental']);
        $category->warehouseItems()->attach($item->id, ['quantity' => 2]);

        $case = $this->makeCase($category->id);

        // Passing null → fall back to the category's default kit.
        $this->service->syncCaseConsumption($case, null);

        $this->assertSame(28, $item->fresh()->quantity);
        $this->assertSame(200, (int) $case->fresh()->item_cost);
    }

    public function test_insufficient_stock_throws_and_leaves_stock_untouched(): void
    {
        $item = $this->makeItem(['quantity' => 2, 'cost_price' => 500]);
        $case = $this->makeCase();

        $this->expectException(\RuntimeException::class);

        try {
            $this->service->syncCaseConsumption($case, [
                ['warehouse_item_id' => $item->id, 'quantity' => 5],
            ]);
        } finally {
            // Transaction rolled back — stock unchanged.
            $this->assertSame(2, $item->fresh()->quantity);
        }
    }

    public function test_reverse_consumption_restores_stock(): void
    {
        $item = $this->makeItem(['quantity' => 10, 'cost_price' => 500]);
        $case = $this->makeCase();

        $this->service->syncCaseConsumption($case, [
            ['warehouse_item_id' => $item->id, 'quantity' => 4],
        ]);
        $this->assertSame(6, $item->fresh()->quantity);

        $this->service->reverseCaseConsumption($case);

        $this->assertSame(10, $item->fresh()->quantity);
        $this->assertSame(0, $case->fresh()->warehouseItems()->count());
    }

    public function test_adjust_changes_stock_and_logs_adjustment(): void
    {
        $item = $this->makeItem(['quantity' => 10]);

        $this->service->adjust($item, -3, 'breakage');

        $this->assertSame(7, $item->fresh()->quantity);
        $this->assertDatabaseHas('warehouse_transactions', [
            'warehouse_item_id' => $item->id,
            'type' => WarehouseTransaction::TYPE_ADJUSTMENT,
            'quantity_change' => -3,
            'notes' => 'breakage',
        ]);
    }

    public function test_low_stock_scope_flags_items_at_or_below_threshold(): void
    {
        $low = $this->makeItem(['name' => 'Low', 'quantity' => 3, 'min_quantity' => 5]);
        $ok = $this->makeItem(['name' => 'Ok', 'quantity' => 50, 'min_quantity' => 5]);

        $lowIds = WarehouseItem::lowStock()->pluck('id')->all();

        $this->assertContains($low->id, $lowIds);
        $this->assertNotContains($ok->id, $lowIds);
        $this->assertTrue($low->fresh()->is_low);
        $this->assertFalse($ok->fresh()->is_low);
    }
}
