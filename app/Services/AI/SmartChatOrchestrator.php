<?php

namespace App\Services\AI;

use App\Services\AI\Tools\AIToolInterface;
use App\Services\AI\Tools\GetRevenueReportTool;
use App\Services\AI\Tools\GetPatientsSummaryTool;
use App\Services\AI\Tools\GetAppointmentsTool;
use App\Services\AI\Tools\GetCasesTool;
use App\Services\AI\Tools\SearchPatientTool;
use App\Services\AI\Tools\SearchMedicalKnowledgeTool;
use App\Services\AI\Tools\GetAnalyticsTool;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmartChatOrchestrator
{
    private AIQuestionAnalyzer $analyzer;
    private EmbeddingService $embeddingService;

    /** @var array<string, AIToolInterface> */
    private array $tools = [];

    public function __construct(AIQuestionAnalyzer $analyzer, EmbeddingService $embeddingService)
    {
        $this->analyzer = $analyzer;
        $this->embeddingService = $embeddingService;
        $this->registerTools();
    }

    /**
     * Register all available tools.
     */
    private function registerTools(): void
    {
        $toolClasses = [
            new GetRevenueReportTool(),
            new GetPatientsSummaryTool(),
            new GetAppointmentsTool(),
            new GetCasesTool(),
            new SearchPatientTool(),
            new SearchMedicalKnowledgeTool(),
            new GetAnalyticsTool(),
        ];

        foreach ($toolClasses as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * Main chat pipeline:
     * 1. Analyze question with AI
     * 2. Decide what tools to run
     * 3. Run database queries (tools)
     * 4. Run vector search
     * 5. Build context
     * 6. Send to GPT
     * 7. Generate answer
     */
    public function chat(string $clinicId, string $question): array
    {
        $startTime = microtime(true);

        try {
            // Step 1: Analyze the question
            Log::info('AI Pipeline: Analyzing question', ['clinic_id' => $clinicId, 'question' => $question]);
            $analysis = $this->analyzer->analyze($question);
            Log::info('AI Pipeline: Analysis result', ['analysis' => $analysis]);

            // Simple greeting — skip entire pipeline, respond directly
            if ($analysis['intent'] === 'general' && !$analysis['needs_database'] && !$analysis['needs_vector_search']) {
                $answer = $this->callGPT($question, '', 300);
                return $this->buildResponse($question, $answer, [], $analysis, $startTime);
            }

            $contextBuilder = new ContextBuilder();

            // Step 2 & 3: Select and run tools based on analysis
            $toolsToRun = $this->selectTools($analysis);
            $toolParams = [
                'date_range' => $analysis['date_range'],
                'entities' => $analysis['entities'],
                'question' => $question,
                'clinic_id' => $clinicId,
            ];

            foreach ($toolsToRun as $toolName) {
                if (isset($this->tools[$toolName])) {
                    $toolResult = $this->tools[$toolName]->execute($toolParams);
                    $contextBuilder->addToolResult($toolName, $toolResult);
                    Log::info("AI Pipeline: Tool {$toolName} executed", ['result_length' => strlen($toolResult)]);
                }
            }

            // Step 4: Vector search — only when tools can't fully answer
            // Skip for intents where DB tools already provide comprehensive data
            $originalRecords = [];
            $skipVectorIntents = ['revenue', 'expenses', 'patients', 'reservations', 'cases', 'analytics'];
            $shouldRunVector = $analysis['needs_vector_search'] && !in_array($analysis['intent'], $skipVectorIntents);

            if ($shouldRunVector) {
                $originalRecords = $this->runVectorSearch($clinicId, $question);
                $contextBuilder->addVectorSearchResults($originalRecords);
            }

            // Step 4b: Medical knowledge search — only for medical questions
            if ($analysis['needs_knowledge_base'] && $analysis['intent'] === 'medical_question') {
                $kbTool = $this->tools['search_medical_knowledge'] ?? null;
                if ($kbTool) {
                    $kbResult = $kbTool->execute($toolParams);
                    $contextBuilder->addKnowledgeBase($kbResult);
                }
            }

            // Step 5: Build context
            $context = $contextBuilder->build();

            // Step 6 & 7: Send to GPT and generate answer
            $answer = $this->callGPT($question, $context, 1500);

            // Build source references from vector search
            $sources = array_map(fn($record) => [
                'type' => $record['source'],
                'record_id' => $record['record_id'],
                'similarity' => $record['similarity'],
            ], $originalRecords);

            $elapsed = round((microtime(true) - $startTime) * 1000);
            Log::info("AI Pipeline: Completed in {$elapsed}ms", [
                'clinic_id' => $clinicId,
                'intent' => $analysis['intent'],
                'tools_used' => $toolsToRun,
            ]);

            return $this->buildResponse($question, $answer, $sources, $analysis, $startTime);

        } catch (\Exception $e) {
            Log::error('AI Pipeline Error: ' . $e->getMessage(), [
                'clinic_id' => $clinicId,
                'question' => $question,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process your question: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Select which tools to run based on the analysis result.
     *
     * @return string[]
     */
    private function selectTools(array $analysis): array
    {
        $intent = $analysis['intent'];
        $tools = [];

        $intentToolMap = [
            'revenue' => ['get_revenue_report'],
            'expenses' => ['get_revenue_report'],
            'patients' => ['get_patients_summary'],
            'reservations' => ['get_appointments'],
            'cases' => ['get_cases'],
            'search_patient' => ['search_patient'],
            'analytics' => ['get_analytics'],
            'medical_question' => [],  // Handled by knowledge base search
        ];

        $tools = $intentToolMap[$intent] ?? [];

        // For analytics, also include revenue for comprehensive data
        if ($intent === 'analytics') {
            $tools[] = 'get_revenue_report';
            $tools[] = 'get_patients_summary';
        }

        return array_unique($tools);
    }

    /**
     * Run vector search and return original records.
     */
    private function runVectorSearch(string $clinicId, string $question): array
    {
        try {
            $queryVector = $this->embeddingService->generateEmbedding($question);
            $vectorString = '[' . implode(',', $queryVector) . ']';

            $results = DB::connection('pgsql_embeddings')
                ->select(
                    "SELECT id, clinic_id, table_name, record_id, content,
                            1 - (embedding <=> ?::vector) as similarity
                     FROM embeddings
                     WHERE clinic_id = ?
                       AND 1 - (embedding <=> ?::vector) >= 0.75
                     ORDER BY embedding <=> ?::vector
                     LIMIT 5",
                    [$vectorString, $clinicId, $vectorString, $vectorString]
                );

            if (empty($results)) {
                return [];
            }

            // Build simple records with content only (no extra DB queries for performance)
            return array_map(fn($r) => [
                'source' => $r->table_name,
                'record_id' => $r->record_id,
                'content' => $r->content,
                'similarity' => round($r->similarity, 4),
            ], $results);

        } catch (\Exception $e) {
            Log::warning('Vector search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Call OpenAI Chat API to generate the final answer.
     */
    private function callGPT(string $question, string $context, int $maxTokens = 1000): string
    {
        $apiKey = config('services.openai.api_key');
        $model = config('services.openai.chat_model', 'gpt-4o-mini');

        $systemMessage = 'You are a smart AI assistant for a dental/medical clinic management system called SmartClinic. '
            . 'You have direct access to real-time clinic data including: revenue/bills, expenses, patients, cases/treatments, and reservations/appointments. '
            . 'When real-time data context is provided, use it to give accurate, data-driven answers with specific numbers. '
            . 'Always present financial data clearly with totals. '
            . 'For analytics questions, provide insights and actionable recommendations. '
            . 'Be professional, friendly, and concise. Always respond in the same language the user asks in (Arabic or English). '
            . 'If data shows trends, explain possible reasons. '
            . 'The current date and time is: ' . now()->toDateTimeString() . '.';

        $userMessage = $question;
        if (!empty($context)) {
            $userMessage = "Based on the following real-time clinic data, answer this question accurately:\n\n"
                . "Question: {$question}\n\n"
                . "Clinic Data:\n{$context}";
        }

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        if (str_contains($model, 'gpt-5')) {
            $body['max_completion_tokens'] = $maxTokens;
        } else {
            $body['max_tokens'] = $maxTokens;
            $body['temperature'] = 0.3;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', $body);

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \Exception($data['error']['message'] ?? 'OpenAI API error');
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (empty($content)) {
            if (str_contains($model, 'gpt-5')) {
                Log::warning('gpt-5-nano returned empty content, retrying with gpt-4o-mini');
                $body['model'] = 'gpt-4o-mini';
                $body['max_tokens'] = $maxTokens;
                $body['temperature'] = 0.3;
                unset($body['max_completion_tokens']);

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', $body);

                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? 'I could not generate a response. Please try again.';
            } else {
                $content = 'I could not generate a response. Please try again.';
            }
        }

        return $content;
    }

    /**
     * Build the final response array.
     */
    private function buildResponse(string $question, string $answer, array $sources, array $analysis, float $startTime): array
    {
        return [
            'success' => true,
            'question' => $question,
            'answer' => $answer,
            'sources' => $sources,
            'analysis' => [
                'intent' => $analysis['intent'],
                'date_range' => $analysis['date_range']['type'],
            ],
            'answered_at' => now()->toDateTimeString(),
            'response_time_ms' => round((microtime(true) - $startTime) * 1000),
        ];
    }
}
