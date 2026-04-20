<?php
namespace App\Models;
use App\Events\CaseCreated;
use App\Events\CaseCompleted;
use App\Traits\HasEmbeddings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseModel extends Model
{
    use HasFactory, SoftDeletes, HasEmbeddings;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cases';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (CaseModel $case) {
            CaseCreated::dispatch($case);
        });

        static::updated(function (CaseModel $case) {
            // Fire CaseCompleted when status changes to a "completed" status
            if ($case->wasChanged('status_id') && $case->status_id === self::COMPLETED_STATUS_ID) {
                CaseCompleted::dispatch($case);
            }
        });
    }

    /**
     * Status ID that represents "completed". Override via clinic settings if needed.
     */
    public const COMPLETED_STATUS_ID = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'case_categores_id',
        'notes',
        'status_id',
        'price',
        'tooth_num',
        'root_stuffing',
        'is_paid',
        'case_date',
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
            'case_categores_id' => 'integer',
            'status_id' => 'integer',
            'price' => 'integer',
            'is_paid' => 'boolean',
            'case_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the patient that owns the case.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the doctor that owns the case.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Get the category of the case.
     */
    public function category()
    {
        return $this->belongsTo(CaseCategory::class, 'case_categores_id');
    }

    /**
     * Get the status of the case.
     */
    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all of the case's notes.
     */
    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    /**
     * Get all of the case's bills.
     */
    public function bills()
    {
        return $this->morphMany(Bill::class, 'billable');
    }

    /**
     * Get the ophthalmology encounter details for the case (collection, for API include).
     */
    public function ophthalmologyEncounterDetails()
    {
        return $this->hasMany(OphthalmologyEncounterDetail::class, 'case_id');
    }

    /**
     * Get the single ophthalmology encounter detail for the case (for resource output).
     */
    public function ophthalmologyDetails()
    {
        return $this->hasOne(OphthalmologyEncounterDetail::class, 'case_id');
    }

    /**
     * Get the class name for polymorphic relations.
     * This ensures compatibility with legacy data stored as 'App\Models\Case'
     */
    public function getMorphClass()
    {
        return 'App\Models\Case';
    }

    /**
     * Scope a query to only include paid cases.
     */
    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    /**
     * Scope a query to only include unpaid cases.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    /**
     * Scope a query to filter by doctor.
     */
    public function scopeByDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    /**
     * Scope a query to filter by patient.
     */
    public function scopeByPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Get all of the case's images.
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * Convert case data to embedding content string.
     */
    public function toEmbeddingContent(): string
    {
        $parts = [
            "Dental/Medical Case",
            $this->patient ? "Patient: {$this->patient->name}" : null,
            $this->doctor ? "Doctor: {$this->doctor->name}" : null,
            $this->category ? "Category: {$this->category->name}" : null,
            $this->tooth_num ? "Tooth Number: {$this->tooth_num}" : null,
            $this->price ? "Price: {$this->price}" : null,
            $this->status ? "Status: {$this->status->name}" : null,
            "Paid: " . ($this->is_paid ? 'Yes' : 'No'),
            $this->case_date ? "Case Date: {$this->case_date->format('Y-m-d')}" : null,
            $this->getAttribute('notes') ? "Notes: {$this->getAttribute('notes')}" : null,
            $this->root_stuffing ? "Root Stuffing: {$this->root_stuffing}" : null,
        ];

        return implode('. ', array_filter($parts));
    }
}
