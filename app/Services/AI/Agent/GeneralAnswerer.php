<?php

namespace App\Services\AI\Agent;

use App\Services\VectorSearchService;

/**
 * Open questions the action flows do not cover ("which doctor has the most
 * cases?", medical knowledge) go to the chatbot's existing read-only answer
 * pipeline, unchanged, so the widget keeps answering them.
 */
class GeneralAnswerer
{
    /** @return array{answer: string, sources: array}|null */
    public function answer(string $tenantId, string $question): ?array
    {
        try {
            $result = app(VectorSearchService::class)->chat($tenantId, $question);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if (empty($result['success'])) {
            return null;
        }

        return ['answer' => (string) $result['answer'], 'sources' => $result['sources'] ?? []];
    }
}
