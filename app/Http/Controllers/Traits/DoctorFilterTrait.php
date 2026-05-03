<?php

namespace App\Http\Controllers\Traits;

use App\Http\Helpers\BillsIsolationHelper;
use Illuminate\Support\Facades\Auth;

/**
 * Provides role-based doctor ID filtering for controllers.
 *
 * Two filtering methods:
 *  - getDoctorIdFilter()      → permission-based only (used by cases, reports, etc.)
 *  - getBillsDoctorIdFilter() → also checks doctor_bills_isolation setting (bills only)
 */
trait DoctorFilterTrait
{
    /**
     * Get doctor_id filter — PERMISSION-BASED ONLY.
     *
     * Used by: CaseController, CaseReportController, and any non-bill controller.
     * Does NOT check the doctor_bills_isolation clinic setting.
     *
     * - Super Doctor / Secretary / Admin → null (see everything)
     * - Doctor with view-all-bills permission → null
     * - Doctor without permission → user_id
     */
    private function getDoctorIdFilter(): ?int
    {
        $user = Auth::user();

        if ($user->hasRole('clinic_super_doctor') || $user->hasRole('secretary') || $user->hasRole('super_admin')) {
            \Log::info('DoctorFilter: User has admin role, returning null');
            return null;
        }

        if ($user->hasRole('doctor')) {
            $hasPermission = $user->hasPermissionTo('view-all-bills');
            \Log::info('DoctorFilter: Checking view-all-bills permission', [
                'user_id'        => $user->id,
                'has_permission' => $hasPermission,
                'roles'          => $user->getRoleNames(),
            ]);

            if ($hasPermission) {
                return null;
            }

            return $user->id;
        }

        return null;
    }

    /**
     * Get doctor_id filter for BILLS — respects the doctor_bills_isolation clinic setting.
     *
     * Used by: BillController only.
     *
     * When doctor_bills_isolation = true:
     *   → Doctor is ALWAYS isolated (ignores view-all-bills permission)
     * When doctor_bills_isolation = false (default):
     *   → Falls back to standard permission-based check
     *
     * Super Doctor / Secretary / Admin are NEVER affected by isolation.
     */
    private function getBillsDoctorIdFilter(): ?int
    {
        $user = Auth::user();

        if ($user->hasRole('clinic_super_doctor') || $user->hasRole('secretary') || $user->hasRole('super_admin')) {
            \Log::info('BillsDoctorFilter: User has admin role, returning null');
            return null;
        }

        if ($user->hasRole('doctor')) {
            if (BillsIsolationHelper::isEnabled()) {
                \Log::info('BillsDoctorFilter: doctor_bills_isolation ON, returning user_id', [
                    'user_id' => $user->id,
                ]);
                return $user->id;
            }

            // Isolation OFF: standard permission check
            $hasPermission = $user->hasPermissionTo('view-all-bills');
            \Log::info('BillsDoctorFilter: isolation OFF, checking view-all-bills permission', [
                'user_id'        => $user->id,
                'has_permission' => $hasPermission,
            ]);

            if ($hasPermission) {
                return null;
            }

            return $user->id;
        }

        return null;
    }
}
