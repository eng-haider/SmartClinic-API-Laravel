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
            'tooth_details' => $this->tooth_details,
            
            // Public profile fields
            'public_token' => $this->public_token,
            'is_public_profile_enabled' => $this->is_public_profile_enabled,
            'public_profile_url' => $this->public_profile_url,
            
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            
            // Relationships (when loaded)
            'doctor' => $this->whenLoaded('doctor'),
            'fromWhereCome' => $this->whenLoaded('fromWhereCome'),
            'cases' => $this->whenLoaded('cases'),
            'recipes' => $this->whenLoaded('recipes'),
            'reservations' => $this->whenLoaded('reservations'),
            'bills' => $this->whenLoaded('bills'),
            'notes' => $this->whenLoaded('notes'),
            'images' => $this->whenLoaded('images'),
            'creator' => $this->whenLoaded('creator'),
            'updator' => $this->whenLoaded('updator'),
            
            // Counts (when loaded with withCount)
            'cases_count' => $this->when(isset($this->cases_count), $this->cases_count),
            'recipes_count' => $this->when(isset($this->recipes_count), $this->recipes_count),
            'reservations_count' => $this->when(isset($this->reservations_count), $this->reservations_count),
            'bills_count' => $this->when(isset($this->bills_count), $this->bills_count),
            'notes_count' => $this->when(isset($this->notes_count), $this->notes_count),
            'images_count' => $this->when(isset($this->images_count), $this->images_count),
        ];
    }
}
