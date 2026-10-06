<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicPatientResource;
use App\Models\Image;
use App\Models\Patient;
use App\Repositories\ClinicSettingRepository;
use Illuminate\Http\JsonResponse;

class PublicPatientController extends Controller
{
    /** Every imageable_type a case image may carry (see the morph map). */
    private const CASE_IMAGE_TYPES = ['Case', 'CaseModel', 'App\\Models\\CaseModel', 'App\\Models\\Case'];
    private const PATIENT_IMAGE_TYPES = ['Patient', 'App\\Models\\Patient'];

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
        $this->attachCaseImages($patient);
        $this->attachPatientPhotos($patient);

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
        $this->attachCaseImages($patient);

        $data = (new PublicPatientResource($patient))->toArray(request())['treatment_timeline'];
        return $this->privateResponse(['success' => true, 'data' => $data]);
    }

    /**
     * Case photos as a flat list. URLs are short-lived signed links, so the
     * response never hands out a permanent path into the tenant file tree.
     */
    public function images(string $token): JsonResponse
    {
        $patient = $this->patient($token);
        if (!$patient) {
            return $this->unavailable();
        }

        $patient->load(['cases' => fn ($query) => $query
            ->select('id', 'patient_id', 'case_date', 'created_at')
            ->orderByDesc('case_date')->orderByDesc('created_at')]);
        $this->attachCaseImages($patient);
        $this->attachPatientPhotos($patient);

        $payload = (new PublicPatientResource($patient))->toArray(request());
        $data = collect($payload['photos'])
            ->concat(collect($payload['treatment_timeline'])->flatMap(fn (array $treatment) => $treatment['images']))
            ->values();
        return $this->privateResponse(['success' => true, 'data' => $data]);
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

    /**
     * One query for every case's images instead of a morphMany per case: the
     * morph relation only matches the canonical 'Case' alias, while older
     * uploads may carry any alias from CASE_IMAGE_TYPES.
     */
    private function attachCaseImages(Patient $patient): void
    {
        $cases = $patient->getRelation('cases');
        $images = Image::query()
            ->whereIn('imageable_type', self::CASE_IMAGE_TYPES)
            ->whereIn('imageable_id', $cases->pluck('id'))
            ->ordered()
            ->orderBy('created_at')
            // No column list: tooth_num only exists on tenants that have run
            // the 2026_10_03 migration, and a missing column would 500 the page.
            ->get()
            ->groupBy('imageable_id');

        $cases->each(fn ($case) => $case->setRelation('images', $images->get($case->id, collect())->values()));
    }

    /**
     * The dashboard's "Case Photos" gallery stores uploads on the patient, not
     * on a case, so those photos only reach the portal through this relation.
     * Profile pictures are not clinical photos and stay out.
     */
    private function attachPatientPhotos(Patient $patient): void
    {
        $patient->setRelation('images', Image::query()
            ->whereIn('imageable_type', self::PATIENT_IMAGE_TYPES)
            ->where('imageable_id', $patient->id)
            ->where(fn ($query) => $query->whereNull('type')->orWhere('type', '!=', 'profile'))
            ->ordered()
            ->orderBy('created_at')
            ->get());
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
