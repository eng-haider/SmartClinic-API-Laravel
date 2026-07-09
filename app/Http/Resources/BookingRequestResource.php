<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingRequestResource extends JsonResource
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
            'phone' => $this->phone,
            'preferred_date' => $this->preferred_date?->format('Y-m-d'),
            'preferred_time' => $this->preferred_time,
            'note' => $this->note,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,

            'patient_id' => $this->patient_id,
            'reservation_id' => $this->reservation_id,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relationships (when loaded)
            'patient' => $this->whenLoaded('patient'),
            'reservation' => $this->whenLoaded('reservation'),
            'reviewer' => $this->when($this->relationLoaded('reviewer'), function () {
                return $this->reviewer ? [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                ] : null;
            }),
        ];
    }
}
