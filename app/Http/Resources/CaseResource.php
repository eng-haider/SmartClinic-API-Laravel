<?php

namespace App\Http\Resources;

use App\Http\Helpers\BillsIsolationHelper;
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
        // Determine if the current doctor should see financial info for this case.
        // When doctor_bills_isolation is ON, a plain doctor only sees price/payment
        // for cases that belong to them. Admins/super-doctors always see everything.
        $showPrice = $this->canViewCasePrice();

        $data = [
            'id' => $this->id,
            'patient' => $this->when($this->relationLoaded('patient'), [
                'id'    => $this->patient_id,
                'name'  => $this->patient?->name ?? 'N/A',
                'phone' => $this->patient?->phone ?? 'N/A',
            ]),
            'doctor' => $this->when($this->relationLoaded('doctor'), [
                'id'    => $this->doctor_id,
                'name'  => $this->doctor?->name ?? 'N/A',
                'phone' => $this->doctor?->phone ?? 'N/A',
            ]),
            'category' => $this->when($this->relationLoaded('category'), [
                'id'   => $this->case_categores_id,
                'name' => $this->category?->name ?? 'N/A',
            ]),
            'status' => $this->when($this->relationLoaded('status'), [
                'id'      => $this->status_id,
                'name_ar' => $this->status?->name_ar ?? 'N/A',
                'name_en' => $this->status?->name_en ?? 'N/A',
                'color'   => $this->status?->color ?? null,
            ]),
            'notes'          => $this->notes,
            'price'          => $showPrice ? $this->price : null,
            'is_paid'        => $showPrice ? $this->is_paid : null,
            'payment_status' => $showPrice ? ($this->is_paid ? 'Paid' : 'Unpaid') : null,
            'case_date'      => $this->case_date?->format('Y-m-d H:i:s'),
            'bills' => $this->when($this->relationLoaded('bills'), function () {
                $bills = $this->bills;

                // If doctor_bills_isolation is ON and user is a plain doctor,
                // only show bills that belong to this doctor within the case.
                $user = \Illuminate\Support\Facades\Auth::user();
                if (
                    $user &&
                    $user->hasRole('doctor') &&
                    !$user->hasRole('clinic_super_doctor') &&
                    !$user->hasRole('secretary') &&
                    !$user->hasRole('super_admin') &&
                    BillsIsolationHelper::isEnabled()
                ) {
                    $bills = $bills->where('doctor_id', $user->id)->values();
                }

                return $bills->map(function ($bill) {
                    return [
                        'id'             => $bill->id,
                        'price'          => $bill->price,
                        'is_paid'        => $bill->is_paid,
                        'payment_status' => $bill->is_paid ? 'Paid' : 'Unpaid',
                        'use_credit'     => $bill->use_credit,
                        'credit_usage'   => $bill->use_credit ? 'Used Credit' : 'No Credit',
                        'created_at'     => $bill->created_at?->format('Y-m-d H:i:s'),
                        'updated_at'     => $bill->updated_at?->format('Y-m-d H:i:s'),
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

    /**
     * Determine if the current authenticated user can view the price/payment info
     * for this specific case.
     *
     * Rules:
     * - Admins / super-doctors / secretaries → always can see price
     * - When isolation is OFF → always can see price
     * - When isolation is ON + doctor viewing their own case → can see price
     * - When isolation is ON + doctor viewing another doctor's case → price is hidden
     */
    private function canViewCasePrice(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        if (!$user) {
            return false;
        }

        // Admins always see price
        if (
            $user->hasRole('clinic_super_doctor') ||
            $user->hasRole('secretary') ||
            $user->hasRole('super_admin')
        ) {
            return true;
        }

        // If isolation is OFF, everyone sees price
        if (!BillsIsolationHelper::isEnabled()) {
            return true;
        }

        // Isolation is ON: doctor can only see price for their own cases
        return (int) $this->doctor_id === (int) $user->id;
    }
}

