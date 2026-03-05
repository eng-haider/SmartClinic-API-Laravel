<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
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
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'clinics_id' => $this->clinics_id,
            'status_id' => $this->status_id,
            'notes' => $this->notes,
            'reservation_start_date' => $this->reservation_start_date?->format('Y-m-d'),
            'reservation_end_date' => $this->reservation_end_date?->format('Y-m-d'),
            'reservation_from_time' => $this->reservation_from_time,
            'reservation_to_time' => $this->reservation_to_time,
            'is_waiting' => $this->is_waiting,
            'patient' => $this->when($this->relationLoaded('patient'), function () {
                return $this->patient ? [
                    'id' => $this->patient->id,
                    'name' => $this->patient->name,
                    'phone' => $this->patient->phone,
                ] : null;
            }),
            'doctor' => $this->when($this->relationLoaded('doctor'), function () {
                return $this->doctor ? [
                    'id' => $this->doctor->id,
                    'name' => $this->doctor->name,
                ] : null;
            }),
            'status' => $this->when($this->relationLoaded('status'), function () {
                return $this->status ? [
                    'id' => $this->status->id,
                    'name' => $this->status->name,
                ] : null;
            }),
            'creator_id' => $this->creator_id,
            'updator_id' => $this->updator_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
