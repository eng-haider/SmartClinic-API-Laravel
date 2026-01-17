<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicExpenseCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'clinic_id' => $this->clinic_id,
            'is_active' => $this->is_active,
            'creator_id' => $this->creator_id,
            'updator_id' => $this->updator_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            
            // Relationships (loaded conditionally)
            'clinic' => $this->whenLoaded('clinic'),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'updator' => new UserResource($this->whenLoaded('updator')),
            'expenses_count' => $this->whenCounted('expenses'),
        ];
    }
}
