<?php

namespace App\Modules\Orthodontics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrthodonticAnalysisResource extends JsonResource
{
    private bool $includeData = true;

    /** Lists and reports leave the (large) traced data out. */
    public function withoutData(): static
    {
        $this->includeData = false;

        return $this;
    }

    public static function summaries($analyses)
    {
        return collect($analyses)->map(fn ($a) => (new static($a))->withoutData())->values();
    }

    public function toArray(Request $request): array
    {
        $full = $this->includeData;

        return [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'type' => $this->type,
            'title' => $this->title,
            'file_id' => $this->file_id,
            'cbct_study_id' => $this->cbct_study_id,
            'analysis_date' => $this->analysis_date?->toDateString(),
            'status' => $this->status,
            'revision' => $this->revision,
            'data' => $this->when($full, fn () => $this->data ?? (object) []),
            'results' => $this->results ?? (object) [],
            'notes' => $this->notes,
            'created_by' => $this->creator?->only(['id', 'name']),
            'reviewed_by' => $this->reviewer?->only(['id', 'name']),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
