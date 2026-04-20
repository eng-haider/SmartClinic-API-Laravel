<?php

namespace App\Services\Messaging;

use App\Models\AutomationTarget;
use App\Models\CaseModel;
use App\Models\MessageTemplate;
use App\Models\Patient;

class TemplateEngine
{
    /**
     * Render a template with context from an automation target.
     */
    public function renderForTarget(AutomationTarget $target): string
    {
        $template = MessageTemplate::where('key', $target->rule->template_key)
            ->active()
            ->first();

        if (!$template) {
            throw new \RuntimeException("Template not found or inactive: {$target->rule->template_key}");
        }

        $variables = $this->buildVariables($target);

        return $template->render($variables);
    }

    /**
     * Render a template by key with given variables.
     */
    public function render(string $templateKey, array $variables): string
    {
        $template = MessageTemplate::where('key', $templateKey)
            ->active()
            ->first();

        if (!$template) {
            throw new \RuntimeException("Template not found or inactive: {$templateKey}");
        }

        return $template->render($variables);
    }

    /**
     * Build variable map from automation target context.
     */
    public function buildVariables(AutomationTarget $target): array
    {
        $target->loadMissing(['patient', 'caseModel.doctor', 'caseModel.category']);

        $patient = $target->patient;
        $case = $target->caseModel;

        $vars = [
            'patient_name' => $patient->name ?? '',
            'patient_phone' => $patient->phone ?? '',
        ];

        if ($case) {
            $vars['case_name'] = $case->category->name ?? '';
            $vars['case_date'] = $case->case_date?->format('Y-m-d') ?? '';
            $vars['case_notes'] = $case->notes ?? '';
            $vars['doctor_name'] = $case->doctor->name ?? '';
        }

        // Clinic name from tenant
        $vars['clinic_name'] = tenant('name') ?? '';

        return $vars;
    }

    /**
     * Preview a template with sample data (for admin UI).
     */
    public function preview(string $templateKey): string
    {
        $sampleVars = [
            'patient_name' => 'أحمد محمد',
            'patient_phone' => '9647801234567',
            'case_name' => 'علاج عصب',
            'case_date' => '2026-04-10',
            'case_notes' => 'ملاحظات العلاج',
            'doctor_name' => 'د. علي',
            'clinic_name' => 'عيادة الصحة',
            'notes' => 'ملاحظات إضافية',
        ];

        return $this->render($templateKey, $sampleVars);
    }
}
