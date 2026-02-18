<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SecretaryRepository
{
    /**
     * Get all secretaries for a specific clinic
     * In multi-tenant setup, database is already isolated by tenant
     * clinic_id is optional for backward compatibility
     */
    public function getAllForClinic(?string $clinicId = null, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::whereHas('roles', function ($query) {
                $query->where('name', 'secretary');
            })
            ->with(['permissions', 'roles']);

        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Sort
        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Find secretary by ID within clinic
     * In multi-tenant setup, database is already isolated by tenant
     * clinic_id is optional for backward compatibility
     */
    public function findInClinic(int $id): ?User
    {
        $query = User::on('mysql')->where('id', $id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'secretary');
            })
            ->with(['permissions', 'roles']);

        return $query->first();
    }

    /**
     * Create a new secretary
     * Always create in central database
     * clinic_id is automatically retrieved from authenticated user
     */
    public function create(array $data): User
    {
        $hashedPassword = Hash::make($data['password']);
     
        // Remove clinic_id from data for tenant databases (they don't have this column)
        // clinic_id is only used in central database
        $secretary = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            // 'email' => $data['email'],
            'password' => $hashedPassword,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Assign secretary role (only if role exists in central DB)
        try {
            $secretary->assignRole('secretary');
        } catch (\Exception $e) {
            // If secretary role doesn't exist in central DB, continue without it
            // Secretaries work within tenant databases where roles are properly seeded
            Log::warning('Could not assign secretary role in central database', [
                'user_id' => $secretary->id,
                'error' => $e->getMessage()
            ]);
        }

        // Assign custom permissions if provided
        if (!empty($data['permissions'])) {
            $secretary->givePermissionTo($data['permissions']);
        }

        // Also create user in central database for smart login (if in tenant context)
        try {
            // Use tenant() helper to get current clinic ID reliably
            $clinicId = function_exists('tenant') && tenant() ? tenant()->id : null;

            if (!$clinicId) {
                // Fallback: try from authenticated user's clinic_id attribute
                $authUser = Auth::user();
                $clinicId = $authUser ? \Illuminate\Support\Facades\DB::connection(config('tenancy.database.central_connection'))
                    ->table('users')
                    ->where('phone', $authUser->phone)
                    ->value('clinic_id') : null;
            }

            if ($clinicId) {
                $centralConnection = config('tenancy.database.central_connection');

                // Check if user already exists in central database
                $existingCentralUser = User::on($centralConnection)
                    ->where('phone', $secretary->phone)
                    ->first();

                if (!$existingCentralUser) {
                    // Create user in central database for smart login
                    $centralUser = new User();
                    $centralUser->setConnection($centralConnection);
                    $centralUser->name      = $secretary->name;
                    $centralUser->phone     = $secretary->phone;
                    $centralUser->email     = $secretary->email ?? null;
                    $centralUser->password  = $hashedPassword;
                    $centralUser->is_active = $secretary->is_active ?? true;
                    $centralUser->clinic_id = $clinicId;
                    $centralUser->save();

                    Log::info('Secretary created in central database for smart login', [
                        'tenant_user_id' => $secretary->id,
                        'central_user_id' => $centralUser->id,
                        'clinic_id'       => $clinicId,
                        'phone'           => $secretary->phone,
                    ]);
                } else {
                    Log::info('Secretary already exists in central database, skipping creation', [
                        'phone'      => $secretary->phone,
                        'central_id' => $existingCentralUser->id,
                    ]);
                }
            } else {
                Log::warning('Could not determine clinic_id for central DB sync, skipping', [
                    'phone' => $secretary->phone,
                ]);
            }
        } catch (\Exception $e) {
            // Log but don't fail the operation if central DB creation fails
            Log::warning('Failed to create secretary in central database', [
                'phone' => $secretary->phone,
                'error' => $e->getMessage(),
            ]);
        }

        return $secretary->fresh(['permissions', 'roles']);
    }

    /**
     * Update secretary information
     */
    public function update(User $secretary, array $data): User
    {
        $updateData = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            // 'email' => $data['email'],
            'is_active' => $data['is_active'] ?? $secretary->is_active,
        ];

        // Only update password if provided
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $secretary->update($updateData);

        return $secretary->fresh(['permissions', 'roles']);
    }

    /**
     * Update secretary permissions
     */
    public function updatePermissions(User $secretary, array $permissions): User
    {
        // Sync permissions (replaces all direct permissions)
        $secretary->syncPermissions($permissions);

        return $secretary->fresh(['permissions', 'roles']);
    }

    /**
     * Delete secretary
     */
    public function delete(User $secretary): bool
    {
        return $secretary->delete();
    }

    /**
     * Toggle secretary active status
     */
    public function toggleStatus(User $secretary): User
    {
        $secretary->update([
            'is_active' => !$secretary->is_active,
        ]);

        return $secretary->fresh(['permissions', 'roles']);
    }

    /**
     * Get available permissions for secretary role
     * Automatically fetches from clinic_super_doctor role permissions in config
     */
    public function getAvailablePermissions(): array
    {
        // Get all permissions from clinic_super_doctor role
        $allPermissions = config('rolesAndPermissions.roles.clinic_super_doctor.permissions', []);

        // Group permissions by category for better UI organization
        $groupedPermissions = [];

        foreach ($allPermissions as $permission) {
            // Extract category from permission name (e.g., 'view-clinic-patients' -> 'patients')
            if (preg_match('/-(patient|case|bill|reservation|note|recipe|expense|doctor|image|report|user|clinic)s?$/i', $permission, $matches)) {
                $category = $matches[1] . 's'; // pluralize
                $groupedPermissions[$category][$permission] = $this->formatPermissionName($permission);
            } elseif (strpos($permission, 'manage-') === 0) {
                $groupedPermissions['system'][$permission] = $this->formatPermissionName($permission);
            } else {
                $groupedPermissions['general'][$permission] = $this->formatPermissionName($permission);
            }
        }

        return $groupedPermissions;
    }

    /**
     * Format permission name for display
     */
    private function formatPermissionName(string $permission): string
    {
        // Convert 'view-clinic-patients' to 'View Clinic Patients'
        return ucwords(str_replace('-', ' ', $permission));
    }

    /**
     * Get all permissions from clinic_super_doctor role
     */
    public function getAllPermissions(): array
    {
        return config('rolesAndPermissions.roles.clinic_super_doctor.permissions', []);
    }

    /**
     * Get base role permissions for secretary
     * These are automatically granted via the secretary role
     * Note: Secretary role has no base permissions by design - all permissions are custom assigned
     */
    public function getBaseRolePermissions(): array
    {
        $basePermissions = config('rolesAndPermissions.roles.secretary.permissions', []);
        
        // If no permissions in config, return empty array
        // Secretaries get custom permissions assigned by clinic_super_doctor
        return $basePermissions;
    }
}
