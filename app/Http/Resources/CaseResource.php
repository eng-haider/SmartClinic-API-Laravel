<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseResource extends JsonResource
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
                'phone' => $this->doctor?->phone ?? 'N/A',
            ],
            'clinic' => $this->clinic ? [
                'id' => $this->clinic_id,
                'name' => $this->clinic?->name ?? 'N/A',
            ] : null,
            'category' => [
                'id' => $this->case_categores_id,
                'name_ar' => $this->category?->name_ar ?? 'N/A',
                'name_en' => $this->category?->name_en ?? 'N/A',
            ],
            'status' => [
                'id' => $this->status_id,
                'name_ar' => $this->status?->name_ar ?? 'N/A',
                'name_en' => $this->status?->name_en ?? 'N/A',
                'color' => $this->status?->color ?? null,
            ],
            'notes' => $this->notes,
            'price' => $this->price,
            'tooth_num' => $this->tooth_num,
            'root_stuffing' => $this->root_stuffing,
            'is_paid' => $this->is_paid,
            'payment_status' => $this->is_paid ? 'Paid' : 'Unpaid',
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
