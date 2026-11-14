<?php

namespace App\Modules\Orthodontics\Cbct;

use App\Models\CaseModel;
use App\Modules\Orthodontics\Models\OrthodonticCbctInstance;
use App\Modules\Orthodontics\Models\OrthodonticCbctStudy;
use App\Modules\Orthodontics\Services\OrthodonticsAudit;
use App\Modules\Orthodontics\Services\OrthodonticsException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CBCT study lifecycle: create (uploading) -> add instances in batches ->
 * complete (ready). Uploading in batches keeps every request within the PHP
 * upload limits and lets the browser show real progress.
 */
class CbctService
{
    public function __construct(private OrthodonticsAudit $audit)
    {
    }

    public function driver(?string $name = null): CbctStorageDriver
    {
        return match ($name ?? config('orthodontics.cbct.driver', 'local')) {
            'orthanc' => app(OrthancCbctStorage::class),
            default => app(LocalCbctStorage::class),
        };
    }

    public function create(CaseModel $case, ?string $description): OrthodonticCbctStudy
    {
        $study = OrthodonticCbctStudy::create([
            'case_id' => $case->id,
            'driver' => $this->driver()->name(),
            'status' => OrthodonticCbctStudy::STATUS_UPLOADING,
            'description' => $description,
            'uploaded_by' => Auth::id(),
        ]);
        $this->audit->log($case->id, 'cbct.created', $study);

        return $study;
    }

    /**
     * @param  UploadedFile[]  $files
     * @return array{accepted: int, skipped: array}
     */
    public function addInstances(OrthodonticCbctStudy $study, array $files): array
    {
        if ($study->status !== OrthodonticCbctStudy::STATUS_UPLOADING) {
            throw new OrthodonticsException('This study is no longer accepting files.', 409, 'study_closed');
        }
        $driver = $this->driver($study->driver);
        $max = (int) config('orthodontics.cbct.max_instances', 5000);
        $accepted = 0;
        $skipped = [];

        foreach ($files as $file) {
            $name = $file->getClientOriginalName();
            try {
                $header = DicomHeaderParser::parseFile($file->getRealPath());
            } catch (OrthodonticsException $e) {
                $skipped[] = ['file' => $name, 'reason' => $e->getMessage()];
                continue;
            }

            // One study per record: refuse files from another study (another
            // scan, or another patient's files mixed into the folder).
            if ($study->study_instance_uid && $header['study_instance_uid'] !== $study->study_instance_uid) {
                $skipped[] = ['file' => $name, 'reason' => 'Belongs to a different DICOM study.'];
                continue;
            }
            if (OrthodonticCbctInstance::where('study_id', $study->id)->where('sop_instance_uid', $header['sop_instance_uid'])->exists()) {
                $skipped[] = ['file' => $name, 'reason' => 'Duplicate instance (already uploaded).'];
                continue;
            }
            if ($study->instances_count >= $max) {
                $skipped[] = ['file' => $name, 'reason' => 'Instance limit reached for this study.'];
                continue;
            }
            if (empty($header['rows']) || empty($header['columns'])) {
                $skipped[] = ['file' => $name, 'reason' => 'Not an image instance (no rows/columns).'];
                continue;
            }

            $stored = $driver->store($study, $file->getRealPath(), $header);

            DB::transaction(function () use ($study, $header, $stored, $file) {
                OrthodonticCbctInstance::create([
                    'study_id' => $study->id,
                    'sop_instance_uid' => $header['sop_instance_uid'],
                    'series_instance_uid' => $header['series_instance_uid'] ?? null,
                    'instance_number' => $header['instance_number'] ?? null,
                    'frames' => $header['frames'] ?? 1,
                    'rows' => $header['rows'] ?? null,
                    'columns' => $header['columns'] ?? null,
                    'slice_position' => $header['slice_position'] ?? null,
                    'path' => $stored['path'] ?? null,
                    'orthanc_instance_id' => $stored['orthanc_instance_id'] ?? null,
                    'size' => (int) $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                ]);

                $study->fill([
                    'study_instance_uid' => $study->study_instance_uid ?: $header['study_instance_uid'],
                    'orthanc_study_id' => $study->orthanc_study_id ?: ($stored['orthanc_study_id'] ?? null),
                    'modality' => $study->modality ?: ($header['modality'] ?? null),
                    'study_date' => $study->study_date ?: $this->date($header['study_date'] ?? null),
                    'description' => $study->description ?: ($header['study_description'] ?? null),
                    'instances_count' => $study->instances_count + 1,
                    'total_size' => $study->total_size + (int) $file->getSize(),
                ])->save();
            });
            $accepted++;
        }

        return ['accepted' => $accepted, 'skipped' => $skipped];
    }

    public function complete(OrthodonticCbctStudy $study): OrthodonticCbctStudy
    {
        if ($study->instances_count < 1) {
            throw new OrthodonticsException('Upload at least one DICOM file before finishing.', 422, 'study_empty');
        }

        $series = OrthodonticCbctInstance::where('study_id', $study->id)
            ->selectRaw('series_instance_uid, count(*) as instances, sum(frames) as frames, max(`rows`) as max_rows, max(`columns`) as max_columns')
            ->groupBy('series_instance_uid')
            ->get()
            ->map(fn ($s) => [
                'series_instance_uid' => $s->series_instance_uid,
                'instances' => (int) $s->instances,
                'frames' => (int) $s->frames,
                'rows' => (int) $s->max_rows,
                'columns' => (int) $s->max_columns,
            ])->values()->all();

        $study->fill([
            'status' => OrthodonticCbctStudy::STATUS_READY,
            'series' => $series,
            'completed_at' => now(),
            'error' => null,
        ])->save();
        $this->audit->log($study->case_id, 'cbct.completed', $study, ['instances' => $study->instances_count]);

        return $study;
    }

    /**
     * Instances in viewing order (per series, along the slice normal).
     */
    public function orderedInstances(OrthodonticCbctStudy $study)
    {
        return OrthodonticCbctInstance::where('study_id', $study->id)
            ->orderBy('series_instance_uid')
            ->orderByRaw('slice_position is null')
            ->orderBy('slice_position')
            ->orderBy('instance_number')
            ->orderBy('id')
            ->get(['id', 'study_id', 'sop_instance_uid', 'series_instance_uid', 'instance_number', 'frames', 'rows', 'columns', 'slice_position', 'size']);
    }

    /**
     * Mark a study failed and remove its partial files. Only for studies that
     * never completed - a ready study is never discarded automatically.
     */
    public function abandon(OrthodonticCbctStudy $study, string $reason): void
    {
        if ($study->status === OrthodonticCbctStudy::STATUS_READY) {
            return;
        }
        $this->driver($study->driver)->discard($study);
        OrthodonticCbctInstance::where('study_id', $study->id)->delete();
        $study->fill(['status' => OrthodonticCbctStudy::STATUS_FAILED, 'error' => $reason, 'instances_count' => 0, 'total_size' => 0])->save();
        $this->audit->log($study->case_id, 'cbct.abandoned', $study, ['reason' => $reason]);
    }

    private function date(?string $dicomDate): ?string
    {
        return ($dicomDate && preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dicomDate, $m)) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;
    }
}
