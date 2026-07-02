<?php

namespace App\Services;

use App\Models\Embedding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use OpenAI;

class EmbeddingService
{
    private $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.api_key'));
    }

    /**
     * Generate an embedding vector from text using OpenAI.
     *
     * @param string $text  The text to embed
     * @return array        The embedding vector (1536 dimensions)
     */
    public function generateEmbedding(string $text): array
    {
        $response = $this->client->embeddings()->create([
            'model' => config('services.openai.embedding_model', 'text-embedding-3-small'),
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    /**
     * Upsert an embedding for a specific record.
     * Skips regeneration if content hasn't changed.
     *
     * @param string $clinicId   The clinic/tenant ID
     * @param string $tableName  Source table name
     * @param int    $recordId   Source record ID
     * @param string $content    Concatenated text content
     */
    public function upsertEmbedding(string $clinicId, string $tableName, int $recordId, string $content): void
    {
        // Check if embedding exists with same content
        $existing = Embedding::forClinic($clinicId)
            ->forRecord($tableName, $recordId)
            ->first();

        if ($existing && $existing->content === $content) {
            return; // Content unchanged, skip
        }

        // Generate new embedding
        $vector = $this->generateEmbedding($content);
        $vectorString = '[' . implode(',', $vector) . ']';

        // Atomic upsert keyed on the (clinic_id, table_name, record_id) unique
        // constraint. Concurrent jobs for the same record (e.g. a create quickly
        // followed by an update) can otherwise both read "no existing row" during
        // the slow OpenAI call above and then both INSERT, causing a duplicate-key
        // violation. ON CONFLICT makes the write race-safe: whoever loses the
        // insert falls through to the UPDATE instead of erroring.
        \Illuminate\Support\Facades\DB::connection('pgsql_embeddings')
            ->statement(
                "INSERT INTO embeddings (clinic_id, table_name, record_id, content, embedding, updated_at)
                 VALUES (?, ?, ?, ?, ?::vector, ?)
                 ON CONFLICT (clinic_id, table_name, record_id)
                 DO UPDATE SET content = EXCLUDED.content,
                               embedding = EXCLUDED.embedding,
                               updated_at = EXCLUDED.updated_at",
                [$clinicId, $tableName, $recordId, $content, $vectorString, now()]
            );
    }

    /**
     * Delete embedding for a specific record.
     */
    public function deleteEmbedding(string $clinicId, string $tableName, int $recordId): void
    {
        Embedding::forClinic($clinicId)
            ->forRecord($tableName, $recordId)
            ->delete();
    }

    /**
     * Sync a model's embedding from its toEmbeddingContent() method.
     */
    public function syncModelEmbedding(Model $model, string $clinicId): void
    {
        if (!method_exists($model, 'toEmbeddingContent')) {
            throw new \InvalidArgumentException(
                get_class($model) . ' must implement toEmbeddingContent()'
            );
        }

        $content = $model->toEmbeddingContent();

        if (empty(trim($content))) {
            Log::info('Skipping embedding for empty content', [
                'model' => get_class($model),
                'id' => $model->getKey(),
            ]);
            return;
        }

        $this->upsertEmbedding(
            $clinicId,
            $model->getTable(),
            $model->getKey(),
            $content
        );
    }

    /**
     * Bulk sync embeddings for all records of a model class.
     * Useful for initial seeding or full re-sync.
     *
     * @param string $modelClass  The model class (e.g., Patient::class)
     * @param string $clinicId    The clinic/tenant ID
     * @param int    $chunkSize   Number of records per batch
     */
    public function bulkSync(string $modelClass, string $clinicId, int $chunkSize = 100): array
    {
        $synced = 0;
        $failed = 0;

        $modelClass::query()->chunk($chunkSize, function ($records) use ($clinicId, &$synced, &$failed) {
            foreach ($records as $record) {
                try {
                    $this->syncModelEmbedding($record, $clinicId);
                    $synced++;
                } catch (\Exception $e) {
                    $failed++;
                    Log::warning('Bulk embedding sync failed', [
                        'model' => get_class($record),
                        'id' => $record->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return ['synced' => $synced, 'failed' => $failed];
    }
}
