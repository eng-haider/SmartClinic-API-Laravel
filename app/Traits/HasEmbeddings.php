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
 *
 * All work is deferred to app()->terminating() so it runs AFTER the
 * HTTP response is sent — POST/PATCH return immediately while embedding
 * sync happens in the background of the same PHP process.
 */
trait HasEmbeddings
{
    /**
     * Boot the trait: register model observers for embedding sync.
     */
    public static function bootHasEmbeddings(): void
    {
        static::created(function ($model) {
            $clinicId = tenant('id');
            if (!$clinicId) {
                return;
            }

            app()->terminating(function () use ($model, $clinicId) {
                try {
                    $content = trim($model->toEmbeddingContent());
                    if ($content === '') {
                        return;
                    }
                    \App\Jobs\SyncEmbeddingJob::dispatch(
                        $clinicId,
                        $model->getEmbeddingTableName(),
                        $model->getKey(),
                        $content
                    );
                } catch (\Throwable $e) {
                    Log::warning('Embedding job dispatch failed on create: ' . $e->getMessage(), [
                        'model' => get_class($model),
                        'id' => $model->getKey(),
                    ]);
                }
            });
        });

        static::updated(function ($model) {
            $clinicId = tenant('id');
            if (!$clinicId) {
                return;
            }

            // Cheap short-circuit: if the model didn't actually change any
            // persisted attributes, skip the embedding work entirely.
            if (empty($model->getChanges())) {
                return;
            }

            $tableName = $model->getEmbeddingTableName();
            $recordId = $model->getKey();

            app()->terminating(function () use ($model, $clinicId, $tableName, $recordId) {
                try {
                    $content = trim($model->toEmbeddingContent());
                    if ($content === '') {
                        return;
                    }

                    $existingEmbedding = \App\Models\Embedding::forClinic($clinicId)
                        ->forRecord($tableName, $recordId)
                        ->first();

                    if (!$existingEmbedding || $existingEmbedding->content !== $content) {
                        \App\Jobs\SyncEmbeddingJob::dispatch($clinicId, $tableName, $recordId, $content);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Embedding job dispatch failed on update: ' . $e->getMessage(), [
                        'model' => get_class($model),
                        'id' => $recordId,
                    ]);
                }
            });
        });

        static::deleted(function ($model) {
            $clinicId = tenant('id');
            if (!$clinicId) {
                return;
            }

            $tableName = $model->getEmbeddingTableName();
            $recordId = $model->getKey();
            $modelClass = get_class($model);

            app()->terminating(function () use ($clinicId, $tableName, $recordId, $modelClass) {
                try {
                    app(EmbeddingService::class)->deleteEmbedding($clinicId, $tableName, $recordId);
                } catch (\Throwable $e) {
                    Log::warning('Embedding delete failed: ' . $e->getMessage(), [
                        'model' => $modelClass,
                        'id' => $recordId,
                    ]);
                }
            });
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
