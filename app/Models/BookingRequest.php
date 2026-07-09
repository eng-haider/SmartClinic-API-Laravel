<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'preferred_date',
        'preferred_time',
        'note',
        'status',
        'rejection_reason',
        'patient_id',
        'reservation_id',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'patient_id' => 'integer',
            'reservation_id' => 'integer',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * The patient created/linked when this request was approved.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The reservation created when this request was approved.
     */
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * The staff member who reviewed this request.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope a query to only include pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Whether the request is still awaiting review.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
