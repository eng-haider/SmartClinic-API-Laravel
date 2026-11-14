<?php

namespace App\Modules\Orthodontics\Models;

use App\Models\CaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrthodonticCbctStudy extends Model
{
    use SoftDeletes;

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $table = 'orthodontic_cbct_studies';

    protected $fillable = [
        'case_id', 'driver', 'status', 'description', 'study_instance_uid', 'orthanc_study_id',
        'modality', 'study_date', 'instances_count', 'total_size', 'series', 'viewer_state',
        'error', 'uploaded_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'case_id' => 'integer',
            'instances_count' => 'integer',
            'total_size' => 'integer',
            'series' => 'array',
            'viewer_state' => 'array',
            'study_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function instances()
    {
        return $this->hasMany(OrthodonticCbctInstance::class, 'study_id');
    }
}
