<?php

namespace App\Traits;

use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Log;

/**
 * Trait HasEmbeddings
 *
 * Add to models (Patient, Reservation, CaseModel, Bill) to
 * auto-sync embeddings when records are created or updated.
 *
 * Each model using this trait must implement:
 * - toEmbeddingContent(): string
 */
trait HasEmbeddings
{
    /**
     * Boot the trait: register model observers for embedding sync.
     */
    public static function bootHasEmbeddings(): void
    {
        // Generate embedding when a new record is created
        static::created(function ($model) {
            try {
                $clinicId = tenant('id');
                if ($clinicId) {
                    // Pre-compute content so the background Job doesn't need to query the tenant DB
                    $content = trim($model->toEmbeddingContent());
                    if (!empty($content)) {
                        \App\Jobs\SyncEmbeddingJob::dispatch($clinicId, $model->getEmbeddingTableName(), $model->getKey(), $content);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Embedding job dispatch failed on create: ' . $e->getMessage(), [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                ]);
            }
        });

        // Regenerate embedding when record is updated and content changed
        static::updated(function ($model) {
            try {
                $clinicId = tenant('id');
                if ($clinicId) {
                    $content = trim($model->toEmbeddingContent());
                    
                    if (empty($content)) {
                        return; // Nothing to sync
                    }

                    // Check if embedding exists with same content in the central pgsql_embeddings DB
                    $existingEmbedding = \App\Models\Embedding::forClinic($clinicId)
                        ->forRecord($model->getEmbeddingTableName(), $model->getKey())
                        ->first();

                    // Only dispatch job if content actually changed
                    if (!$existingEmbedding || $existingEmbedding->content !== $content) {
                        \App\Jobs\SyncEmbeddingJob::dispatch($clinicId, $model->getEmbeddingTableName(), $model->getKey(), $content);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Embedding job dispatch failed on update: ' . $e->getMessage(), [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                ]);
            }
        });

        // Remove embedding when record is deleted
        static::deleted(function ($model) {
            try {
                $clinicId = tenant('id');
                if ($clinicId) {
                    app(EmbeddingService::class)->deleteEmbedding(
                        $clinicId,
                        $model->getEmbeddingTableName(),
                        $model->getKey()
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Embedding delete failed: ' . $e->getMessage(), [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                ]);
            }
        });
    }

    /**
     * Get the table name used for embedding storage.
     */
    public function getEmbeddingTableName(): string
    {
        return $this->getTable();
    }

    /**
     * Convert model to embedding content string.
     * Override this in each model for custom content.
     */
    abstract public function toEmbeddingContent(): string;
}
