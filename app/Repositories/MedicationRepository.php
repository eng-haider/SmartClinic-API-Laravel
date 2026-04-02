<?php

namespace App\Repositories;

use App\Models\Medication;
use Illuminate\Database\Eloquent\Collection;

class MedicationRepository
{
    /**
     * Get all medications for a clinic, with optional name search.
     */
    public function getAllForClinic(int $clinicId, ?string $search = null): Collection
    {
        $query = Medication::where('clinic_id', $clinicId);

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Find a medication by name for a clinic.
     */
    public function findByNameAndClinic(string $name, int $clinicId): ?Medication
    {
        return Medication::where('clinic_id', $clinicId)
            ->where('name', $name)
            ->first();
    }

    /**
     * Create a new medication for a clinic.
     */
    public function create(string $name, int $clinicId): Medication
    {
        return Medication::create([
            'name' => $name,
            'clinic_id' => $clinicId,
        ]);
    }

    /**
     * Find a medication by ID scoped to a clinic.
     */
    public function findByIdAndClinic(int $id, int $clinicId): ?Medication
    {
        return Medication::where('id', $id)
            ->where('clinic_id', $clinicId)
            ->first();
    }

    /**
     * Delete a medication.
     */
    public function delete(Medication $medication): bool
    {
        return (bool) $medication->delete();
    }
}
