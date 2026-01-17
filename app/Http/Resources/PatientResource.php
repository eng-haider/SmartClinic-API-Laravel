<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
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
            'age' => $this->age,
            'doctor_id' => $this->doctor_id,
            'clinics_id' => $this->clinics_id,
            'phone' => $this->phone,
            'systemic_conditions' => $this->systemic_conditions,
            'sex' => $this->sex,
            'sex_label' => $this->sex === 1 ? 'Male' : ($this->sex === 2 ? 'Female' : null),
            'address' => $this->address,
            'notes' => $this->notes,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'rx_id' => $this->rx_id,
            'note' => $this->note,
            'from_where_come_id' => $this->from_where_come_id,
            'identifier' => $this->identifier,
            'credit_balance' => $this->credit_balance,
            'credit_balance_add_at' => $this->credit_balance_add_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            
            // Relationships (when loaded)
            'doctor' => $this->whenLoaded('doctor'),
            'clinic' => $this->whenLoaded('clinic'),
            'fromWhereCome' => $this->whenLoaded('fromWhereCome'),
            'cases' => $this->whenLoaded('cases'),
            'reservations' => $this->whenLoaded('reservations'),
            'bills' => $this->whenLoaded('bills'),
            'notes' => $this->whenLoaded('notes'),
        ];
    }
}
