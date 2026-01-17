<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
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
            'patient' => [
                'id' => $this->patient_id,
                'name' => $this->patient?->full_name ?? 'N/A',
                'phone' => $this->patient?->phone ?? 'N/A',
            ],
            'doctor' => [
                'id' => $this->doctor_id,
                'name' => $this->doctor?->name ?? 'N/A',
            ],
            'clinic' => $this->clinic ? [
                'id' => $this->clinics_id,
                'name' => $this->clinic?->name ?? 'N/A',
            ] : null,
            'billable' => $this->billable ? [
                'id' => $this->billable_id,
                'type' => $this->billable_type,
            ] : null,
            'price' => $this->price,
            'is_paid' => $this->is_paid,
            'payment_status' => $this->payment_status,
            'use_credit' => $this->use_credit,
            'credit_usage' => $this->credit_usage,
            'creator' => $this->creator ? [
                'id' => $this->creator_id,
                'name' => $this->creator?->name ?? 'N/A',
            ] : null,
            'updator' => $this->updator ? [
                'id' => $this->updator_id,
                'name' => $this->updator?->name ?? 'N/A',
            ] : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
