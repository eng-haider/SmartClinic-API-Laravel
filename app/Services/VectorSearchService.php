<?php

namespace App\Services;

use App\Models\Embedding;
use App\Models\Patient;
use App\Models\Reservation;
use App\Models\CaseModel;
use App\Models\Bill;
use App\Services\AI\SmartChatOrchestrator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VectorSearchService
{
    private EmbeddingService $embeddingService;
    private SmartChatOrchestrator $orchestrator;

    public function __construct(EmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
        $this->orchestrator = app(SmartChatOrchestrator::class);
    }

    /**
     * Search for the most similar embeddings to a query string.
     */
    public function searchSimilar(string $clinicId, string $query, int $limit = 5): Collection
    {
        $queryVector = $this->embeddingService->generateEmbedding($query);
        $vectorString = '[' . implode(',', $queryVector) . ']';

        $results = DB::connection('pgsql_embeddings')
            ->select(
                "SELECT id, clinic_id, table_name, record_id, content,
                        1 - (embedding <=> ?::vector) as similarity
                 FROM embeddings
                 WHERE clinic_id = ?
                 ORDER BY embedding <=> ?::vector
                 LIMIT ?",
                [$vectorString, $clinicId, $vectorString, $limit]
            );

        return collect($results);
    }

    /**
     * Fetch the original records from the tenant database using table_name + record_id.
     */
    public function fetchOriginalRecords(Collection $embeddingResults): array
    {
        $records = [];

        $modelMap = [
            'patients' => Patient::class,
            'reservations' => Reservation::class,
            'cases' => CaseModel::class,
            'bills' => Bill::class,
        ];

        foreach ($embeddingResults as $embedding) {
            $modelClass = $modelMap[$embedding->table_name] ?? null;

            if (!$modelClass) {
                $records[] = [
                    'source' => $embedding->table_name,
                    'record_id' => $embedding->record_id,
                    'content' => $embedding->content,
                    'similarity' => round($embedding->similarity, 4),
                ];
                continue;
            }

            try {
                $query = $modelClass::query()->where('id', $embedding->record_id);

                switch ($embedding->table_name) {
                    case 'patients':
                        $query->with(['doctor:id,name']);
                        break;
                    case 'reservations':
                        $query->with(['patient:id,name', 'doctor:id,name', 'status:id,name', 'reservationType:id,name']);
                        break;
                    case 'cases':
                        $query->with(['patient:id,name', 'doctor:id,name', 'category:id,name', 'status:id,name']);
                        break;
                    case 'bills':
                        $query->with(['patient:id,name', 'doctor:id,name']);
                        break;
                }

                $record = $query->first();

                if ($record) {
                    $records[] = [
                        'source' => $embedding->table_name,
                        'record_id' => $embedding->record_id,
                        'data' => $record->toArray(),
                        'content' => $embedding->content,
                        'similarity' => round($embedding->similarity, 4),
                    ];
                } else {
                    $records[] = [
                        'source' => $embedding->table_name,
                        'record_id' => $embedding->record_id,
                        'content' => $embedding->content,
                        'similarity' => round($embedding->similarity, 4),
                        'note' => 'Original record no longer exists',
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch original record', [
                    'table' => $embedding->table_name,
                    'record_id' => $embedding->record_id,
                    'error' => $e->getMessage(),
                ]);

                $records[] = [
                    'source' => $embedding->table_name,
                    'record_id' => $embedding->record_id,
                    'content' => $embedding->content,
                    'similarity' => round($embedding->similarity, 4),
                ];
            }
        }

        return $records;
    }

    // =========================================================================
    // MAIN CHAT METHOD — delegates to SmartChatOrchestrator
    // =========================================================================

    /**
     * Smart chat: Analyze → Tools → Vector Search → Context → GPT → Answer
     *
     * This method maintains backward compatibility while using the new
     * AI-powered pipeline via SmartChatOrchestrator.
     */
    public function chat(string $clinicId, string $question): array
    {
        return $this->orchestrator->chat($clinicId, $question);
    }
}