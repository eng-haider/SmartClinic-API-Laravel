<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'status_id',
        'notes',
        'reservation_start_date',
        'reservation_end_date',
        'reservation_from_time',
        'reservation_to_time',
        'is_waiting',
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
            'patient_id' => 'integer',
            'doctor_id' => 'integer',
            'status_id' => 'integer',
            'creator_id' => 'integer',
            'updator_id' => 'integer',
            'reservation_start_date' => 'date',
            'reservation_end_date' => 'date',
            'is_waiting' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically set creator_id when creating
        static::creating(function ($reservation) {
            if (Auth::check()) {
                $reservation->creator_id = Auth::id();
            }
        });

        // Automatically set updator_id when updating
        static::updating(function ($reservation) {
            if (Auth::check()) {
                $reservation->updator_id = Auth::id();
            }
        });
    }

    /**
     * Get the patient that owns the reservation.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the doctor assigned to the reservation.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Get the status of the reservation.
     */
    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all of the reservation's notes.
     */
    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    /**
     * Get all of the reservation's bills.
     */
    public function bills()
    {
        return $this->morphMany(Bill::class, 'billable');
    }

    /**
     * Scope a query to only include waiting reservations.
     */
    public function scopeWaiting($query)
    {
        return $query->where('is_waiting', true);
    }

    /**
     * Scope a query to only include non-waiting reservations.
     */
    public function scopeNotWaiting($query)
    {
        return $query->where('is_waiting', false);
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
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, int $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('reservation_start_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter by specific date.
     */
    public function scopeByDate($query, $date)
    {
        return $query->whereDate('reservation_start_date', $date);
    }

    /**
     * Scope a query to filter upcoming reservations.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('reservation_start_date', '>=', now()->toDateString())
                     ->orderBy('reservation_start_date')
                     ->orderBy('reservation_from_time');
    }

    /**
     * Scope a query to filter past reservations.
     */
    public function scopePast($query)
    {
        return $query->where('reservation_end_date', '<', now()->toDateString())
                     ->orderBy('reservation_start_date', 'desc');
    }

    /**
     * Scope a query to filter today's reservations.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('reservation_start_date', '<=', now()->toDateString())
                     ->whereDate('reservation_end_date', '>=', now()->toDateString())
                     ->orderBy('reservation_from_time');
    }
}
