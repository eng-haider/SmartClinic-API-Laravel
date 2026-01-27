<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class SecretaryRepository
{
    /**
     * Get all secretaries for a specific clinic
     */
    public function getAllForClinic(int $clinicId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::where('clinic_id', $clinicId)
            ->whereHas('roles', function ($query) {
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
     */
    public function findInClinic(int $id, int $clinicId): ?User
    {
        return User::where('id', $id)
            ->where('clinic_id', $clinicId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'secretary');
            })
            ->with(['permissions', 'roles'])
            ->first();
    }

    /**
     * Create a new secretary
     */
    public function create(array $data, int $clinicId): User
    {
        $secretary = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'clinic_id' => $clinicId,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Assign secretary role
        $secretary->assignRole('secretary');

        // Assign custom permissions if provided
        if (!empty($data['permissions'])) {
            $secretary->givePermissionTo($data['permissions']);
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
            'email' => $data['email'],
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
     */
    public function getAvailablePermissions(): array
    {
        return [
            'patients' => [
                'view-clinic-patients' => 'View all clinic patients',
                'create-patient' => 'Create new patients',
                'edit-patient' => 'Edit patient information',
                'delete-patient' => 'Delete patients',
                'search-patient' => 'Search patients',
            ],
            'cases' => [
                'view-clinic-cases' => 'View all clinic cases',
                'create-case' => 'Create new cases',
                'edit-case' => 'Edit cases',
                'delete-case' => 'Delete cases',
            ],
            'bills' => [
                'view-clinic-bills' => 'View all clinic bills',
                'create-bill' => 'Create new bills',
                'edit-bill' => 'Edit bills',
                'delete-bill' => 'Delete bills',
                'mark-bill-paid' => 'Mark bills as paid',
            ],
            'reservations' => [
                'view-clinic-reservations' => 'View all reservations',
                'create-reservation' => 'Create new reservations',
                'edit-reservation' => 'Edit reservations',
                'delete-reservation' => 'Delete reservations',
            ],
            'notes' => [
                'create-note' => 'Create notes',
                'edit-note' => 'Edit notes',
                'delete-note' => 'Delete notes',
            ],
        ];
    }

    /**
     * Get base role permissions
     */
    public function getBaseRolePermissions(): array
    {
        return [
            'view-own-clinic',
            'view-notes',
        ];
    }
}
