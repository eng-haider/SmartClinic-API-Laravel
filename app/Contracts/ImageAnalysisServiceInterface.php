<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * ImageAnalysisServiceInterface
 *
 * Each medical specialty implements this to provide specialty-specific
 * AI image analysis (dental X-rays, eye fundus images, skin photos, etc.).
 *
 * The ImageAnalysisFactory resolves the correct implementation based
 * on the current tenant's specialty setting.
 */
interface ImageAnalysisServiceInterface
{
    /**
     * Get the specialty this analyzer serves (e.g. 'dental', 'ophthalmology').
     */
    public function specialty(): string;

    /**
     * Get a human-readable label for the analysis type (e.g. 'Dental X-Ray', 'Eye Fundus').
     */
    public function analysisLabel(): string;

    /**
     * Analyze a medical image.
     *
     * @param UploadedFile|null $imageFile   Uploaded image file
     * @param string|null       $imageBase64 Base64-encoded image string
     * @param int|null          $patientId   Optional patient ID for context
     * @return array Structured analysis result
     */
    public function analyze(?UploadedFile $imageFile = null, ?string $imageBase64 = null, ?int $patientId = null): array;
}
