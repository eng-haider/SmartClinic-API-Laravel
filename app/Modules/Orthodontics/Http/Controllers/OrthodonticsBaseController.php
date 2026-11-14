<?php

namespace App\Modules\Orthodontics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Modules\Orthodontics\Services\OrthodonticsAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

abstract class OrthodonticsBaseController extends Controller
{
    public function __construct(protected OrthodonticsAccess $access)
    {
    }

    /**
     * The case, after every module, permission, ownership and category check.
     */
    protected function caseFor(int $caseId, string $ability = 'view'): CaseModel
    {
        return $this->access->case(Auth::user(), $caseId, $ability);
    }

    protected function ok($data, string $message = 'OK', int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data] + $extra, $status);
    }
}
