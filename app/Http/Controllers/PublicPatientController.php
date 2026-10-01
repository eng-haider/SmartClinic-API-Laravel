<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicPatientResource;
use App\Models\Patient;
use App\Repositories\ClinicSettingRepository;
use Illuminate\Http\JsonResponse;

class PublicPatientController extends Controller
{
    public function __construct(private ClinicSettingRepository $clinicSettings)
    {
    }

    /**
     * Patient dashboard by opaque public token. Tenant selection happens in
     * middleware; every relation below is therefore scoped to that tenant.
     */
    public function show(string $token): JsonResponse
    {
        $patient = $this->patient($token);
        if (!$patient) {
            return $this->unavailable();
        }

        $patient->load([
            'cases' => fn ($query) => $query
                ->with([
                    'category:id,name,name_en,name_ar',
                    'status:id,name_en,name_ar',
                    'doctor:id,name',
                ])
                ->select('id', 'patient_id', 'doctor_id', 'case_categores_id', 'status_id', 'tooth_num', 'case_date', 'price', 'created_at')
                ->orderByDesc('case_date')
                ->orderByDesc('created_at'),
            'reservations' => fn ($query) => $query
                ->with(['doctor:id,name', 'status:id,name_en,name_ar'])
                ->select('id', 'patient_id', 'doctor_id', 'status_id', 'reservation_start_date', 'reservation_from_time')
                ->whereDate('reservation_start_date', '>=', now()->toDateString())
                ->orderBy('reservation_start_date')
                ->orderBy('reservation_from_time'),
            'bills' => fn ($query) => $query
                ->select('id', 'patient_id', 'price', 'bill_date', 'created_at')
                ->orderByDesc('bill_date')
                ->orderByDesc('created_at')
                ->limit(20),
        ]);

        $total = (int) $patient->cases->sum(fn ($case) => max(0, (int) ($case->price ?? 0)));
        // The history is deliberately capped for a one-hand mobile view, but
        // the summary itself must always use the complete payment total.
        $paid = (int) $patient->bills()->sum('price');
        $paid = min($paid, $total);

        $finance = [
            'total' => $total,
            'paid' => $paid,
            'remaining' => max(0, $total - $paid),
            'payments' => $patient->bills->map(fn ($bill) => [
                'date' => ($bill->bill_date ?? $bill->created_at)?->format('Y-m-d'),
                'amount' => (int) $bill->price,
            ])->values()->all(),
        ];

        $clinic = $this->clinicSettings->publicIdentity();
        if (function_exists('tenant') && tenant()?->public_slug) {
            $clinic['public_website_path'] = '/clinic/' . tenant()->public_slug;
        }

        return $this->privateResponse([
            'success' => true,
            'data' => new PublicPatientResource($patient, $clinic, $finance),
        ]);
    }

    /**
     * Compatibility endpoint for older clients. It returns the same safe
     * treatment representation, without internal identifiers or notes.
     */
    public function cases(string $token): JsonResponse
    {
        $patient = $this->patient($token);
        if (!$patient) {
            return $this->unavailable();
        }

        $patient->load(['cases' => fn ($query) => $query
            ->with(['category:id,name,name_en,name_ar', 'status:id,name_en,name_ar', 'doctor:id,name'])
            ->select('id', 'patient_id', 'doctor_id', 'case_categores_id', 'status_id', 'tooth_num', 'case_date', 'created_at')
            ->orderByDesc('case_date')->orderByDesc('created_at')]);

        $data = (new PublicPatientResource($patient))->toArray(request())['treatment_timeline'];
        return $this->privateResponse(['success' => true, 'data' => $data]);
    }

    /**
     * Images remain unavailable until the data model carries an explicit
     * patient-visible flag and a token-scoped file delivery mechanism.
     */
    public function images(string $token): JsonResponse
    {
        return $this->patient($token)
            ? $this->privateResponse(['success' => true, 'data' => []])
            : $this->unavailable();
    }

    public function reservations(string $token): JsonResponse
    {
        $patient = $this->patient($token);
        if (!$patient) {
            return $this->unavailable();
        }

        $patient->load(['reservations' => fn ($query) => $query
            ->with(['doctor:id,name', 'status:id,name_en,name_ar'])
            ->select('id', 'patient_id', 'doctor_id', 'status_id', 'reservation_start_date', 'reservation_from_time')
            ->whereDate('reservation_start_date', '>=', now()->toDateString())
            ->orderBy('reservation_start_date')->orderBy('reservation_from_time')]);

        $data = (new PublicPatientResource($patient))->toArray(request())['appointments'];
        return $this->privateResponse(['success' => true, 'data' => $data]);
    }

    private function patient(string $token): ?Patient
    {
        return Patient::findByPublicToken($token);
    }

    private function unavailable(): JsonResponse
    {
        // Do not reveal whether a token existed, was disabled, or belongs to
        // another tenant.
        return $this->privateResponse([
            'success' => false,
            'message' => 'This patient link is unavailable.',
        ], 404);
    }

    /** @param array<string, mixed> $payload */
    private function privateResponse(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
