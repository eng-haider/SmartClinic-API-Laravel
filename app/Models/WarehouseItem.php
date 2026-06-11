<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'warehouse_items';

    /**
     * The "booted" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Set creator_id when creating
        static::creating(function ($model) {
            if (auth()->check() && is_null($model->creator_id)) {
                $model->creator_id = auth()->id();
            }
        });

        // Set updator_id when updating
        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updator_id = auth()->id();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'unit',
        'quantity',
        'min_quantity',
        'cost_price',
        'clinic_expense_category_id',
        'notes',
        'creator_id',
        'updator_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'min_quantity' => 'integer',
            'cost_price' => 'decimal:2',
            'clinic_expense_category_id' => 'integer',
            'creator_id' => 'integer',
            'updator_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['is_low'];

    /**
     * Stock movement ledger for this item.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WarehouseTransaction::class);
    }

    /**
     * Default expense category used when recording purchase expenses.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ClinicExpenseCategory::class, 'clinic_expense_category_id');
    }

    /**
     * The user who created this item.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * The user who last updated this item.
     */
    public function updator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updator_id');
    }

    /**
     * Cases that consumed this item.
     */
    public function cases(): BelongsToMany
    {
        return $this->belongsToMany(CaseModel::class, 'case_warehouse_item', 'warehouse_item_id', 'case_id')
            ->withPivot(['quantity', 'unit_cost'])
            ->withTimestamps();
    }

    /**
     * Case categories that list this item in their default kit.
     */
    public function caseCategories(): BelongsToMany
    {
        return $this->belongsToMany(CaseCategory::class, 'case_category_warehouse_item', 'warehouse_item_id', 'case_category_id')
            ->withPivot(['quantity'])
            ->withTimestamps();
    }

    /**
     * Scope a query to only include items at or below their low-stock threshold.
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity', '<=', 'min_quantity');
    }

    /**
     * Whether the item is at or below its low-stock threshold.
     */
    public function getIsLowAttribute(): bool
    {
        return (int) $this->quantity <= (int) $this->min_quantity;
    }
}
