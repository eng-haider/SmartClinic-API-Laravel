<?php

namespace Tests\Unit;

use App\Models\Patient;
use App\Repositories\PatientRepository;
use App\Services\PatientService;
use PHPUnit\Framework\TestCase;

class PatientServiceTest extends TestCase
{
    private PatientService $service;
    private PatientRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PatientRepository();
        $this->service = new PatientService($this->repository);
    }

    /**
     * Test service can be instantiated
     */
    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(PatientService::class, $this->service);
    }

    /**
     * Test creating patient throws exception for duplicate phone
     */
    public function test_create_patient_throws_exception_for_duplicate_phone(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Phone number already registered');

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '01001234567',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
        ];

        // This would need a database connection in real tests
        // Use Mockery to mock the repository
    }

    /**
     * Test updating patient throws exception for non-existent patient
     */
    public function test_update_patient_throws_exception_for_non_existent_patient(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Patient not found');

        $this->service->updatePatient(999, ['first_name' => 'Test']);
    }

    /**
     * Test deleting patient throws exception for non-existent patient
     */
    public function test_delete_patient_throws_exception_for_non_existent_patient(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Patient not found');

        $this->service->deletePatient(999);
    }
}
