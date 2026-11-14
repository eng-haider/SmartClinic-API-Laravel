<?php

namespace App\Modules\Orthodontics\Models;

use App\Models\CaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An original upload (radiograph, face photo, 3D model). The file itself is
 * kept on the tenant's private disk and is never overwritten; deleting the
 * record only soft-deletes it, so the original stays on disk.
 */
class OrthodonticFile extends Model
{
    use SoftDeletes;

    public const IMAGE_KINDS = ['ceph_xray', 'face_frontal', 'face_smile', 'face_profile', 'intraoral_photo'];

    public const MODEL_KINDS = ['model_upper', 'model_lower', 'model_combined'];

    public const MODEL_EXTENSIONS = ['stl', 'ply', 'obj'];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected $table = 'orthodontic_files';

    protected $fillable = [
        'case_id', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'extension',
        'size', 'sha256', 'width', 'height', 'meta', 'uploaded_by',
    ];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return [
            'case_id' => 'integer',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'meta' => 'array',
            'uploaded_by' => 'integer',
        ];
    }

    public static function kinds(): array
    {
        return array_merge(self::IMAGE_KINDS, self::MODEL_KINDS);
    }

    public function isModel(): bool
    {
        return in_array($this->kind, self::MODEL_KINDS, true);
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}
