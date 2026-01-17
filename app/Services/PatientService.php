<?php

namespace App\Services;

use App\Models\Patient;
use App\Repositories\PatientRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class PatientService
{
    /**
     * Create a new service instance
     */
    public function __construct(private PatientRepository $patientRepository)
    {
    }

    /**
     * Get all patients with filters
     */
    public function getAllPatients(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->patientRepository->getAllWithFilters($filters, $perPage);
    }

    /**
     * Get patient by ID
     */
    public function getPatient(int $id): ?Patient
    {
        return $this->patientRepository->getById($id);
    }

    /**
     * Create a new patient
     */
    public function createPatient(array $data): Patient
    {
        // Check if phone already exists
        if ($this->patientRepository->existsByPhone($data['phone'])) {
            throw new \Exception('Phone number already registered');
        }

        // Check if email already exists (if provided)
        if (!empty($data['email']) && $this->patientRepository->existsByEmail($data['email'])) {
            throw new \Exception('Email already registered');
        }

        return $this->patientRepository->create($data);
    }

    /**
     * Update patient
     */
    public function updatePatient(int $id, array $data): Patient
    {
        $patient = $this->getPatient($id);

        if (!$patient) {
            throw new \Exception('Patient not found');
        }

        // Check if phone is being changed and already exists
        if (!empty($data['phone']) && $data['phone'] !== $patient->phone) {
            if ($this->patientRepository->existsByPhone($data['phone'], $id)) {
                throw new \Exception('Phone number already registered');
            }
        }

        // Check if email is being changed and already exists
        if (!empty($data['email']) && $data['email'] !== $patient->email) {
            if ($this->patientRepository->existsByEmail($data['email'], $id)) {
                throw new \Exception('Email already registered');
            }
        }

        return $this->patientRepository->update($id, $data);
    }

    /**
     * Delete patient
     */
    public function deletePatient(int $id): bool
    {
        $patient = $this->getPatient($id);

        if (!$patient) {
            throw new \Exception('Patient not found');
        }

        return $this->patientRepository->delete($id);
    }

    /**
     * Search patient by phone
     */
    public function searchByPhone(string $phone): ?Patient
    {
        return $this->patientRepository->getByPhone($phone);
    }

    /**
     * Search patient by email
     */
    public function searchByEmail(string $email): ?Patient
    {
        return $this->patientRepository->getByEmail($email);
    }
}
