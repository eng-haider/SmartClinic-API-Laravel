<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicExpenseResource extends JsonResource
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
            'quantity' => $this->quantity,
            'clinic_expense_category_id' => $this->clinic_expense_category_id,
            'date' => $this->date?->format('Y-m-d'),
            'price' => $this->price,
            'total' => $this->total,
            'is_paid' => $this->is_paid,
            'doctor_id' => $this->doctor_id,
            'creator_id' => $this->creator_id,
            'updator_id' => $this->updator_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            
            // Relationships (loaded conditionally)
            'category' => new ClinicExpenseCategoryResource($this->whenLoaded('category')),
            'doctor' => new UserResource($this->whenLoaded('doctor')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'updator' => new UserResource($this->whenLoaded('updator')),
        ];
    }
}
