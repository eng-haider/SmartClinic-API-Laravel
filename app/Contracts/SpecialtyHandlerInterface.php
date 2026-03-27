<?php

namespace App\Contracts;

/**
 * SpecialtyHandlerInterface
 *
 * Each medical specialty (dental, ophthalmology, dermatology, general)
 * implements this interface to provide specialty-specific behavior.
 *
 * The SpecialtyManager resolves the correct handler based on the
 * current tenant's specialty setting.
 */
interface SpecialtyHandlerInterface
{
    /**
     * Get the specialty identifier (e.g. 'dental', 'ophthalmology').
     */
    public function specialty(): string;

    /**
     * Extra validation rules for encounter creation/update.
     * Merged with base CaseRequest rules.
     *
     * @return array<string, string>
     */
    public function validationRules(): array;

    /**
     * Extra fields to include in API resource output.
     * Merged with base CaseResource output.
     *
     * @param mixed $encounter The CaseModel/Encounter instance
     * @return array<string, mixed>
     */
    public function resourceFields($encounter): array;

    /**
     * Process/transform data before saving an encounter.
     * Can modify the data array (e.g. move fields to detail table).
     *
     * @param array $data Validated request data
     * @return array Modified data for saving to cases table
     */
    public function beforeSave(array $data): array;

    /**
     * Perform post-save operations (e.g. save to specialty detail table).
     *
     * @param mixed $encounter The saved CaseModel/Encounter instance
     * @param array $data Original request data
     */
    public function afterSave($encounter, array $data): void;

    /**
     * Additional searchable fields for repository search queries.
     *
     * @return array<string>
     */
    public function searchableFields(): array;

    /**
     * Default features enabled for this specialty.
     * Used when seeding tenant_features for new tenants.
     *
     * @return array<string, bool>
     */
    public function defaultFeatures(): array;
}
