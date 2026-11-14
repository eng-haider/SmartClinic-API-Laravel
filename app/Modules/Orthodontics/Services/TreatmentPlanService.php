<?php

namespace App\Modules\Orthodontics\Services;

use App\Models\CaseModel;
use App\Modules\Orthodontics\Models\OrthodonticTreatmentPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * One treatment plan per orthodontic case. Each save keeps the previous
 * version in the audit log, so earlier plans can always be read back.
 */
class TreatmentPlanService
{
    public function __construct(private OrthodonticsAudit $audit)
    {
    }

    public function forCase(CaseModel $case): ?OrthodonticTreatmentPlan
    {
        return OrthodonticTreatmentPlan::where('case_id', $case->id)->first();
    }

    public function save(CaseModel $case, array $input, ?int $expectedRevision): OrthodonticTreatmentPlan
    {
        return DB::transaction(function () use ($case, $input, $expectedRevision) {
            $plan = OrthodonticTreatmentPlan::withTrashed()->where('case_id', $case->id)->lockForUpdate()->first();

            if (!$plan) {
                $plan = OrthodonticTreatmentPlan::create($input + [
                    'case_id' => $case->id,
                    'revision' => 1,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
                $this->audit->log($case->id, 'plan.created', $plan);

                return $plan;
            }

            if ($expectedRevision !== null && $expectedRevision !== $plan->revision) {
                throw OrthodonticsException::conflict($plan->revision);
            }

            $previous = $plan->only(array_merge($plan->getFillable(), ['updated_at']));
            if ($plan->trashed()) {
                $plan->restore();
            }
            $plan->fill($input + [
                'revision' => $plan->revision + 1,
                'updated_by' => Auth::id(),
            ])->save();
            $this->audit->log($case->id, 'plan.updated', $plan, ['revision' => $plan->revision, 'previous' => $previous]);

            return $plan;
        });
    }

    public function history(CaseModel $case): array
    {
        return \App\Modules\Orthodontics\Models\OrthodonticAuditLog::where('case_id', $case->id)
            ->whereIn('action', ['plan.created', 'plan.updated'])
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'user_id', 'action', 'details', 'created_at'])
            ->toArray();
    }
}
