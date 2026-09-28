<?php

namespace Tests\Unit;

use App\Models\Patient;
use App\Models\Reservation;
use App\Models\CaseModel;
use App\Models\Bill;
use PHPUnit\Framework\TestCase;

class ModelEmbeddingContentTest extends TestCase
{
    /**
     * Test Patient model has toEmbeddingContent method.
     */
    public function test_patient_has_embedding_content_method(): void
    {
        $this->assertTrue(
            method_exists(Patient::class, 'toEmbeddingContent'),
            'Patient model must implement toEmbeddingContent()'
        );
    }

    /**
     * Test Reservation model has toEmbeddingContent method.
     */
    public function test_reservation_has_embedding_content_method(): void
    {
        $this->assertTrue(
            method_exists(Reservation::class, 'toEmbeddingContent'),
            'Reservation model must implement toEmbeddingContent()'
        );
    }

    /**
     * Test CaseModel has toEmbeddingContent method.
     */
    public function test_case_model_has_embedding_content_method(): void
    {
        $this->assertTrue(
            method_exists(CaseModel::class, 'toEmbeddingContent'),
            'CaseModel must implement toEmbeddingContent()'
        );
    }

    /**
     * Test Bill model has toEmbeddingContent method.
     */
    public function test_bill_has_embedding_content_method(): void
    {
        $this->assertTrue(
            method_exists(Bill::class, 'toEmbeddingContent'),
            'Bill model must implement toEmbeddingContent()'
        );
    }

    /**
     * Test Patient model has HasEmbeddings trait.
     */
    public function test_patient_uses_has_embeddings_trait(): void
    {
        $uses = class_uses_recursive(Patient::class);
        $this->assertArrayHasKey(
            \App\Traits\HasEmbeddings::class,
            $uses,
            'Patient model must use HasEmbeddings trait'
        );
    }

    /**
     * Test Reservation model has HasEmbeddings trait.
     */
    public function test_reservation_uses_has_embeddings_trait(): void
    {
        $uses = class_uses_recursive(Reservation::class);
        $this->assertArrayHasKey(
            \App\Traits\HasEmbeddings::class,
            $uses,
            'Reservation model must use HasEmbeddings trait'
        );
    }

    /**
     * Test CaseModel has HasEmbeddings trait.
     */
    public function test_case_model_uses_has_embeddings_trait(): void
    {
        $uses = class_uses_recursive(CaseModel::class);
        $this->assertArrayHasKey(
            \App\Traits\HasEmbeddings::class,
            $uses,
            'CaseModel must use HasEmbeddings trait'
        );
    }

    /**
     * Test Bill model has HasEmbeddings trait.
     */
    public function test_bill_uses_has_embeddings_trait(): void
    {
        $uses = class_uses_recursive(Bill::class);
        $this->assertArrayHasKey(
            \App\Traits\HasEmbeddings::class,
            $uses,
            'Bill model must use HasEmbeddings trait'
        );
    }

    /**
     * Test toEmbeddingContent method returns string type.
     */
    public function test_to_embedding_content_returns_string(): void
    {
        $reflection = new \ReflectionMethod(Patient::class, 'toEmbeddingContent');
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());

        $reflection = new \ReflectionMethod(Reservation::class, 'toEmbeddingContent');
        $this->assertEquals('string', $reflection->getReturnType()->getName());

        $reflection = new \ReflectionMethod(CaseModel::class, 'toEmbeddingContent');
        $this->assertEquals('string', $reflection->getReturnType()->getName());

        $reflection = new \ReflectionMethod(Bill::class, 'toEmbeddingContent');
        $this->assertEquals('string', $reflection->getReturnType()->getName());
    }

    /**
     * Test Patient getEmbeddingTableName returns correct table.
     */
    public function test_patient_embedding_table_name(): void
    {
        $patient = new Patient();
        $this->assertEquals('patients', $patient->getEmbeddingTableName());
    }

    /**
     * Test CaseModel getEmbeddingTableName returns correct table.
     */
    public function test_case_model_embedding_table_name(): void
    {
        $case = new CaseModel();
        $this->assertEquals('cases', $case->getEmbeddingTableName());
    }

    /**
     * Test Bill getEmbeddingTableName returns correct table.
     */
    public function test_bill_embedding_table_name(): void
    {
        $bill = new Bill();
        $this->assertEquals('bills', $bill->getEmbeddingTableName());
    }

    /**
     * Test Reservation getEmbeddingTableName returns correct table.
     */
    public function test_reservation_embedding_table_name(): void
    {
        $reservation = new Reservation();
        $this->assertEquals('reservations', $reservation->getEmbeddingTableName());
    }

    /**
     * Test getEmbeddingTableName method exists on all models.
     */
    public function test_all_models_have_get_embedding_table_name(): void
    {
        $this->assertTrue(method_exists(Patient::class, 'getEmbeddingTableName'));
        $this->assertTrue(method_exists(Reservation::class, 'getEmbeddingTableName'));
        $this->assertTrue(method_exists(CaseModel::class, 'getEmbeddingTableName'));
        $this->assertTrue(method_exists(Bill::class, 'getEmbeddingTableName'));
    }
}
