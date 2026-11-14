<?php

namespace App\Modules\Orthodontics\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable snapshot of an analysis at one revision.
 */
class OrthodonticAnalysisRevision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'orthodontic_analysis_revisions';

    protected $fillable = ['analysis_id', 'revision', 'status', 'data', 'results', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'analysis_id' => 'integer',
            'revision' => 'integer',
            'data' => 'array',
            'results' => 'array',
        ];
    }
}
