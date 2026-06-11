<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'name'                       => $this->name,
            'unit'                       => $this->unit,
            'quantity'                   => $this->quantity,
            'min_quantity'               => $this->min_quantity,
            'is_low'                     => $this->is_low,
            'cost_price'                 => $this->cost_price,
            'clinic_expense_category_id' => $this->clinic_expense_category_id,
            'notes'                      => $this->notes,
            'creator_id'                 => $this->creator_id,
            'updator_id'                 => $this->updator_id,
            'created_at'                 => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'                 => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at'                 => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relationships (loaded conditionally)
            'category'     => new ClinicExpenseCategoryResource($this->whenLoaded('category')),
            'creator'      => new UserResource($this->whenLoaded('creator')),
            'updator'      => new UserResource($this->whenLoaded('updator')),
            'transactions' => WarehouseTransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }
}
