<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIQuestionAnalyzer
{
    private string $apiKey;
    private string $model;
    private SpecialtyChatConfig $specialtyConfig;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.analyzer_model', 'gpt-4o-mini');
        $this->specialtyConfig = new SpecialtyChatConfig();
    }

    /**
     * Analyze a user question using AI and return structured intent data.
     *
     * @return array{
     *   intent: string,
     *   entities: array{patient_name: string, doctor_name: string},
     *   date_range: array{type: string, start: string|null, end: string|null},
     *   needs_database: bool,
     *   needs_vector_search: bool,
     *   needs_knowledge_base: bool
     * }
     */
    public function analyze(string $question): array
    {
        // FAST PATH: Skip the OpenAI call entirely for simple greetings
        $greetingResult = $this->detectGreeting($question);
        if ($greetingResult !== null) {
            return $greetingResult;
        }



        try {
            $systemPrompt = $this->buildSystemPrompt();
            $userPrompt = "Analyze this question: \"{$question}\"";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(6)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 300,
                'temperature' => 0.0,
                'response_format' => ['type' => 'json_object'],
            ]);

            $data = $response->json();

            if (isset($data['error'])) {
                throw new \Exception($data['error']['message'] ?? 'OpenAI API error');
            }

            $content = $data['choices'][0]['message']['content'] ?? null;

            if (empty($content)) {
                return $this->fallbackAnalysis($question);
            }

            $parsed = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('AIQuestionAnalyzer: Failed to parse JSON response', [
                    'content' => $content,
                ]);
                return $this->fallbackAnalysis($question);
            }

            $result = $this->normalizeResult($parsed);

            return $result;

        } catch (\Exception $e) {
            Log::error('AIQuestionAnalyzer error: ' . $e->getMessage());
            return $this->fallbackAnalysis($question);
        }
    }

    /**
     * Fast greeting detection — avoids the OpenAI API call entirely.
     * Returns null if the question is NOT a greeting.
     */
    private function detectGreeting(string $question): ?array
    {
        $q = mb_strtolower(trim($question));

        $greetings = [
            'hello', 'hi', 'hey', 'how are you', 'good morning', 'good evening',
            'good afternoon', 'what can you do', 'who are you', 'help',
            'مرحبا', 'اهلا', 'السلام عليكم', 'كيف حالك', 'شلونك', 'هلو',
            'صباح الخير', 'مساء الخير', 'شنو تگدر تسوي', 'ساعدني',
        ];

        foreach ($greetings as $greeting) {
            if (str_contains($q, $greeting)) {
                return [
                    'intent' => 'general',
                    'entities' => ['patient_name' => '', 'doctor_name' => ''],
                    'date_range' => ['type' => 'none', 'start' => null, 'end' => null],
                    'needs_database' => false,
                    'needs_vector_search' => false,
                    'needs_knowledge_base' => false,
                ];
            }
        }

        return null;
    }

    /**
     * Build the system prompt that instructs GPT to return structured JSON.
     * Specialty-aware via SpecialtyChatConfig.
     */
    private function buildSystemPrompt(): string
    {
        return $this->specialtyConfig->buildAnalyzerSystemPrompt();
    }

    /**
     * Normalize the parsed result to ensure all expected keys exist.
     */
    private function normalizeResult(array $parsed): array
    {
        return [
            'intent' => $parsed['intent'] ?? 'general',
            'entities' => [
                'patient_name' => $parsed['entities']['patient_name'] ?? '',
                'doctor_name' => $parsed['entities']['doctor_name'] ?? '',
            ],
            'date_range' => [
                'type' => $parsed['date_range']['type'] ?? 'none',
                'start' => $parsed['date_range']['start'] ?? null,
                'end' => $parsed['date_range']['end'] ?? null,
            ],
            'needs_database' => (bool) ($parsed['needs_database'] ?? false),
            'needs_vector_search' => (bool) ($parsed['needs_vector_search'] ?? false),
            'needs_knowledge_base' => (bool) ($parsed['needs_knowledge_base'] ?? false),
        ];
    }

    /**
     * Fallback analysis using simple keyword matching when OpenAI is unavailable.
     * Includes specialty-specific keywords.
     */
    private function fallbackAnalysis(string $question): array
    {
        $q = mb_strtolower(trim($question));

        $intent = 'general';
        $needsDb = false;
        $needsVector = true;
        $needsKb = false;

        // Base keywords + specialty-specific case keywords
        $specialtyCaseKeywords = $this->specialtyConfig->caseKeywords();
        $baseCaseKeywords = ['case', 'treatment', 'procedure', 'حالات', 'حالة', 'علاج'];

        // Simple keyword-based fallback
        $intentMap = [
            'revenue' => ['revenue', 'income', 'bill', 'payment', 'paid', 'unpaid', 'إيرادات', 'فواتير', 'دخل', 'مدفوع', 'غير مدفوع', 'مبالغ'],
            'expenses' => ['expense', 'cost', 'spending', 'مصاريف', 'مصروف', 'نفقات', 'تكاليف'],
            'patients' => ['patient', 'patients', 'مرضى', 'مريض', 'مراجع'],
            'reservations' => ['appointment', 'reservation', 'schedule', 'booking', 'مواعيد', 'موعد', 'حجز'],
            'cases' => array_merge($baseCaseKeywords, $specialtyCaseKeywords),
            'analytics' => ['top', 'compare', 'trend', 'growth', 'decrease', 'increase', 'best', 'worst', 'مقارنة', 'أفضل'],
            'medical_question' => $this->specialtyConfig->medicalKeywords(),
        ];

        foreach ($intentMap as $intentName => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($q, $keyword)) {
                    $intent = $intentName;
                    if ($intentName === 'medical_question') {
                        $needsDb = false;
                        $needsKb = true;
                    } else {
                        $needsDb = true;
                    }
                    break 2;
                }
            }
        }

        // Detect greetings
        $greetings = ['hello', 'hi', 'hey', 'help', 'مرحبا', 'اهلا', 'السلام عليكم'];
        foreach ($greetings as $greeting) {
            if (str_contains($q, $greeting)) {
                $intent = 'general';
                $needsDb = false;
                $needsVector = false;
                break;
            }
        }

        return [
            'intent' => $intent,
            'entities' => ['patient_name' => '', 'doctor_name' => ''],
            'date_range' => ['type' => 'none', 'start' => null, 'end' => null],
            'needs_database' => $needsDb,
            'needs_vector_search' => $needsVector,
            'needs_knowledge_base' => $needsKb,
        ];
    }
}
