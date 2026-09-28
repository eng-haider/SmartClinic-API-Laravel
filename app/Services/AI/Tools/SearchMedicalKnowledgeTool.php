<?php

namespace App\Services\AI\Tools;

use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SearchMedicalKnowledgeTool implements AIToolInterface
{
    public function name(): string
    {
        return 'search_medical_knowledge';
    }

    public function description(): string
    {
        return 'Searches the medical knowledge base using vector similarity for dental/medical information.';
    }

    public function execute(array $params): string
    {
        $question = $params['question'] ?? '';
        $clinicId = $params['clinic_id'] ?? '';

        if (empty($question)) {
            return "No question provided for medical knowledge search.";
        }

        try {
            $embeddingService = app(EmbeddingService::class);
            $queryVector = $embeddingService->generateEmbedding($question);
            $vectorString = '[' . implode(',', $queryVector) . ']';

            // Check if medical_knowledge table exists
            $tableExists = DB::connection('pgsql_embeddings')
                ->select("SELECT to_regclass('public.medical_knowledge') IS NOT NULL as exists");

            if (empty($tableExists) || !$tableExists[0]->exists) {
                return "Medical knowledge base is not yet configured.";
            }

            $results = DB::connection('pgsql_embeddings')->select(
                "SELECT id, title, content,
                        1 - (embedding <=> ?::vector) as similarity
                 FROM medical_knowledge
                 WHERE 1 - (embedding <=> ?::vector) >= 0.5
                 ORDER BY embedding <=> ?::vector
                 LIMIT 5",
                [$vectorString, $vectorString, $vectorString]
            );

            if (empty($results)) {
                return "No relevant medical knowledge found for this question.";
            }

            $lines = ["--- Medical Knowledge Base Results ---"];
            foreach ($results as $index => $result) {
                $num = $index + 1;
                $similarity = round($result->similarity, 4);
                $lines[] = "Result {$num} (Similarity: {$similarity}):";
                $lines[] = "Title: {$result->title}";
                $lines[] = "Content: {$result->content}";
                $lines[] = "";
            }

            return implode("\n", $lines);

        } catch (\Exception $e) {
            Log::warning('SearchMedicalKnowledgeTool error: ' . $e->getMessage());
            return "Medical knowledge search is temporarily unavailable.";
        }
    }
}
