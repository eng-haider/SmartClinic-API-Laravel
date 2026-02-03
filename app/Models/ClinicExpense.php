<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicExpense extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clinic_expenses';

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
        'quantity',
        'clinic_expense_category_id',
        'date',
        'price',
        'is_paid',
        'doctor_id',
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
            'price' => 'decimal:2',
            'is_paid' => 'boolean',
            'date' => 'date',
            'creator_id' => 'integer',
            'updator_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the expense category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ClinicExpenseCategory::class, 'clinic_expense_category_id');
    }

    /**
     * Get the doctor associated with this expense.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Get the user who created this expense.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the user who last updated this expense.
     */
    public function updator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updator_id');
    }

    /**
     * Scope a query to only include paid expenses.
     */
    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    /**
     * Scope a query to only include unpaid expenses.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter by start date (from).
     */
    public function scopeDateFrom($query, $date)
    {
        return $query->where('date', '>=', $date);
    }

    /**
     * Scope a query to filter by end date (to).
     */
    public function scopeDateTo($query, $date)
    {
        return $query->where('date', '<=', $date);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('clinic_expense_category_id', $categoryId);
    }

    /**
     * Calculate the total amount for the expense.
     */
    public function getTotalAttribute(): float
    {
        return ($this->quantity ?? 1) * $this->price;
    }
}
