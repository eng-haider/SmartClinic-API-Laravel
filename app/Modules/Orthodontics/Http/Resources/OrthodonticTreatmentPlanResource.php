<?php

namespace App\Modules\Orthodontics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrthodonticTreatmentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->only($this->resource->getFillable());
        $data['id'] = $this->id;
        $data['start_date'] = $this->start_date?->toDateString();
        $data['observations'] = $this->observations ?? [];
        $data['stages'] = $this->stages ?? [];
        $data['updated_at'] = $this->updated_at?->toIso8601String();

        return $data;
    }
}
