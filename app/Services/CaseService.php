<?php

namespace App\Services;

use App\Models\Case as MedicalCase;
use App\Repositories\CaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class CaseService
{
    /**
     * Create a new service instance
     */
    public function __construct(private CaseRepository $caseRepository)
    {
    }

    /**
     * Get all cases with filters
     */
    public function getAllCases(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->caseRepository->getAllWithFilters($filters, $perPage);
    }

    /**
     * Get case by ID
     */
    public function getCase(int $id): ?MedicalCase
    {
        $case = $this->caseRepository->getById($id);
        
        if (!$case) {
            throw new \Exception('Case not found');
        }

        return $case;
    }

    /**
     * Create a new case
     */
    public function createCase(array $data): MedicalCase
    {
        // Validate relationships exist
        $this->validateCaseRelationships($data);

        return $this->caseRepository->create($data);
    }

    /**
     * Update case
     */
    public function updateCase(int $id, array $data): MedicalCase
    {
        // Validate case exists
        $case = $this->getCase($id);

        // Validate relationships if they are being updated
        if (isset($data['patient_id']) || isset($data['doctor_id']) || 
            isset($data['case_categores_id']) || isset($data['status_id'])) {
            $this->validateCaseRelationships($data, $id);
        }

        return $this->caseRepository->update($id, $data);
    }

    /**
     * Delete case
     */
    public function deleteCase(int $id): bool
    {
        $case = $this->getCase($id);
        return $this->caseRepository->delete($id);
    }

    /**
     * Restore soft deleted case
     */
    public function restoreCase(int $id): bool
    {
        return $this->caseRepository->restore($id);
    }

    /**
     * Force delete case permanently
     */
    public function forceDeleteCase(int $id): bool
    {
        return $this->caseRepository->forceDelete($id);
    }

    /**
     * Get cases by patient
     */
    public function getCasesByPatient(int $patientId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->caseRepository->getByPatientId($patientId, $perPage);
    }

    /**
     * Get cases by doctor
     */
    public function getCasesByDoctor(int $doctorId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->caseRepository->getByDoctorId($doctorId, $perPage);
    }

    /**
     * Get cases by status
     */
    public function getCasesByStatus(int $statusId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->caseRepository->getByStatusId($statusId, $perPage);
    }

    /**
     * Get cases by category
     */
    public function getCasesByCategory(int $categoryId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->caseRepository->getByCategoryId($categoryId, $perPage);
    }

    /**
     * Get paid cases
     */
    public function getPaidCases(int $perPage = 15): LengthAwarePaginator
    {
        return $this->caseRepository->getPaidCases($perPage);
    }

    /**
     * Get unpaid cases
     */
    public function getUnpaidCases(int $perPage = 15): LengthAwarePaginator
    {
        return $this->caseRepository->getUnpaidCases($perPage);
    }

    /**
     * Mark case as paid
     */
    public function markCaseAsPaid(int $id): bool
    {
        $case = $this->getCase($id);
        return $this->caseRepository->markAsPaid($id);
    }

    /**
     * Mark case as unpaid
     */
    public function markCaseAsUnpaid(int $id): bool
    {
        $case = $this->getCase($id);
        return $this->caseRepository->markAsUnpaid($id);
    }

    /**
     * Update case status
     */
    public function updateCaseStatus(int $id, int $statusId): bool
    {
        $case = $this->getCase($id);
        return $this->caseRepository->updateStatus($id, $statusId);
    }

    /**
     * Get revenue statistics
     */
    public function getRevenueStatistics(): array
    {
        $totalRevenue = $this->caseRepository->getTotalRevenue();
        $totalUnpaid = $this->caseRepository->getTotalUnpaidAmount();

        return [
            'total_revenue' => $totalRevenue,
            'total_unpaid' => $totalUnpaid,
            'total_expected' => $totalRevenue + $totalUnpaid,
            'payment_rate' => $totalRevenue > 0 
                ? round(($totalRevenue / ($totalRevenue + $totalUnpaid)) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Validate case relationships
     */
    private function validateCaseRelationships(array $data, ?int $exceptId = null): void
    {
        // This is a placeholder for validation logic
        // You can add more sophisticated validation here if needed
        // such as checking if patient_id, doctor_id, etc. exist in their respective tables
    }
}
