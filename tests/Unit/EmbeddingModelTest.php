<?php

namespace Tests\Unit;

use App\Models\Embedding;
use PHPUnit\Framework\TestCase;

class EmbeddingModelTest extends TestCase
{
    /**
     * Test Embedding model uses correct connection.
     */
    public function test_embedding_uses_pgsql_embeddings_connection(): void
    {
        $embedding = new Embedding();
        $this->assertEquals('pgsql_embeddings', $embedding->getConnectionName());
    }

    /**
     * Test Embedding model uses correct table name.
     */
    public function test_embedding_uses_correct_table(): void
    {
        $embedding = new Embedding();
        $this->assertEquals('embeddings', $embedding->getTable());
    }

    /**
     * Test Embedding model has timestamps disabled.
     */
    public function test_embedding_has_timestamps_disabled(): void
    {
        $embedding = new Embedding();
        $this->assertFalse($embedding->usesTimestamps());
    }

    /**
     * Test Embedding model has correct fillable attributes.
     */
    public function test_embedding_has_correct_fillable(): void
    {
        $embedding = new Embedding();
        $expected = ['clinic_id', 'table_name', 'record_id', 'content', 'embedding', 'updated_at'];
        $this->assertEquals($expected, $embedding->getFillable());
    }

    /**
     * Test Embedding model casts record_id to integer.
     */
    public function test_embedding_casts_record_id_to_integer(): void
    {
        $embedding = new Embedding();
        $casts = $embedding->getCasts();
        $this->assertArrayHasKey('record_id', $casts);
        $this->assertEquals('integer', $casts['record_id']);
    }

    /**
     * Test Embedding model casts updated_at to datetime.
     */
    public function test_embedding_casts_updated_at_to_datetime(): void
    {
        $embedding = new Embedding();
        $casts = $embedding->getCasts();
        $this->assertArrayHasKey('updated_at', $casts);
        $this->assertEquals('datetime', $casts['updated_at']);
    }
}
