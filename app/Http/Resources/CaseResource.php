<?php

namespace App\Http\Resources;

use App\Services\SpecialtyManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Dynamically includes specialty-specific fields via SpecialtyManager.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'patient' => $this->when($this->relationLoaded('patient'), [
                'id' => $this->patient_id,
                'name' => $this->patient?->name ?? 'N/A',
                'phone' => $this->patient?->phone ?? 'N/A',
            ]),
            'doctor' => $this->when($this->relationLoaded('doctor'), [
                'id' => $this->doctor_id,
                'name' => $this->doctor?->name ?? 'N/A',
                'phone' => $this->doctor?->phone ?? 'N/A',
            ]),
            'category' => $this->when($this->relationLoaded('category'), [
                'id' => $this->case_categores_id,
                'name' => $this->category?->name ?? 'N/A',
            ]),
            'status' => $this->when($this->relationLoaded('status'), [
                'id' => $this->status_id,
                'name_ar' => $this->status?->name_ar ?? 'N/A',
                'name_en' => $this->status?->name_en ?? 'N/A',
                'color' => $this->status?->color ?? null,
            ]),
            'notes' => $this->notes,
            'price' => $this->price,
            'is_paid' => $this->is_paid,
            'payment_status' => $this->is_paid ? 'Paid' : 'Unpaid',
            'case_date' => $this->case_date?->format('Y-m-d H:i:s'),
            'bills' => $this->when($this->relationLoaded('bills'), function () {
                return $this->bills->map(function ($bill) {
                    return [
                        'id' => $bill->id,
                        'price' => $bill->price,
                        'is_paid' => $bill->is_paid,
                        'payment_status' => $bill->is_paid ? 'Paid' : 'Unpaid',
                        'use_credit' => $bill->use_credit,
                        'credit_usage' => $bill->use_credit ? 'Used Credit' : 'No Credit',
                        'created_at' => $bill->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $bill->updated_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];

        // Merge specialty-specific fields
        // Dental: tooth_num, root_stuffing
        // Ophthalmology: eye_side, visual_acuity, iop, etc.
        return array_merge($data, SpecialtyManager::handler()->resourceFields($this->resource));
    }
}
