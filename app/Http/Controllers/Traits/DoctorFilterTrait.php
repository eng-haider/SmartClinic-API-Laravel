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
     * - Doctor: sees ONLY their own data [user_id]
     */
    private function getDoctorIdFilter(): ?int
    {
        $user = Auth::user();
        
        // Super doctor, secretary and super_admin see all data in this tenant
        if ($user->hasRole('clinic_super_doctor') || $user->hasRole('secretary') || $user->hasRole('super_admin')) {
            return null;
        }
        
        // Doctor sees only their own data
        if ($user->hasRole('doctor')) {
            return $user->id;
        }
        
        // Default: show all data in this tenant
        return null;
    }
}
