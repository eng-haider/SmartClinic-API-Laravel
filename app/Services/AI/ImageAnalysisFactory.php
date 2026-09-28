<?php

namespace App\Services\AI;

use App\Contracts\ImageAnalysisServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * ImageAnalysisFactory
 *
 * Resolves the correct ImageAnalysisService based on the
 * current tenant's specialty. Follows the same pattern as SpecialtyManager.
 *
 * Usage:
 *   $analyzer = ImageAnalysisFactory::make();           // auto-detect from tenant
 *   $analyzer = ImageAnalysisFactory::make('dental');    // explicit specialty
 *   $analyzer->analyze($file, null, $patientId);
 *
 * To add a new specialty:
 *   1. Create a new service in app/Modules/{Specialty}/{Specialty}ImageAnalysisService.php
 *   2. Extend BaseImageAnalysisService
 *   3. Register it in the ANALYZERS array below
 */
class ImageAnalysisFactory
{
    /**
     * Registry: specialty key => analyzer class.
     * Add new specialties here.
     */
    private const ANALYZERS = [
        'dental'        => \App\Modules\Dental\DentalXrayAnalysisService::class,
        'ophthalmology' => \App\Modules\Ophthalmology\OphthalmologyImageAnalysisService::class,
        'general'       => \App\Modules\General\GeneralImageAnalysisService::class,
        // Future:
        // 'dermatology' => \App\Modules\Dermatology\DermatologyImageAnalysisService::class,
        // 'radiology'   => \App\Modules\Radiology\RadiologyImageAnalysisService::class,
    ];

    /**
     * Resolve the image analysis service for the current tenant or given specialty.
     *
     * @param string|null $specialty Override specialty (null = read from tenant)
     * @return ImageAnalysisServiceInterface
     */
    public static function make(?string $specialty = null): ImageAnalysisServiceInterface
    {
        $specialty = $specialty ?? (tenant('specialty') ?? 'dental');
        $analyzerClass = self::ANALYZERS[$specialty] ?? self::ANALYZERS['general'];

        try {
            return app($analyzerClass);
        } catch (\Throwable $e) {
            Log::warning("ImageAnalysisFactory: Failed to resolve analyzer for '{$specialty}', using GeneralImageAnalysisService", [
                'error' => $e->getMessage(),
            ]);
            return app(\App\Modules\General\GeneralImageAnalysisService::class);
        }
    }

    /**
     * Check if a specialty has a dedicated image analyzer.
     */
    public static function hasAnalyzer(string $specialty): bool
    {
        return isset(self::ANALYZERS[$specialty]);
    }

    /**
     * Get all registered specialty keys with analyzers.
     */
    public static function availableSpecialties(): array
    {
        return array_keys(self::ANALYZERS);
    }
}
