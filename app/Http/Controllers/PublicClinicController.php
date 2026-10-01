<?php

namespace App\Http\Controllers;

use App\Models\CaseCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\ClinicSettingRepository;
use Illuminate\Http\JsonResponse;
use Stancl\Tenancy\Tenancy;

/**
 * Delivers the deliberately small, public marketing representation of a
 * clinic. Patient data and authenticated clinic settings never pass through
 * this controller.
 */
class PublicClinicController extends Controller
{
    public function __construct(
        private Tenancy $tenancy,
        private ClinicSettingRepository $clinicSettings
    ) {
    }

    public function show(string $slug): JsonResponse
    {
        $central = config('tenancy.database.central_connection', 'mysql');
        $tenant = Tenant::on($central)->where('public_slug', $slug)->first();

        if (!$tenant) {
            return $this->notFound();
        }

        try {
            $this->tenancy->initialize($tenant);
            $clinic = $this->clinicSettings->publicIdentity();

            // Existing clinics predate this setting. They remain public unless
            // a clinic explicitly turns the site off in its configuration.
            if ($clinic['public_site_enabled'] === false) {
                return $this->notFound();
            }

            $services = CaseCategory::query()
                ->select('name', 'name_en', 'name_ar', 'category_type', 'order')
                ->orderBy('order')
                ->orderBy('name')
                ->limit(12)
                ->get()
                ->map(fn (CaseCategory $category) => [
                    'name' => $category->name,
                    'name_en' => $category->name_en,
                    'name_ar' => $category->name_ar,
                    'type' => $category->category_type,
                ])
                ->values();

            // Only members with a doctor role are suitable for a public
            // directory. A clinic that has not configured roles simply shows
            // no doctor section rather than leaking staff accounts.
            $doctors = User::query()
                ->select('id', 'name')
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('name', 'like', '%doctor%'))
                ->orderBy('name')
                ->get()
                ->map(fn (User $doctor) => [
                    'name' => $doctor->name,
                    'title' => null,
                ])
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'slug' => $tenant->public_slug,
                    // The existing staged booking API resolves tenancy from
                    // this opaque tenant key. It is never used to read data.
                    'booking_clinic_id' => $tenant->getKey(),
                    'clinic' => $clinic,
                    'services' => $services,
                    'doctors' => $doctors,
                ],
            ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json([
                'success' => false,
                'message' => 'This clinic website is temporarily unavailable.',
            ], 503)->header('Cache-Control', 'no-store');
        }
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'This clinic website is unavailable.',
        ], 404)->header('Cache-Control', 'no-store');
    }
}
