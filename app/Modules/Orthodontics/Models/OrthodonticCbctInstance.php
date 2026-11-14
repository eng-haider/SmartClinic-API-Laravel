<?php

namespace App\Modules\Orthodontics\Models;

use Illuminate\Database\Eloquent\Model;

class OrthodonticCbctInstance extends Model
{
    protected $table = 'orthodontic_cbct_instances';

    protected $fillable = [
        'study_id', 'sop_instance_uid', 'series_instance_uid', 'instance_number', 'frames',
        'rows', 'columns', 'slice_position', 'path', 'orthanc_instance_id', 'size', 'sha256',
    ];

    protected $hidden = ['path', 'orthanc_instance_id'];

    protected function casts(): array
    {
        return [
            'study_id' => 'integer',
            'instance_number' => 'integer',
            'frames' => 'integer',
            'rows' => 'integer',
            'columns' => 'integer',
            'slice_position' => 'float',
            'size' => 'integer',
        ];
    }

    public function study()
    {
        return $this->belongsTo(OrthodonticCbctStudy::class, 'study_id');
    }
}
