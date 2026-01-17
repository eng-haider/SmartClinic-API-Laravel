<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'notes',
        'doctors_id',
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
            'doctors_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the patient that owns the recipe.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the doctor that owns the recipe.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctors_id');
    }

    /**
     * Get the recipe items for the recipe.
     */
    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
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
        return $query->where('doctors_id', $doctorId);
    }
}
