<?php

namespace App\Modules\Orthodontics\Models;

use Illuminate\Database\Eloquent\Model;

class OrthodonticAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'orthodontic_audit_logs';

    protected $fillable = ['case_id', 'user_id', 'action', 'subject_type', 'subject_id', 'details', 'ip'];

    protected function casts(): array
    {
        return [
            'case_id' => 'integer',
            'user_id' => 'integer',
            'subject_id' => 'integer',
            'details' => 'array',
        ];
    }
}
