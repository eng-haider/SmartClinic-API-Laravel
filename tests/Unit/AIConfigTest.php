<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AIConfigTest extends TestCase
{
    /**
     * Test services config has OpenAI settings with chat and embedding model.
     */
    public function test_services_config_has_openai_models(): void
    {
        $configPath = __DIR__ . '/../../config/services.php';
        $this->assertFileExists($configPath);

        $config = include $configPath;

        $this->assertArrayHasKey('openai', $config);
        $this->assertArrayHasKey('api_key', $config['openai']);
        $this->assertArrayHasKey('chat_model', $config['openai']);
        $this->assertArrayHasKey('embedding_model', $config['openai']);
    }

    /**
     * Test embeddings migration file exists.
     */
    public function test_embeddings_migration_exists(): void
    {
        $migrationPath = __DIR__ . '/../../database/migrations/2026_03_24_000001_create_embeddings_table.php';
        $this->assertFileExists($migrationPath);
    }

    /**
     * Test HasEmbeddings trait file exists.
     */
    public function test_has_embeddings_trait_exists(): void
    {
        $traitPath = __DIR__ . '/../../app/Traits/HasEmbeddings.php';
        $this->assertFileExists($traitPath);
    }

    /**
     * Test EmbeddingService class exists.
     */
    public function test_embedding_service_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\EmbeddingService::class));
    }

    /**
     * Test VectorSearchService class exists.
     */
    public function test_vector_search_service_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\VectorSearchService::class));
    }

    /**
     * Test Embedding model class exists.
     */
    public function test_embedding_model_exists(): void
    {
        $this->assertTrue(class_exists(\App\Models\Embedding::class));
    }

    /**
     * Test AIController has chat method.
     */
    public function test_ai_controller_has_chat_method(): void
    {
        $this->assertTrue(
            method_exists(\App\Http\Controllers\AIController::class, 'chat'),
            'AIController must have chat() method'
        );
    }

    /**
     * Test AIController has syncEmbeddings method.
     */
    public function test_ai_controller_has_sync_embeddings_method(): void
    {
        $this->assertTrue(
            method_exists(\App\Http\Controllers\AIController::class, 'syncEmbeddings'),
            'AIController must have syncEmbeddings() method'
        );
    }
}
