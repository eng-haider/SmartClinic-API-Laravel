<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Set creator_id when creating
        static::creating(function ($bill) {
            if (auth()->check() && is_null($bill->creator_id)) {
                $bill->creator_id = auth()->id();
            }
        });

        // Set updator_id when updating
        static::updating(function ($bill) {
            if (auth()->check()) {
                $bill->updator_id = auth()->id();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'billable_id',
        'billable_type',
        'is_paid',
        'price',
        'doctor_id',
        'creator_id',
        'updator_id',
        'use_credit',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'billable_id' => 'integer',
            'doctor_id' => 'integer',
            'creator_id' => 'integer',
            'updator_id' => 'integer',
            'price' => 'integer',
            'is_paid' => 'boolean',
            'use_credit' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the parent billable model (Case, Reservation, etc.).
     */
    public function billable()
    {
        return $this->morphTo();
    }

    /**
     * Get the patient that owns the bill.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the doctor assigned to the bill.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Get the user who created the bill.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the user who last updated the bill.
     */
    public function updator()
    {
        return $this->belongsTo(User::class, 'updator_id');
    }

    /**
     * Get all of the bill's notes.
     */
    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    /**
     * Scope a query to only include paid bills.
     */
    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    /**
     * Scope a query to only include unpaid bills.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    /**
     * Scope a query to only include bills that used credit.
     */
    public function scopeUsingCredit($query)
    {
        return $query->where('use_credit', true);
    }

    /**
     * Scope a query to only include bills that didn't use credit.
     */
    public function scopeNotUsingCredit($query)
    {
        return $query->where('use_credit', false);
    }

    /**
     * Scope a query to filter by patient.
     */
    public function scopeByPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope a query to filter by doctor.
     */
    public function scopeByDoctor($query, int $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    /**
     * Scope a query to filter by billable type.
     */
    public function scopeByBillableType($query, string $type)
    {
        return $query->where('billable_type', $type);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to get total revenue.
     */
    public function scopeTotalRevenue($query)
    {
        return $query->paid()->sum('price');
    }

    /**
     * Scope a query to get total outstanding.
     */
    public function scopeTotalOutstanding($query)
    {
        return $query->unpaid()->sum('price');
    }

    /**
     * Mark bill as paid.
     */
    public function markAsPaid(): bool
    {
        return $this->update(['is_paid' => true]);
    }

    /**
     * Mark bill as unpaid.
     */
    public function markAsUnpaid(): bool
    {
        return $this->update(['is_paid' => false]);
    }

    /**
     * Get payment status label.
     */
    public function getPaymentStatusAttribute(): string
    {
        return $this->is_paid ? 'Paid' : 'Unpaid';
    }

    /**
     * Get credit usage label.
     */
    public function getCreditUsageAttribute(): string
    {
        return $this->use_credit ? 'Credit Used' : 'No Credit';
    }
}
