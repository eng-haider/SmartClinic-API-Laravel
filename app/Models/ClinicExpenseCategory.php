<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicExpenseCategory extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clinic_expense_categories';

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
        'description',
        'clinic_id',
        'is_active',
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
            'is_active' => 'boolean',
            'creator_id' => 'integer',
            'updator_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the clinic that owns this expense category.
     */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic_id');
    }

    /**
     * Get the expenses in this category.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(ClinicExpense::class, 'clinic_expense_category_id');
    }

    /**
     * Get the user who created this category.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the user who last updated this category.
     */
    public function updator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updator_id');
    }

    /**
     * Scope a query to only include active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by clinic.
     */
    public function scopeForClinic($query, $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }
}
