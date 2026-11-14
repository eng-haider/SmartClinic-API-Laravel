<?php

namespace App\Modules\Orthodontics\Services;

use App\Modules\Orthodontics\Models\OrthodonticAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Audit trail of orthodontic record changes and of access to images/DICOM.
 * Never lets an audit failure break the clinical action itself.
 */
class OrthodonticsAudit
{
    public function log(?int $caseId, string $action, ?Model $subject = null, array $details = []): void
    {
        try {
            OrthodonticAuditLog::create([
                'case_id' => $caseId,
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'details' => $details ?: null,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Orthodontics audit log failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
