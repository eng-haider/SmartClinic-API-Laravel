<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'warehouse_item_id' => $this->warehouse_item_id,
            'type'            => $this->type,
            'quantity_change' => $this->quantity_change,
            'unit_cost'       => $this->unit_cost,
            'source_type'     => $this->source_type,
            'source_id'       => $this->source_id,
            'doctor_id'       => $this->doctor_id,
            'notes'           => $this->notes,
            'doctor'          => new UserResource($this->whenLoaded('doctor')),
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
