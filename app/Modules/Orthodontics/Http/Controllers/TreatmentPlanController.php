<?php

namespace App\Modules\Orthodontics\Http\Controllers;

use App\Modules\Orthodontics\Http\Requests\TreatmentPlanRequest;
use App\Modules\Orthodontics\Http\Resources\OrthodonticTreatmentPlanResource;
use App\Modules\Orthodontics\Services\OrthodonticsAccess;
use App\Modules\Orthodontics\Services\TreatmentPlanService;
use Illuminate\Http\JsonResponse;

class TreatmentPlanController extends OrthodonticsBaseController
{
    public function __construct(OrthodonticsAccess $access, private TreatmentPlanService $plans)
    {
        parent::__construct($access);
    }

    public function show(int $case): JsonResponse
    {
        $plan = $this->plans->forCase($this->caseFor($case));

        return $this->ok($plan ? new OrthodonticTreatmentPlanResource($plan) : null);
    }

    public function update(TreatmentPlanRequest $request, int $case): JsonResponse
    {
        $c = $this->caseFor($case, 'manage');
        $expected = $request->filled('expected_revision') ? (int) $request->input('expected_revision') : null;
        $plan = $this->plans->save($c, $request->planData(), $expected);

        return $this->ok(new OrthodonticTreatmentPlanResource($plan), 'Treatment plan saved');
    }

    public function history(int $case): JsonResponse
    {
        return $this->ok($this->plans->history($this->caseFor($case)));
    }
}
