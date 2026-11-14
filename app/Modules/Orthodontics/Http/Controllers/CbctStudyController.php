<?php

namespace App\Modules\Orthodontics\Http\Controllers;

use App\Modules\Orthodontics\Cbct\CbctService;
use App\Modules\Orthodontics\Http\Resources\OrthodonticCbctStudyResource;
use App\Modules\Orthodontics\Models\OrthodonticCbctInstance;
use App\Modules\Orthodontics\Models\OrthodonticCbctStudy;
use App\Modules\Orthodontics\Services\OrthodonticsAccess;
use App\Modules\Orthodontics\Services\OrthodonticsAudit;
use App\Modules\Orthodontics\Services\OrthodonticsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CbctStudyController extends OrthodonticsBaseController
{
    public function __construct(OrthodonticsAccess $access, private CbctService $cbct, private OrthodonticsAudit $audit)
    {
        parent::__construct($access);
    }

    public function index(int $case): JsonResponse
    {
        $c = $this->caseFor($case);

        return $this->ok(OrthodonticCbctStudyResource::collection(
            OrthodonticCbctStudy::where('case_id', $c->id)->orderByDesc('id')->get()
        ));
    }

    public function store(Request $request, int $case): JsonResponse
    {
        $c = $this->caseFor($case, 'manage');
        $data = $request->validate(['description' => ['nullable', 'string', 'max:190']]);

        return $this->ok(new OrthodonticCbctStudyResource($this->cbct->create($c, $data['description'] ?? null)), 'Study created', 201);
    }

    /**
     * POST .../cbct/{study}/instances  files[] - one batch of DICOM files.
     */
    public function upload(Request $request, int $case, int $study): JsonResponse
    {
        $s = $this->find($case, $study, 'manage');
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:200'],
            'files.*' => ['file', 'max:' . (int) config('orthodontics.max_upload_kb.dicom_batch')],
        ]);
        $total = array_sum(array_map(fn ($f) => $f->getSize(), $request->file('files')));
        if ($total > (int) config('orthodontics.max_upload_kb.dicom_batch') * 1024) {
            throw new OrthodonticsException('This batch is too large. Upload fewer files at a time.', 413, 'batch_too_large');
        }

        $result = $this->cbct->addInstances($s, $request->file('files'));

        return $this->ok($result + ['study' => new OrthodonticCbctStudyResource($s->fresh())], 'Batch processed');
    }

    public function complete(int $case, int $study): JsonResponse
    {
        $s = $this->find($case, $study, 'manage');

        return $this->ok(new OrthodonticCbctStudyResource($this->cbct->complete($s)), 'Study ready');
    }

    /**
     * GET .../cbct/{study} - the study and its instances in viewing order.
     */
    public function show(int $case, int $study): JsonResponse
    {
        $s = $this->find($case, $study);
        $this->audit->log($s->case_id, 'cbct.opened', $s);

        return $this->ok([
            'study' => new OrthodonticCbctStudyResource($s),
            'instances' => $this->cbct->orderedInstances($s),
        ]);
    }

    /**
     * GET .../cbct/{study}/instances/{instance} - the DICOM file, streamed
     * through Laravel (never a public URL).
     */
    public function instance(int $case, int $study, int $instance)
    {
        $s = $this->find($case, $study);
        if ($s->status !== OrthodonticCbctStudy::STATUS_READY) {
            throw new OrthodonticsException('This study is not ready yet.', 409, 'study_not_ready');
        }
        $row = OrthodonticCbctInstance::where('study_id', $s->id)->find($instance);
        if (!$row) {
            throw OrthodonticsException::notFound('DICOM instance');
        }

        return $this->cbct->driver($s->driver)->stream($row);
    }

    public function viewerState(Request $request, int $case, int $study): JsonResponse
    {
        $s = $this->find($case, $study, 'manage');
        $data = $request->validate(['viewer_state' => ['present', 'array']]);
        if (strlen((string) json_encode($data['viewer_state'])) > 256 * 1024) {
            throw new OrthodonticsException('Viewer state is too large.', 422, 'invalid_state');
        }
        $s->viewer_state = $data['viewer_state'];
        $s->save();

        return $this->ok(new OrthodonticCbctStudyResource($s), 'Viewer state saved');
    }

    public function destroy(int $case, int $study): JsonResponse
    {
        $s = $this->find($case, $study, 'manage');
        if ($s->status === OrthodonticCbctStudy::STATUS_UPLOADING) {
            // An unfinished upload: drop its partial files.
            $this->cbct->abandon($s, 'Cancelled by user');
        }
        $s->delete(); // soft delete - a completed study's files are kept
        $this->audit->log($s->case_id, 'cbct.deleted', $s);

        return $this->ok(null, 'Study removed');
    }

    private function find(int $caseId, int $studyId, string $ability = 'view'): OrthodonticCbctStudy
    {
        $c = $this->caseFor($caseId, $ability);
        $s = OrthodonticCbctStudy::where('case_id', $c->id)->find($studyId);
        if (!$s) {
            throw OrthodonticsException::notFound('CBCT study');
        }

        return $s;
    }
}
