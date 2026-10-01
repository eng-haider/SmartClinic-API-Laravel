<?php

namespace App\Http\Resources;

use App\Models\CaseModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public portal is a bearer-link experience, not a staff chart. Keep this
 * representation intentionally small: no database IDs, notes, identifiers,
 * raw image links, or clinician-only prescription/document content.
 */
class PublicPatientResource extends JsonResource
{
    /** @param array<string, mixed> $clinic */
    public function __construct($resource, private array $clinic = [], private array $finance = [])
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $cases = $this->resource->relationLoaded('cases') ? $this->resource->getRelation('cases') : collect();
        $reservations = $this->resource->relationLoaded('reservations')
            ? $this->resource->getRelation('reservations')
            : collect();
        $activeCase = $cases->first(fn (CaseModel $case) => (int) $case->status_id !== CaseModel::COMPLETED_STATUS_ID);

        return [
            // A greeting needs a name, but nothing else from the patient identity.
            'name' => $this->name,
            'clinic' => $this->clinic ?: null,
            'next_appointment' => $this->appointment($reservations->first()),
            'appointments' => $reservations
                ->map(fn ($reservation) => $this->appointment($reservation))
                ->filter()
                ->values(),
            'current_treatment' => $activeCase ? $this->treatment($activeCase) : null,
            'treatment_timeline' => $cases
                ->map(fn (CaseModel $case) => $this->treatment($case))
                ->values(),
            'financial_summary' => [
                'total' => (int) ($this->finance['total'] ?? 0),
                'paid' => (int) ($this->finance['paid'] ?? 0),
                'remaining' => (int) ($this->finance['remaining'] ?? 0),
                'currency' => $this->clinic['currency'] ?? 'IQD',
            ],
            'payment_history' => collect($this->finance['payments'] ?? [])->values(),
            // There is no explicit patient-visibility field on recipes or
            // images yet. Returning an empty collection is safer than guessing.
            'prescriptions' => [],
            'documents' => [],
        ];
    }

    private function appointment($reservation): ?array
    {
        if (!$reservation) {
            return null;
        }

        return [
            'date' => $reservation->reservation_start_date?->format('Y-m-d'),
            'time' => $reservation->reservation_from_time,
            'doctor' => $reservation->doctor?->name,
            'status' => $reservation->status ? [
                'name_en' => $reservation->status->name_en,
                'name_ar' => $reservation->status->name_ar,
            ] : null,
        ];
    }

    private function treatment(CaseModel $case): array
    {
        return [
            'tooth' => $case->tooth_num,
            'category' => $case->category ? [
                'name' => $case->category->name,
                'name_en' => $case->category->name_en,
                'name_ar' => $case->category->name_ar,
            ] : null,
            'status' => $case->status ? [
                'name_en' => $case->status->name_en,
                'name_ar' => $case->status->name_ar,
            ] : null,
            'doctor' => $case->doctor?->name,
            'date' => ($case->case_date ?? $case->created_at)?->format('Y-m-d'),
        ];
    }
}
