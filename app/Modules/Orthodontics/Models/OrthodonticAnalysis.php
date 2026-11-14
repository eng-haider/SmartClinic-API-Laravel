<?php

namespace App\Modules\Orthodontics\Models;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved analysis. `data` is what the doctor entered (landmarks, calibration,
 * annotations); `results` is what the server computed from it. Every save
 * bumps `revision` and writes an OrthodonticAnalysisRevision, so earlier
 * versions are never lost.
 */
class OrthodonticAnalysis extends Model
{
    use SoftDeletes;

    public const TYPES = ['ceph', 'cast', 'face', 'cbct'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEWED = 'reviewed';

    protected $table = 'orthodontic_analyses';

    protected $fillable = [
        'case_id', 'type', 'title', 'file_id', 'cbct_study_id', 'analysis_date', 'status',
        'revision', 'data', 'results', 'notes', 'created_by', 'updated_by', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'case_id' => 'integer',
            'file_id' => 'integer',
            'cbct_study_id' => 'integer',
            'revision' => 'integer',
            'analysis_date' => 'date',
            'data' => 'array',
            'results' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function file()
    {
        return $this->belongsTo(OrthodonticFile::class, 'file_id');
    }

    public function cbctStudy()
    {
        return $this->belongsTo(OrthodonticCbctStudy::class, 'cbct_study_id');
    }

    public function revisions()
    {
        return $this->hasMany(OrthodonticAnalysisRevision::class, 'analysis_id')->orderByDesc('revision');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isReviewed(): bool
    {
        return $this->status === self::STATUS_REVIEWED;
    }
}
