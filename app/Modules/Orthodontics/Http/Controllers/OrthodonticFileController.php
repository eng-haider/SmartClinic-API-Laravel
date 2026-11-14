<?php

namespace App\Modules\Orthodontics\Http\Controllers;

use App\Modules\Orthodontics\Http\Resources\OrthodonticFileResource;
use App\Modules\Orthodontics\Models\OrthodonticFile;
use App\Modules\Orthodontics\Services\OrthodonticFileStorage;
use App\Modules\Orthodontics\Services\OrthodonticsAccess;
use App\Modules\Orthodontics\Services\OrthodonticsAudit;
use App\Modules\Orthodontics\Services\OrthodonticsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OrthodonticFileController extends OrthodonticsBaseController
{
    public function __construct(OrthodonticsAccess $access, private OrthodonticFileStorage $storage, private OrthodonticsAudit $audit)
    {
        parent::__construct($access);
    }

    public function index(Request $request, int $case): JsonResponse
    {
        $c = $this->caseFor($case);
        $files = OrthodonticFile::where('case_id', $c->id)
            ->when($request->filled('kind'), fn ($q) => $q->whereIn('kind', explode(',', (string) $request->input('kind'))))
            ->orderByDesc('id')
            ->get();

        return $this->ok(OrthodonticFileResource::collection($files));
    }

    public function store(Request $request, int $case): JsonResponse
    {
        $c = $this->caseFor($case, 'manage');
        $kind = (string) $request->input('kind');
        $isModel = in_array($kind, OrthodonticFile::MODEL_KINDS, true);
        $maxKb = (int) config('orthodontics.max_upload_kb.' . ($isModel ? 'model' : 'image'));

        $request->validate([
            'kind' => ['required', Rule::in(OrthodonticFile::kinds())],
            'file' => ['required', 'file', 'max:' . $maxKb],
            'captured_at' => ['nullable', 'date'],
            'label' => ['nullable', 'string', 'max:190'],
        ]);

        $file = $this->storage->store($c, $request->file('file'), $kind, array_filter([
            'captured_at' => $request->input('captured_at'),
            'label' => $request->input('label'),
        ]));

        return $this->ok(new OrthodonticFileResource($file), 'File uploaded', 201);
    }

    /**
     * GET .../files/{file}/content - the original bytes, for an authenticated
     * viewer only. Looked up through the case, so another case's (or clinic's)
     * file id is not found.
     */
    public function content(int $case, int $file)
    {
        $c = $this->caseFor($case);
        $record = OrthodonticFile::where('case_id', $c->id)->find($file);
        if (!$record) {
            throw OrthodonticsException::notFound('File');
        }

        return $this->storage->stream($record);
    }

    /**
     * PATCH .../files/{file} - label, capture date, and for models the
     * doctor's confirmation of the file's units (which unlocks mm values).
     */
    public function update(Request $request, int $case, int $file): JsonResponse
    {
        $c = $this->caseFor($case, 'manage');
        $record = OrthodonticFile::where('case_id', $c->id)->find($file);
        if (!$record) {
            throw OrthodonticsException::notFound('File');
        }

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:190'],
            'captured_at' => ['nullable', 'date'],
            'units' => ['nullable', Rule::in(['mm', 'cm', 'inch', 'm', 'custom'])],
            'scale_to_mm' => ['nullable', 'numeric', 'gt:0', 'lt:100000'],
            'bounding_box' => ['nullable', 'array'],
        ]);

        $meta = $record->meta ?? [];
        foreach (['label', 'captured_at', 'bounding_box'] as $key) {
            if (array_key_exists($key, $data)) {
                $meta[$key] = $data[$key];
            }
        }
        if ($record->isModel() && array_key_exists('units', $data)) {
            $scale = match ($data['units']) {
                'mm' => 1.0, 'cm' => 10.0, 'm' => 1000.0, 'inch' => 25.4,
                'custom' => isset($data['scale_to_mm']) ? (float) $data['scale_to_mm'] : null,
                default => null,
            };
            if ($data['units'] === 'custom' && !$scale) {
                throw new OrthodonticsException('Enter the scale to millimetres for custom units.', 422, 'invalid_units');
            }
            $meta['units'] = $data['units'];
            $meta['scale_to_mm'] = $scale;
            $meta['units_confirmed'] = $scale !== null;
            $meta['units_confirmed_by'] = $scale !== null ? Auth::id() : null;
            $meta['units_confirmed_at'] = $scale !== null ? now()->toIso8601String() : null;
        }

        $before = $record->meta;
        $record->meta = $meta;
        $record->save();
        $this->audit->log($c->id, 'file.updated', $record, ['previous_meta' => $before]);

        return $this->ok(new OrthodonticFileResource($record), 'File updated');
    }

    public function destroy(int $case, int $file): JsonResponse
    {
        $c = $this->caseFor($case, 'manage');
        $record = OrthodonticFile::where('case_id', $c->id)->find($file);
        if (!$record) {
            throw OrthodonticsException::notFound('File');
        }
        // Soft delete only: the original stays on disk and in the database.
        $record->delete();
        $this->audit->log($c->id, 'file.deleted', $record);

        return $this->ok(null, 'File removed');
    }
}
