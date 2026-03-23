<?php

namespace Tests\Unit;

use App\Services\VectorSearchService;
use App\Services\EmbeddingService;
use PHPUnit\Framework\TestCase;

class VectorSearchServiceTest extends TestCase
{
    /**
     * Test VectorSearchService requires EmbeddingService dependency.
     */
    public function test_service_requires_embedding_service(): void
    {
        $reflection = new \ReflectionClass(VectorSearchService::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('embeddingService', $params[0]->getName());

        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertEquals(EmbeddingService::class, $type->getName());
    }

    /**
     * Test chat method exists and has correct signature.
     */
    public function test_chat_method_signature(): void
    {
        $reflection = new \ReflectionClass(VectorSearchService::class);
        $method = $reflection->getMethod('chat');

        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('clinicId', $params[0]->getName());
        $this->assertEquals('question', $params[1]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * Test searchSimilar method has correct signature with default limit.
     */
    public function test_search_similar_method_signature(): void
    {
        $reflection = new \ReflectionClass(VectorSearchService::class);
        $method = $reflection->getMethod('searchSimilar');

        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('clinicId', $params[0]->getName());
        $this->assertEquals('query', $params[1]->getName());
        $this->assertEquals('limit', $params[2]->getName());
        $this->assertEquals(5, $params[2]->getDefaultValue());
    }

    /**
     * Test fetchOriginalRecords method exists and is public.
     */
    public function test_fetch_original_records_method_exists(): void
    {
        $reflection = new \ReflectionClass(VectorSearchService::class);
        $method = $reflection->getMethod('fetchOriginalRecords');

        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(1, $params);
    }

    /**
     * Test VectorSearchService has all required methods.
     */
    public function test_service_has_all_required_methods(): void
    {
        $this->assertTrue(method_exists(VectorSearchService::class, 'searchSimilar'));
        $this->assertTrue(method_exists(VectorSearchService::class, 'fetchOriginalRecords'));
        $this->assertTrue(method_exists(VectorSearchService::class, 'chat'));
    }
}
