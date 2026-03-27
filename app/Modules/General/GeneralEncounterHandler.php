<?php

namespace App\Modules\General;

use App\Contracts\SpecialtyHandlerInterface;

/**
 * GeneralEncounterHandler
 *
 * Fallback handler for 'general' specialty clinics.
 * No specialty-specific fields, validation, or features.
 * Acts as a null-object pattern — safe default.
 */
class GeneralEncounterHandler implements SpecialtyHandlerInterface
{
    public function specialty(): string
    {
        return 'general';
    }

    public function validationRules(): array
    {
        return [];
    }

    public function resourceFields($encounter): array
    {
        return [];
    }

    public function beforeSave(array $data): array
    {
        return $data;
    }

    public function afterSave($encounter, array $data): void
    {
        // No specialty-specific post-save logic
    }

    public function searchableFields(): array
    {
        return [];
    }

    public function defaultFeatures(): array
    {
        return [];
    }
}
