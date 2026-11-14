<?php

namespace App\Modules\Orthodontics\Services;

use App\Models\CaseModel;
use App\Modules\Orthodontics\Analysis\CastAnalysisCalculator;
use App\Modules\Orthodontics\Analysis\CbctMeasurementCalculator;
use App\Modules\Orthodontics\Analysis\CephalometricCalculator;
use App\Modules\Orthodontics\Analysis\FaceAnalysisCalculator;
use App\Modules\Orthodontics\Models\OrthodonticAnalysis;
use App\Modules\Orthodontics\Models\OrthodonticAnalysisRevision;
use App\Modules\Orthodontics\Models\OrthodonticCbctStudy;
use App\Modules\Orthodontics\Models\OrthodonticFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Saves analyses. Results are always recomputed on the server from what the
 * doctor entered - a client cannot store a measurement it did not trace.
 * Every save writes a new revision; nothing is overwritten in place.
 */
class AnalysisService
{
    public function __construct(private OrthodonticsAudit $audit)
    {
    }

    public function compute(string $type, array $data): array
    {
        return match ($type) {
            'ceph' => CephalometricCalculator::analyze($data),
            'cast' => CastAnalysisCalculator::analyze($data, config('orthodontics.moyers_table')),
            'face' => FaceAnalysisCalculator::analyze($data),
            'cbct' => CbctMeasurementCalculator::analyze($data),
        };
    }

    public function create(CaseModel $case, array $input): OrthodonticAnalysis
    {
        $type = $input['type'];
        $data = $input['data'] ?? [];
        $fileId = $this->ownedFileId($case, $input['file_id'] ?? null, $type);
        $studyId = $this->ownedStudyId($case, $input['cbct_study_id'] ?? null);
        $this->assertModelFiles($case, $type, $data);

        return DB::transaction(function () use ($case, $input, $type, $data, $fileId, $studyId) {
            $analysis = OrthodonticAnalysis::create([
                'case_id' => $case->id,
                'type' => $type,
                'title' => $input['title'] ?? null,
                'file_id' => $fileId,
                'cbct_study_id' => $studyId,
                'analysis_date' => $input['analysis_date'] ?? now()->toDateString(),
                'status' => OrthodonticAnalysis::STATUS_DRAFT,
                'revision' => 1,
                'data' => $data,
                'results' => $this->compute($type, $data),
                'notes' => $input['notes'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $this->snapshot($analysis);
            $this->audit->log($case->id, 'analysis.created', $analysis, ['type' => $type]);

            return $analysis;
        });
    }

    /**
     * @param  int|null  $expectedRevision  the revision the client edited; a
     *                                      mismatch means someone else saved in
     *                                      between, and is refused (409).
     */
    public function update(OrthodonticAnalysis $analysis, array $input, ?int $expectedRevision): OrthodonticAnalysis
    {
        if ($expectedRevision !== null && $expectedRevision !== $analysis->revision) {
            throw OrthodonticsException::conflict($analysis->revision);
        }

        $case = $analysis->case;

        return DB::transaction(function () use ($analysis, $input, $case) {
            $changes = [];
            if (array_key_exists('data', $input)) {
                $this->assertModelFiles($case, $analysis->type, $input['data'] ?? []);
                $changes['data'] = $input['data'] ?? [];
                $changes['results'] = $this->compute($analysis->type, $changes['data']);
            }
            foreach (['title', 'notes', 'analysis_date'] as $field) {
                if (array_key_exists($field, $input)) {
                    $changes[$field] = $input[$field];
                }
            }
            if (array_key_exists('file_id', $input)) {
                $changes['file_id'] = $this->ownedFileId($case, $input['file_id'], $analysis->type);
            }
            if (array_key_exists('cbct_study_id', $input)) {
                $changes['cbct_study_id'] = $this->ownedStudyId($case, $input['cbct_study_id']);
            }

            // Changing what was reviewed needs a fresh review.
            $reopened = false;
            if ($analysis->isReviewed() && (isset($changes['data']) || isset($changes['file_id']))) {
                $changes['status'] = OrthodonticAnalysis::STATUS_DRAFT;
                $changes['reviewed_by'] = null;
                $changes['reviewed_at'] = null;
                $reopened = true;
            }

            $analysis->fill($changes + [
                'revision' => $analysis->revision + 1,
                'updated_by' => Auth::id(),
            ])->save();
            $this->snapshot($analysis);
            $this->audit->log($analysis->case_id, 'analysis.updated', $analysis, ['revision' => $analysis->revision, 'review_reset' => $reopened]);

            return $analysis;
        });
    }

    /**
     * Doctor review: required before an analysis goes into a final report.
     */
    public function review(OrthodonticAnalysis $analysis, bool $reviewed): OrthodonticAnalysis
    {
        return DB::transaction(function () use ($analysis, $reviewed) {
            $analysis->fill([
                'status' => $reviewed ? OrthodonticAnalysis::STATUS_REVIEWED : OrthodonticAnalysis::STATUS_DRAFT,
                'reviewed_by' => $reviewed ? Auth::id() : null,
                'reviewed_at' => $reviewed ? now() : null,
                'revision' => $analysis->revision + 1,
                'updated_by' => Auth::id(),
            ])->save();
            $this->snapshot($analysis);
            $this->audit->log($analysis->case_id, $reviewed ? 'analysis.reviewed' : 'analysis.review_withdrawn', $analysis);

            return $analysis;
        });
    }

    public function delete(OrthodonticAnalysis $analysis): void
    {
        // Soft delete: the analysis and its revisions stay in the database.
        $analysis->delete();
        $this->audit->log($analysis->case_id, 'analysis.deleted', $analysis);
    }

    private function snapshot(OrthodonticAnalysis $analysis): void
    {
        OrthodonticAnalysisRevision::create([
            'analysis_id' => $analysis->id,
            'revision' => $analysis->revision,
            'status' => $analysis->status,
            'data' => $analysis->data,
            'results' => $analysis->results,
            'notes' => $analysis->notes,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * A referenced file must belong to the same case (and suit the analysis).
     */
    private function ownedFileId(CaseModel $case, $fileId, string $type): ?int
    {
        if ($fileId === null || $fileId === '') {
            return null;
        }
        $file = OrthodonticFile::where('case_id', $case->id)->find((int) $fileId);
        if (!$file) {
            throw new OrthodonticsException('The selected file does not belong to this case.', 422, 'invalid_file');
        }
        $expected = match ($type) {
            'ceph' => ['ceph_xray'],
            'face' => ['face_frontal', 'face_smile', 'face_profile'],
            'cast' => OrthodonticFile::MODEL_KINDS,
            default => null,
        };
        if ($expected && !in_array($file->kind, $expected, true)) {
            throw new OrthodonticsException('This file type cannot be used for this analysis.', 422, 'invalid_file');
        }

        return $file->id;
    }

    /**
     * A cast analysis lists its upper/lower/combined models in
     * data.model_file_ids; each must be a 3D model of this same case.
     */
    private function assertModelFiles(CaseModel $case, string $type, array $data): void
    {
        if ($type !== 'cast' || empty($data['model_file_ids']) || !is_array($data['model_file_ids'])) {
            return;
        }
        $ids = array_values(array_filter(array_map('intval', $data['model_file_ids'])));
        $owned = OrthodonticFile::where('case_id', $case->id)->whereIn('kind', OrthodonticFile::MODEL_KINDS)->whereIn('id', $ids)->count();
        if ($owned !== count(array_unique($ids))) {
            throw new OrthodonticsException('A selected 3D model does not belong to this case.', 422, 'invalid_file');
        }
    }

    private function ownedStudyId(CaseModel $case, $studyId): ?int
    {
        if ($studyId === null || $studyId === '') {
            return null;
        }
        $study = OrthodonticCbctStudy::where('case_id', $case->id)->find((int) $studyId);
        if (!$study) {
            throw new OrthodonticsException('The selected CBCT study does not belong to this case.', 422, 'invalid_study');
        }

        return $study->id;
    }
}
