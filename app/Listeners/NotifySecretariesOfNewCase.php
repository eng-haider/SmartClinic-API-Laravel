<?php

namespace App\Listeners;

use App\Events\CaseCreated;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Notify the secretaries the clinic opted in (via the 'notify-new-cases'
 * permission) when a doctor adds a case, e.g. so they can bill the patient.
 *
 * Registered by listener discovery (app/Listeners) - don't also Event::listen it,
 * or every secretary gets the notification twice.
 */
class NotifySecretariesOfNewCase
{
    public const PERMISSION = 'notify-new-cases';

    public function __construct(
        private NotificationService $notifications,
    ) {}

    public function handle(CaseCreated $event): void
    {
        $case = $event->case;

        // Runs after the response is sent, so it must never break case creation.
        try {
            // Only cases a doctor adds, not ones a secretary or an import creates.
            $doctor = Auth::user();
            if (!$doctor || !$doctor->hasAnyRole(['doctor', 'clinic_super_doctor'])) {
                return;
            }

            $secretaries = User::role('secretary')
                ->permission(self::PERMISSION)
                ->where('is_active', true)
                ->get();

            if ($secretaries->isEmpty()) {
                return;
            }

            $case->loadMissing(['patient', 'category']);
            $patientName = $case->patient?->name ?? '-';
            $category = $case->category?->name;

            $title = 'New case';
            $body = $category
                ? "Dr. {$doctor->name} added a new case ({$category}) for {$patientName}."
                : "Dr. {$doctor->name} added a new case for {$patientName}.";

            $bodyAr = $category
                ? "أضاف د. {$doctor->name} حالة جديدة ({$category}) للمريض {$patientName}."
                : "أضاف د. {$doctor->name} حالة جديدة للمريض {$patientName}.";

            $this->notifications->sendToMultiple($secretaries->all(), $title, $body, [
                'type'       => Notification::TYPE_CASE,
                'priority'   => Notification::PRIORITY_MEDIUM,
                'action_url' => "/patients/{$case->patient_id}",
                'data'       => [
                    'case_id'      => $case->id,
                    'patient_id'   => $case->patient_id,
                    'patient_name' => $patientName,
                    'doctor_id'    => $doctor->id,
                    'doctor_name'  => $doctor->name,
                    'category'     => $category,
                    // Single title/body column - the Arabic copy rides along here.
                    'title_ar'     => 'حالة جديدة',
                    'body_ar'      => $bodyAr,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify secretaries of a new case', [
                'case_id' => $case->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
