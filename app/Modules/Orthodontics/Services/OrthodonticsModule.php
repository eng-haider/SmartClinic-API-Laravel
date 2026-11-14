<?php

namespace App\Modules\Orthodontics\Services;

use App\Models\CaseModel;
use App\Repositories\ClinicSettingRepository;

/**
 * Whether the orthodontics module is switched on for the current clinic, and
 * whether a case is eligible for it.
 *
 * The setting lives in the clinic's own (tenant) database, so each clinic
 * switches the module independently. A missing setting means off.
 */
class OrthodonticsModule
{
    public function __construct(private ClinicSettingRepository $settings)
    {
    }

    public static function settingKey(): string
    {
        return (string) config('orthodontics.setting_key', 'enable_orthodontics');
    }

    public function enabled(): bool
    {
        $setting = $this->settings->getByKey(self::settingKey());

        return $setting !== null && $setting->is_active !== false && $setting->getValue() === true;
    }

    /**
     * A case is eligible only when its category is flagged is_orthodontic.
     * The flag is set per category by the clinic, so it does not depend on
     * category ids or names.
     */
    public function caseIsEligible(CaseModel $case): bool
    {
        return (bool) $case->category?->is_orthodontic;
    }
}
