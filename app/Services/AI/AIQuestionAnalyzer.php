<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIQuestionAnalyzer
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.analyzer_model', 'gpt-4o-mini');
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
     */
    private function buildSystemPrompt(): string
    {
        $today = now()->toDateString();

        return <<<PROMPT
You are an AI question analyzer for a dental/medical clinic management system.
Analyze the user's question and return a JSON object with this exact structure:

{
  "intent": "<one of: revenue, expenses, patients, reservations, cases, analytics, medical_question, search_patient, general>",
  "entities": {
    "patient_name": "<extracted patient name or empty string>",
    "doctor_name": "<extracted doctor name or empty string>"
  },
  "date_range": {
    "type": "<one of: today, yesterday, tomorrow, this_week, last_week, this_month, last_month, specific_date, custom, none>",
    "start": "<ISO date string or null>",
    "end": "<ISO date string or null>"
  },
  "needs_database": <true if the question requires real-time clinic database data>,
  "needs_vector_search": <true if the question might match embedded records like patient names or case details>,
  "needs_knowledge_base": <true if the question is about medical knowledge, procedures, or dental terminology>
}

Rules:
- "analytics" intent is for comparative/trend questions like "top doctor", "compare months", "growth rate", "why revenue decreased"
- "revenue" intent is for questions about payments received from auditor (bills table), case prices set by doctor, unpaid amounts, financial data, income
- "expenses" intent is for questions about clinic expenses, costs, spending
- "patients" intent is for questions about patient counts, demographics, registrations
- "reservations" intent is for questions about appointments, bookings, schedules
- "cases" intent is for questions about treatments, procedures, dental cases
- "search_patient" intent is when user is looking for a specific patient by name
- "medical_question" intent is for general medical/dental knowledge questions
- "general" intent is for greetings, help requests, or unrelated questions
- Today's date is: {$today}
- Support Arabic and English questions
- For date_range, if user says "today" set type to "today", if "this month" set type to "this_month", etc.
- If a specific date is mentioned, set type to "specific_date" and fill start/end
- If no date is mentioned, set type to "none"
- Return ONLY valid JSON, no extra text
PROMPT;
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
     */
    private function fallbackAnalysis(string $question): array
    {
        $q = mb_strtolower(trim($question));

        $intent = 'general';
        $needsDb = false;
        $needsVector = true;
        $needsKb = false;

        // Simple keyword-based fallback
        $intentMap = [
            'revenue' => ['revenue', 'income', 'bill', 'payment', 'paid', 'unpaid', 'إيرادات', 'فواتير', 'دخل', 'مدفوع', 'غير مدفوع', 'مبالغ'],
            'expenses' => ['expense', 'cost', 'spending', 'مصاريف', 'مصروف', 'نفقات', 'تكاليف'],
            'patients' => ['patient', 'patients', 'مرضى', 'مريض', 'مراجع'],
            'reservations' => ['appointment', 'reservation', 'schedule', 'booking', 'مواعيد', 'موعد', 'حجز'],
            'cases' => ['case', 'treatment', 'procedure', 'حالات', 'حالة', 'علاج'],
            'analytics' => ['top', 'compare', 'trend', 'growth', 'decrease', 'increase', 'best', 'worst', 'مقارنة', 'أفضل'],
        ];

        foreach ($intentMap as $intentName => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($q, $keyword)) {
                    $intent = $intentName;
                    $needsDb = true;
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
