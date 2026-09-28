<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseTransaction extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Movement types.
     */
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_CONSUMPTION = 'consumption';
    public const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'warehouse_transactions';

    /**
     * The "booted" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check() && is_null($model->creator_id)) {
                $model->creator_id = auth()->id();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_item_id',
        'type',
        'quantity_change',
        'unit_cost',
        'source_type',
        'source_id',
        'doctor_id',
        'notes',
        'creator_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_item_id' => 'integer',
            'quantity_change' => 'integer',
            'unit_cost' => 'decimal:2',
            'source_id' => 'integer',
            'doctor_id' => 'integer',
            'creator_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * The item this movement belongs to.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(WarehouseItem::class, 'warehouse_item_id');
    }

    /**
     * The originating record (ClinicExpense for purchases, CaseModel for consumption).
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The doctor associated with this movement.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
