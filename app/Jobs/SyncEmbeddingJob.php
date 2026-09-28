<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public string $clinicId;
    public string $tableName;
    public int $recordId;
    public string $content;

    /**
     * Create a new job instance.
     */
    public function __construct(string $clinicId, string $tableName, int $recordId, string $content)
    {
        $this->clinicId = $clinicId;
        $this->tableName = $tableName;
        $this->recordId = $recordId;
        $this->content = $content;
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\EmbeddingService $embeddingService): void
    {
        $embeddingService->upsertEmbedding(
            $this->clinicId,
            $this->tableName,
            $this->recordId,
            $this->content
        );
    }
}
