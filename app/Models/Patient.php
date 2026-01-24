<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'age',
        'doctor_id',
        'clinics_id',
        'phone',
        'systemic_conditions',
        'sex',
        'address',
        'notes',
        'birth_date',
        'rx_id',
        'note',
        'from_where_come_id',
        'identifier',
        'credit_balance',
        'credit_balance_add_at',
        'creator_id',
        'updator_id',
        'tooth_details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'doctor_id' => 'integer',
            'clinics_id' => 'integer',
            'sex' => 'integer',
            'birth_date' => 'date',
            'from_where_come_id' => 'integer',
            'credit_balance' => 'integer',
            'credit_balance_add_at' => 'datetime',
            'creator_id' => 'integer',
            'updator_id' => 'integer',
            'tooth_details' => 'array',
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
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->creator_id = Auth::id();
                $model->updator_id = Auth::id();
            }
        });

        // Automatically set updator_id when updating
        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updator_id = Auth::id();
            }
        });
    }

    /**
     * Get the doctor assigned to the patient.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Get the clinic that owns the patient.
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinics_id');
    }

    /**
     * Get the source where the patient came from.
     */
    public function fromWhereCome()
    {
        return $this->belongsTo(FromWhereCome::class, 'from_where_come_id');
    }

    /**
     * Get the cases for the patient.
     */
    public function cases()
    {
        return $this->hasMany(CaseModel::class);
    }

    /**
     * Get the recipes for the patient.
     */
    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * Get all of the patient's notes.
     */
    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    /**
     * Get all of the patient's reservations.
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Get all of the patient's bills.
     */
    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Get all of the patient's images.
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * Get the patient's gender label.
     */
    public function getSexLabelAttribute(): string
    {
        return match($this->sex) {
            1 => 'Male',
            2 => 'Female',
            default => 'Unknown',
        };
    }

    /**
     * Scope a query to filter by clinic.
     */
    public function scopeByClinic($query, int $clinicId)
    {
        return $query->where('clinics_id', $clinicId);
    }

    /**
     * Scope a query to filter by doctor.
     */
    public function scopeByDoctor($query, int $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    /**
     * Get the user who created this patient.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the user who last updated this patient.
     */
    public function updator()
    {
        return $this->belongsTo(User::class, 'updator_id');
    }
}
