<?php

namespace App\Modules\Orthodontics\Http\Controllers;

use App\Modules\Orthodontics\Http\Requests\StoreAnalysisRequest;
use App\Modules\Orthodontics\Http\Resources\OrthodonticAnalysisResource;
use App\Modules\Orthodontics\Models\OrthodonticAnalysis;
use App\Modules\Orthodontics\Models\OrthodonticFile;
use App\Modules\Orthodontics\Services\AnalysisService;
use App\Modules\Orthodontics\Services\LandmarkDetectionService;
use App\Modules\Orthodontics\Services\OrthodonticsAccess;
use App\Modules\Orthodontics\Services\OrthodonticsAudit;
use App\Modules\Orthodontics\Services\OrthodonticsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrthodonticAnalysisController extends OrthodonticsBaseController
{
    public function __construct(OrthodonticsAccess $access, private AnalysisService $analyses, private OrthodonticsAudit $audit)
    {
        parent::__construct($access);
    }

    public function index(Request $request, int $case): JsonResponse
    {
        $c = $this->caseFor($case);
        $request->validate(['type' => ['nullable', Rule::in(OrthodonticAnalysis::TYPES)]]);

        $list = OrthodonticAnalysis::with(['creator:id,name', 'reviewer:id,name'])
            ->where('case_id', $c->id)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->orderByDesc('analysis_date')->orderByDesc('id')
            ->get();

        // ?with_data=1 for comparisons, which need every set of landmarks.
        return $this->ok($request->boolean('with_data')
            ? OrthodonticAnalysisResource::collection($list)
            : OrthodonticAnalysisResource::summaries($list));
    }

    public function store(StoreAnalysisRequest $request, int $case): JsonResponse
    {
        $c = $this->caseFor($case, 'manage');
        $analysis = $this->analyses->create($c, $request->validated());

        return $this->ok(new OrthodonticAnalysisResource($analysis->load(['creator:id,name'])), 'Analysis saved', 201);
    }

    public function show(int $case, int $analysis): JsonResponse
    {
        return $this->ok(new OrthodonticAnalysisResource($this->find($case, $analysis)->load(['creator:id,name', 'reviewer:id,name'])));
    }

    public function update(StoreAnalysisRequest $request, int $case, int $analysis): JsonResponse
    {
        $a = $this->find($case, $analysis, 'manage');
        $input = $request->validated();
        $expected = isset($input['expected_revision']) ? (int) $input['expected_revision'] : null;
        unset($input['expected_revision']);

        $a = $this->analyses->update($a, $input, $expected);

        return $this->ok(new OrthodonticAnalysisResource($a->load(['creator:id,name', 'reviewer:id,name'])), 'Analysis saved');
    }

    /**
     * POST .../analyses/{analysis}/review  { reviewed: true|false }
     */
    public function review(Request $request, int $case, int $analysis): JsonResponse
    {
        $a = $this->find($case, $analysis, 'manage');
        $data = $request->validate(['reviewed' => ['required', 'boolean']]);

        return $this->ok(new OrthodonticAnalysisResource($this->analyses->review($a, (bool) $data['reviewed'])->load(['creator:id,name', 'reviewer:id,name'])), 'Review updated');
    }

    public function revisions(int $case, int $analysis): JsonResponse
    {
        $a = $this->find($case, $analysis);

        return $this->ok($a->revisions()->get(['id', 'revision', 'status', 'results', 'notes', 'created_by', 'created_at']));
    }

    public function revision(int $case, int $analysis, int $revision): JsonResponse
    {
        $a = $this->find($case, $analysis);
        $row = $a->revisions()->where('revision', $revision)->first();
        if (!$row) {
            throw OrthodonticsException::notFound('Revision');
        }

        return $this->ok($row);
    }

    public function destroy(int $case, int $analysis): JsonResponse
    {
        $this->analyses->delete($this->find($case, $analysis, 'manage'));

        return $this->ok(null, 'Analysis removed');
    }

    /**
     * POST .../analyses/compute - server computation without saving, so the
     * browser preview can be checked against the authoritative calculator.
     */
    public function compute(Request $request, int $case): JsonResponse
    {
        $this->caseFor($case);
        $data = $request->validate([
            'type' => ['required', Rule::in(OrthodonticAnalysis::TYPES)],
            'data' => ['present', 'array'],
        ]);

        return $this->ok($this->analyses->compute($data['type'], $data['data']));
    }

    /**
     * POST .../files/{file}/detect-landmarks - suggestions from a configured
     * provider; never saved by themselves.
     */
    public function detectLandmarks(int $case, int $file, LandmarkDetectionService $detection): JsonResponse
    {
        $c = $this->caseFor($case, 'manage');
        $record = OrthodonticFile::where('case_id', $c->id)->where('kind', 'ceph_xray')->find($file);
        if (!$record) {
            throw OrthodonticsException::notFound('Radiograph');
        }
        $landmarks = $detection->detect($record);
        $this->audit->log($c->id, 'ceph.landmarks_detected', $record, ['count' => count($landmarks), 'provider' => $detection->provider()]);

        return $this->ok([
            'landmarks' => $landmarks,
            'provider' => $detection->provider(),
            'note' => 'Automatically suggested points. Review and correct every point before saving.',
        ]);
    }

    private function find(int $caseId, int $analysisId, string $ability = 'view'): OrthodonticAnalysis
    {
        $c = $this->caseFor($caseId, $ability);
        $a = OrthodonticAnalysis::where('case_id', $c->id)->find($analysisId);
        if (!$a) {
            throw OrthodonticsException::notFound('Analysis');
        }
        $a->setRelation('case', $c);

        return $a;
    }
}
