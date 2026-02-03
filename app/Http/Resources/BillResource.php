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
                'name' => $this->patient?->name ?? 'N/A',
                'phone' => $this->patient?->phone ?? 'N/A',
            ],
            'doctor' => [
                'id' => $this->doctor_id,
                'name' => $this->doctor?->name ?? 'N/A',
            ],
            'billable' => $this->when($this->billable, function () {
                // Check if billable type is CaseModel (handle both legacy and new format) and load full data if relations are loaded
                $isCaseModel = in_array($this->billable_type, ['App\\Models\\Case', 'App\\Models\\CaseModel', 'Case', 'CaseModel']);
                if ($isCaseModel && $this->relationLoaded('billable')) {
                    return [
                        'id' => $this->billable->id,
                        'type' => 'App\\Models\\Case',
                        'patient_id' => $this->billable->patient_id ?? null,
                        'doctor_id' => $this->billable->doctor_id ?? null,
                        'case_categores_id' => $this->billable->case_categores_id ?? null,
                        'status_id' => $this->billable->status_id ?? null,
                        'price' => $this->billable->price ?? null,
                        'notes' => $this->billable->notes ?? null,
                        'tooth_num' => $this->billable->tooth_num ?? null,
                        'is_paid' => $this->billable->is_paid ?? null,
                        'patient' => $this->billable->relationLoaded('patient') ? [
                            'id' => $this->billable->patient->id ?? null,
                            'name' => $this->billable->patient->name ?? 'N/A',
                            'phone' => $this->billable->patient->phone ?? 'N/A',
                        ] : null,
                        'doctor' => $this->billable->relationLoaded('doctor') ? [
                            'id' => $this->billable->doctor->id ?? null,
                            'name' => $this->billable->doctor->name ?? 'N/A',
                        ] : null,
                        'category' => $this->billable->relationLoaded('category') ? [
                            'id' => $this->billable->category->id ?? null,
                            'name' => $this->billable->category->name ?? 'N/A',
                            'order' => $this->billable->category->order ?? null,
                            'item_cost' => $this->billable->category->item_cost ?? null,
                            'created_at' => $this->billable->category->created_at?->format('Y-m-d H:i:s'),
                            'updated_at' => $this->billable->category->updated_at?->format('Y-m-d H:i:s'),
                        ] : null,
                        'status' => $this->billable->relationLoaded('status') ? [
                            'id' => $this->billable->status->id ?? null,
                            'name' => $this->billable->status->name ?? 'N/A',
                        ] : null,
                    ];
                }
                // Default minimal data
                return [
                    'id' => $this->billable_id,
                    'type' => $this->billable_type,
                ];
            }),
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
