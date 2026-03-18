<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * Provides role-based doctor ID filtering for controllers.
 * 
 * Doctors see only their own data.
 * Admins/Secretaries see all data in their clinic.
 */
trait DoctorFilterTrait
{
    /**
     * Get doctor_id filter based on user role.
     * Returns doctor_id or null.
     * 
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     * We only need to filter by doctor for regular doctors who should only see their own data.
     * 
     * - Super Doctor/Secretary/Admin: sees all data in their tenant database [null]
     * - Doctor with view-all-bills permission: sees all bills [null]
     * - Doctor without view-all-bills permission: sees ONLY their own data [user_id]
     */
    private function getDoctorIdFilter(): ?int
    {
        $user = Auth::user();
        
        // Super doctor, secretary and super_admin see all data in this tenant
        if ($user->hasRole('clinic_super_doctor') || $user->hasRole('secretary') || $user->hasRole('super_admin')) {
            \Log::info('DoctorFilter: User has admin role, returning null');
            return null;
        }
        
        // Doctor with view-all-bills permission sees all bills
        $hasPermission = $user->hasPermissionTo('view-all-bills');
        \Log::info('DoctorFilter: Checking view-all-bills permission', [
            'user_id' => $user->id,
            'has_permission' => $hasPermission,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
        
        if ($hasPermission) {
            \Log::info('DoctorFilter: User has view-all-bills permission, returning null');
            return null;
        }
        
        // Doctor without view-all-bills permission sees only their own data
        if ($user->hasRole('doctor')) {
            \Log::info('DoctorFilter: User is doctor without view-all-bills, returning user_id', ['user_id' => $user->id]);
            return $user->id;
        }
        
        // Default: show all data in this tenant
        \Log::info('DoctorFilter: Default case, returning null');
        return null;
    }
}
