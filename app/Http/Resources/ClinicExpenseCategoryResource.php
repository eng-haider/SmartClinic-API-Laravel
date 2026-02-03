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
            'is_active' => $this->is_active,
            'creator_id' => $this->creator_id,
            'updator_id' => $this->updator_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            
            // Expense statistics
            'expenses_count' => $this->whenCounted('expenses'),
            'paid_expenses_count' => $this->when(isset($this->paid_expenses_count), $this->paid_expenses_count ?? 0),
            'unpaid_expenses_count' => $this->when(isset($this->unpaid_expenses_count), $this->unpaid_expenses_count ?? 0),
            'total_expenses_amount' => $this->when(isset($this->total_expenses_amount), (float) ($this->total_expenses_amount ?? 0)),
            'total_paid_amount' => $this->when(isset($this->total_paid_amount), (float) ($this->total_paid_amount ?? 0)),
            'total_unpaid_amount' => $this->when(isset($this->total_unpaid_amount), (float) ($this->total_unpaid_amount ?? 0)),
            
            // Relationships (loaded conditionally)
            'creator' => new UserResource($this->whenLoaded('creator')),
            'updator' => new UserResource($this->whenLoaded('updator')),
            'expenses' => ClinicExpenseResource::collection($this->whenLoaded('expenses')),
        ];
    }
}
