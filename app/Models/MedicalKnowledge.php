<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalKnowledge extends Model
{
    /**
     * The database connection that should be used by the model.
     */
    protected $connection = 'pgsql_embeddings';

    /**
     * The table associated with the model.
     */
    protected $table = 'medical_knowledge';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'clinic_id',
        'title',
        'content',
        'embedding',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope: filter by clinic.
     */
    public function scopeForClinic($query, string $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }
}
