<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Embedding extends Model
{
    /**
     * The database connection that should be used by the model.
     */
    protected $connection = 'pgsql_embeddings';

    /**
     * The table associated with the model.
     */
    protected $table = 'embeddings';

    /**
     * Disable default timestamps (we only use updated_at manually).
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'clinic_id',
        'table_name',
        'record_id',
        'content',
        'embedding',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
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

    /**
     * Scope: filter by specific record.
     */
    public function scopeForRecord($query, string $tableName, int $recordId)
    {
        return $query->where('table_name', $tableName)
                     ->where('record_id', $recordId);
    }

    /**
     * Scope: filter by table name.
     */
    public function scopeForTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }
}
