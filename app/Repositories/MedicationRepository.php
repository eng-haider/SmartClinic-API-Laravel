<?php

namespace App\Repositories;

use App\Models\Medication;
use Illuminate\Database\Eloquent\Collection;

class MedicationRepository
{
    /**
     * Get all medications with optional name search.
     */
    public function getAll(?string $search = null): Collection
    {
        $query = Medication::query();

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Find a medication by name.
     */
    public function findByName(string $name): ?Medication
    {
        return Medication::where('name', $name)->first();
    }

    /**
     * Create a new medication.
     */
    public function create(string $name): Medication
    {
        return Medication::create([
            'name' => $name,
        ]);
    }

    /**
     * Find a medication by ID.
     */
    public function findById(int $id): ?Medication
    {
        return Medication::find($id);
    }

    /**
     * Delete a medication.
     */
    public function delete(Medication $medication): bool
    {
        return (bool) $medication->delete();
    }
}
