<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for public patient profile.
 * 
 * This resource is used for public access via QR code.
 * It excludes sensitive information like financial data, phone, address, etc.
 */
class PublicPatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'age' => $this->age,
            'sex' => $this->sex,
            'sex_label' => $this->sex === 1 ? 'Male' : ($this->sex === 2 ? 'Female' : null),
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'systemic_conditions' => $this->systemic_conditions,
            'tooth_details' => $this->tooth_details,
            
            // Clinic information (limited)
            'clinic' => $this->whenLoaded('clinic', function () {
                return [
                    'name' => $this->clinic->name,
                    'address' => $this->clinic->address,
                    'phone' => $this->clinic->phone,
                ];
            }),
            
            // Doctor information (limited)
            'doctor' => $this->whenLoaded('doctor', function () {
                return [
                    'name' => $this->doctor->name,
                ];
            }),
            
            // Cases (limited information)
            'cases' => $this->whenLoaded('cases', function () {
                return $this->cases->map(function ($case) {
                    return [
                        'id' => $case->id,
                        'tooth_num' => $case->tooth_num,
                        'notes' => $case->notes,
                        'category' => $case->category ? [
                            'name' => $case->category->name,
                            'name_en' => $case->category->name_en,
                            'name_ar' => $case->category->name_ar,
                        ] : null,
                        'status' => $case->status ? [
                            'name_en' => $case->status->name_en,
                            'name_ar' => $case->status->name_ar,
                            'color' => $case->status->color,
                        ] : null,
                        'created_at' => $case->created_at?->format('Y-m-d'),
                    ];
                });
            }),
            
            // Images
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->url,
                        'type' => $image->type,
                        'alt_text' => $image->alt_text,
                        'created_at' => $image->created_at?->format('Y-m-d'),
                    ];
                });
            }),
            
            // Upcoming reservations only
            'upcoming_reservations' => $this->whenLoaded('reservations', function () {
                return $this->reservations->map(function ($reservation) {
                    return [
                        'date' => $reservation->date,
                        'time' => $reservation->time,
                        'status' => $reservation->status,
                        'notes' => $reservation->notes,
                        'doctor' => $reservation->doctor ? [
                            'name' => $reservation->doctor->name,
                        ] : null,
                    ];
                });
            }),
            
            // Counts
            'cases_count' => $this->cases->count(),
            'images_count' => $this->images->count(),
            
            'member_since' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
