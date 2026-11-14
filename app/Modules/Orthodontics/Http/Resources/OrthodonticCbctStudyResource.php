<?php

namespace App\Modules\Orthodontics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrthodonticCbctStudyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'driver' => $this->driver,
            'status' => $this->status,
            'description' => $this->description,
            'study_instance_uid' => $this->study_instance_uid,
            'modality' => $this->modality,
            'study_date' => $this->study_date?->toDateString(),
            'instances_count' => $this->instances_count,
            'total_size' => $this->total_size,
            'series' => $this->series ?? [],
            'viewer_state' => $this->viewer_state ?? (object) [],
            'error' => $this->error,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
