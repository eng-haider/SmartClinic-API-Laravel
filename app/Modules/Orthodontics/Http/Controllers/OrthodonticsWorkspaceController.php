<?php

namespace App\Modules\Orthodontics\Http\Controllers;

use App\Modules\Orthodontics\Analysis\CephalometricCalculator;
use App\Modules\Orthodontics\Analysis\FaceAnalysisCalculator;
use App\Modules\Orthodontics\Models\OrthodonticAnalysis;
use App\Modules\Orthodontics\Models\OrthodonticCbctStudy;
use App\Modules\Orthodontics\Models\OrthodonticFile;
use App\Modules\Orthodontics\Http\Resources\OrthodonticAnalysisResource;
use App\Modules\Orthodontics\Http\Resources\OrthodonticCbctStudyResource;
use App\Modules\Orthodontics\Http\Resources\OrthodonticTreatmentPlanResource;
use App\Modules\Orthodontics\Services\LandmarkDetectionService;
use App\Modules\Orthodontics\Services\OrthodonticsAccess;
use App\Modules\Orthodontics\Services\OrthodonticsAudit;
use App\Modules\Orthodontics\Services\TreatmentPlanService;
use App\Repositories\ClinicSettingRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrthodonticsWorkspaceController extends OrthodonticsBaseController
{
    public function __construct(
        OrthodonticsAccess $access,
        private TreatmentPlanService $plans,
        private LandmarkDetectionService $detection,
        private OrthodonticsAudit $audit,
    ) {
        parent::__construct($access);
    }

    /**
     * GET cases/{case}/orthodontics - workspace summary for one case.
     */
    public function overview(int $case): JsonResponse
    {
        $c = $this->caseFor($case);
        $counts = OrthodonticAnalysis::where('case_id', $c->id)
            ->selectRaw('type, count(*) as total, sum(case when status = ? then 1 else 0 end) as reviewed', [OrthodonticAnalysis::STATUS_REVIEWED])
            ->groupBy('type')->get()->keyBy('type')
            ->map(fn ($r) => ['total' => (int) $r->total, 'reviewed' => (int) $r->reviewed]);

        $plan = $this->plans->forCase($c);

        return $this->ok([
            'case' => [
                'id' => $c->id,
                'case_date' => $c->case_date?->toDateString(),
                'category' => ['id' => $c->category?->id, 'name' => $c->category?->name, 'is_orthodontic' => true],
                'patient' => ['id' => $c->patient?->id, 'name' => $c->patient?->name],
                'doctor' => ['id' => $c->doctor?->id, 'name' => $c->doctor?->name],
            ],
            'can_manage' => $this->access->can(Auth::user(), (string) config('orthodontics.permissions.manage')),
            'analyses' => $counts,
            'files' => OrthodonticFile::where('case_id', $c->id)->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind'),
            'cbct_studies' => OrthodonticCbctStudy::where('case_id', $c->id)->where('status', OrthodonticCbctStudy::STATUS_READY)->count(),
            'treatment_plan' => $plan ? ['status' => $plan->status, 'start_date' => $plan->start_date?->toDateString(), 'revision' => $plan->revision] : null,
            'capabilities' => [
                'landmark_detection' => $this->detection->available(),
                'cbct_driver' => config('orthodontics.cbct.driver', 'local'),
                'moyers_table' => (bool) config('orthodontics.moyers_table'),
            ],
            'definitions' => [
                'ceph_landmarks' => CephalometricCalculator::LANDMARKS,
                'face_landmarks' => FaceAnalysisCalculator::LANDMARKS,
            ],
        ]);
    }

    /**
     * GET cases/{case}/orthodontics/report - everything a combined or single
     * report needs. ?final=1 keeps only doctor-reviewed analyses (a final
     * report must not contain unreviewed results); ?analysis_id=X for one.
     */
    public function report(Request $request, int $case, ClinicSettingRepository $settings): JsonResponse
    {
        $c = $this->caseFor($case);
        $final = $request->boolean('final');

        $query = OrthodonticAnalysis::with(['creator:id,name', 'reviewer:id,name'])
            ->where('case_id', $c->id)
            ->orderBy('type')->orderBy('analysis_date')->orderBy('id');
        if ($request->filled('analysis_id')) {
            $query->where('id', (int) $request->input('analysis_id'));
        }
        if ($request->filled('types')) {
            $query->whereIn('type', array_intersect(explode(',', (string) $request->input('types')), OrthodonticAnalysis::TYPES));
        }
        if ($final) {
            $query->where('status', OrthodonticAnalysis::STATUS_REVIEWED);
        }
        $analyses = $query->get();
        $plan = $this->plans->forCase($c);
        $patient = $c->patient;

        $this->audit->log($c->id, 'report.generated', null, ['final' => $final, 'analyses' => $analyses->pluck('id')->all()]);

        return $this->ok([
            'generated_at' => now()->toIso8601String(),
            'final' => $final,
            'clinic' => $settings->publicIdentity(),
            'patient' => $patient ? [
                'id' => $patient->id,
                'name' => $patient->name,
                'sex' => $patient->sex,
                'age' => $patient->age,
                'birth_date' => $patient->birth_date ? (string) $patient->birth_date : null,
            ] : null,
            'doctor' => ['id' => $c->doctor?->id, 'name' => $c->doctor?->name],
            'case' => ['id' => $c->id, 'category' => $c->category?->name, 'case_date' => $c->case_date?->toDateString(), 'notes' => $c->notes],
            // Full data: the report redraws the tracing from the landmarks.
            'analyses' => OrthodonticAnalysisResource::collection($analyses),
            'unreviewed_count' => OrthodonticAnalysis::where('case_id', $c->id)->where('status', '!=', OrthodonticAnalysis::STATUS_REVIEWED)->count(),
            'treatment_plan' => $plan ? new OrthodonticTreatmentPlanResource($plan) : null,
            'cbct_studies' => OrthodonticCbctStudyResource::collection(OrthodonticCbctStudy::where('case_id', $c->id)->where('status', OrthodonticCbctStudy::STATUS_READY)->get()),
        ]);
    }
}
