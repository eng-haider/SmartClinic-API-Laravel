<?php

namespace Tests\Unit;

use App\Services\EmbeddingService;
use Mockery;
use PHPUnit\Framework\TestCase;

class EmbeddingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test EmbeddingService class exists and has required methods.
     */
    public function test_embedding_service_has_required_methods(): void
    {
        $this->assertTrue(method_exists(EmbeddingService::class, 'generateEmbedding'));
        $this->assertTrue(method_exists(EmbeddingService::class, 'upsertEmbedding'));
        $this->assertTrue(method_exists(EmbeddingService::class, 'deleteEmbedding'));
        $this->assertTrue(method_exists(EmbeddingService::class, 'syncModelEmbedding'));
        $this->assertTrue(method_exists(EmbeddingService::class, 'bulkSync'));
    }

    /**
     * Test generateEmbedding method signature.
     */
    public function test_generate_embedding_method_signature(): void
    {
        $reflection = new \ReflectionClass(EmbeddingService::class);
        $method = $reflection->getMethod('generateEmbedding');

        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('text', $params[0]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * Test upsertEmbedding method signature.
     */
    public function test_upsert_embedding_method_signature(): void
    {
        $reflection = new \ReflectionClass(EmbeddingService::class);
        $method = $reflection->getMethod('upsertEmbedding');

        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(4, $params);
        $this->assertEquals('clinicId', $params[0]->getName());
        $this->assertEquals('tableName', $params[1]->getName());
        $this->assertEquals('recordId', $params[2]->getName());
        $this->assertEquals('content', $params[3]->getName());
    }

    /**
     * Test deleteEmbedding method signature.
     */
    public function test_delete_embedding_method_signature(): void
    {
        $reflection = new \ReflectionClass(EmbeddingService::class);
        $method = $reflection->getMethod('deleteEmbedding');

        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('clinicId', $params[0]->getName());
        $this->assertEquals('tableName', $params[1]->getName());
        $this->assertEquals('recordId', $params[2]->getName());
    }

    /**
     * Test bulkSync method signature.
     */
    public function test_bulk_sync_method_signature(): void
    {
        $reflection = new \ReflectionClass(EmbeddingService::class);
        $method = $reflection->getMethod('bulkSync');

        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('modelClass', $params[0]->getName());
        $this->assertEquals('clinicId', $params[1]->getName());
        $this->assertEquals('chunkSize', $params[2]->getName());
        $this->assertEquals(100, $params[2]->getDefaultValue());
    }
}
