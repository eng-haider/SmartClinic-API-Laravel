<?php

namespace App\Services;

use App\Models\Embedding;
use App\Models\Patient;
use App\Models\Reservation;
use App\Models\CaseModel;
use App\Models\Bill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI;

class VectorSearchService
{
    private $client;
    private EmbeddingService $embeddingService;

    public function __construct(EmbeddingService $embeddingService)
    {
        $this->client = OpenAI::client(config('services.openai.api_key'));
        $this->embeddingService = $embeddingService;
    }

    /**
     * Search for the most similar embeddings to a query string.
     *
     * Uses pgvector cosine distance (<=>) for fast similarity search,
     * filtered by clinic_id for multi-tenant isolation.
     *
     * @param string $clinicId  The clinic/tenant ID
     * @param string $query     The search query text
     * @param int    $limit     Number of results to return
     * @return Collection       Collection of matching embedding records
     */
    public function searchSimilar(string $clinicId, string $query, int $limit = 5): Collection
    {
        // Convert query to embedding vector
        $queryVector = $this->embeddingService->generateEmbedding($query);
        $vectorString = '[' . implode(',', $queryVector) . ']';

        // Perform cosine similarity search using pgvector
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
     *
     * @param Collection $embeddingResults  Results from searchSimilar()
     * @return array                        Array of original records with metadata
     */
    public function fetchOriginalRecords(Collection $embeddingResults): array
    {
        $records = [];

        // Model mapping: table_name => Model class
        $modelMap = [
            'patients' => Patient::class ,
            'reservations' => Reservation::class ,
            'cases' => CaseModel::class ,
            'bills' => Bill::class ,
        ];

        foreach ($embeddingResults as $embedding) {
            $modelClass = $modelMap[$embedding->table_name] ?? null;

            if (!$modelClass) {
                // Unknown table, use the content from embedding directly
                $records[] = [
                    'source' => $embedding->table_name,
                    'record_id' => $embedding->record_id,
                    'content' => $embedding->content,
                    'similarity' => round($embedding->similarity, 4),
                ];
                continue;
            }

            try {
                // Fetch original record with relationships
                $query = $modelClass::query()->where('id', $embedding->record_id);

                // Eager load relationships based on model type
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
                }
                else {
                    // Record was deleted but embedding still exists
                    $records[] = [
                        'source' => $embedding->table_name,
                        'record_id' => $embedding->record_id,
                        'content' => $embedding->content,
                        'similarity' => round($embedding->similarity, 4),
                        'note' => 'Original record no longer exists',
                    ];
                }
            }
            catch (\Exception $e) {
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

    /**
     * Full RAG chat flow:
     * 1. Convert question to embedding
     * 2. Search top 5 most similar vectors for the clinic
     * 3. Fetch original records from tenant DB
     * 4. Build context and send to GPT
     * 5. Return AI response
     *
     * @param string $clinicId  The clinic/tenant ID
     * @param string $question  The user's question
     * @return array            Response with answer, sources, etc.
     */
    /**
     * Check if the question is a simple greeting that doesn't need database search.
     */
    private function isSimpleGreeting(string $question): bool
    {
        $greetings = [
            'hello', 'hi', 'hey', 'how are you', 'good morning', 'good evening',
            'good afternoon', 'what can you do', 'who are you', 'help',
            'مرحبا', 'اهلا', 'السلام عليكم', 'كيف حالك', 'شلونك', 'هلو',
        ];
        $lower = mb_strtolower(trim($question));
        foreach ($greetings as $greeting) {
            if (str_contains($lower, $greeting)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the user is asking about reservations or appointments.
     */
    private function isAskingAboutReservations(string $question): bool
    {
        $question = mb_strtolower(trim($question));
        
        $appointmentWords = [
            'appointment', 'appointments', 'reservation', 'reservations', 'schedule',
            'مواعيد', 'موعد', 'حجوزات', 'حجز', 'جدول', 'مرضى',
        ];
        
        foreach ($appointmentWords as $word) {
            if (str_contains($question, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract a target date from the user's question.
     * Supports: today, tomorrow, yesterday, specific day numbers, and full dates.
     *
     * @return \Carbon\Carbon|null  The parsed date or null if no date detected
     */
    private function extractDateFromQuestion(string $question): ?\Carbon\Carbon
    {
        $question = mb_strtolower(trim($question));

        // Today keywords
        $todayWords = ['today', 'اليوم', 'النهارده', 'النهاردة'];
        foreach ($todayWords as $word) {
            if (str_contains($question, $word)) {
                return now()->startOfDay();
            }
        }

        // Tomorrow keywords
        $tomorrowWords = ['tomorrow', 'غدا', 'غداً', 'باچر', 'باجر', 'بكرة', 'بكره'];
        foreach ($tomorrowWords as $word) {
            if (str_contains($question, $word)) {
                return now()->addDay()->startOfDay();
            }
        }

        // Yesterday keywords
        $yesterdayWords = ['yesterday', 'أمس', 'امس', 'البارحة', 'البارحه'];
        foreach ($yesterdayWords as $word) {
            if (str_contains($question, $word)) {
                return now()->subDay()->startOfDay();
            }
        }

        // Full date patterns: 2026-03-25, 25/03/2026, 25-03-2026
        if (preg_match('/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $question, $m)) {
            try {
                return \Carbon\Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
            } catch (\Exception $e) {}
        }
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $question, $m)) {
            try {
                return \Carbon\Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();
            } catch (\Exception $e) {}
        }

        // Day number pattern: "يوم 25", "day 25", "25 من", "25 لهذا"
        // Match Arabic and English day references
        if (preg_match('/(?:يوم|day)\s*(\d{1,2})/u', $question, $m)) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= 31) {
                try {
                    return now()->startOfMonth()->day($day)->startOfDay();
                } catch (\Exception $e) {}
            }
        }

        // Bare number followed by contextual Arabic words like "لهاذا", "من هذا", "الشهر"
        if (preg_match('/(\d{1,2})\s*(?:لهاذا|لهذا|من هذا|من هاذا|الشهر|this month)/u', $question, $m)) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= 31) {
                try {
                    return now()->startOfMonth()->day($day)->startOfDay();
                } catch (\Exception $e) {}
            }
        }

        // "مواعيد 25" or "حجوزات 25" - appointment word followed by a number
        if (preg_match('/(?:مواعيد|موعد|حجوزات|حجز)\s*(\d{1,2})/u', $question, $m)) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= 31) {
                try {
                    return now()->startOfMonth()->day($day)->startOfDay();
                } catch (\Exception $e) {}
            }
        }

        return null;
    }

    public function chat(string $clinicId, string $question): array
    {
        try {
            $apiKey = config('services.openai.api_key');
            // Hardcode gpt-4o-mini because gpt-5-nano takes ~10 seconds to fail before retrying, which makes it feel slow
            $model = 'gpt-4o-mini';
            $systemMessage = 'You are a helpful AI assistant for a dental/medical clinic management system called SmartClinic. '
                . 'You can greet users, answer general questions, and help with clinic-related queries. '
                . 'When clinic data context is provided, use it to answer questions accurately. '
                . 'If no relevant clinic data is provided, still respond helpfully. '
                . 'Be professional, friendly, and concise. Support both Arabic and English. '
                . 'The current date and time is: ' . now()->toDateTimeString() . '.';

            // For simple greetings, skip the expensive embedding search
            if ($this->isSimpleGreeting($question)) {
                $answer = $this->callOpenAI($apiKey, $model, $systemMessage, $question, 300);

                return [
                    'success' => true,
                    'question' => $question,
                    'answer' => $answer,
                    'sources' => [],
                    'answered_at' => now()->toDateTimeString(),
                ];
            }

            // Step 1 & 2: Search similar embeddings
            $similarEmbeddings = $this->searchSimilar($clinicId, $question, 5);

            // Filter out low-similarity results (below 0.3 threshold)
            $relevantEmbeddings = $similarEmbeddings->filter(function ($item) {
                return $item->similarity >= 0.3;
            });

            // Build context only from relevant results
            $originalRecords = [];
            $context = '';
            if ($relevantEmbeddings->isNotEmpty()) {
                $originalRecords = $this->fetchOriginalRecords($relevantEmbeddings);
                $context = $this->buildContext($originalRecords);
            }

            // Special handling: fetch reservations for a specific date if detected
            if ($this->isAskingAboutReservations($question)) {
                $targetDate = $this->extractDateFromQuestion($question);

                if ($targetDate) {
                    $dateString = $targetDate->toDateString();
                    $dateReservations = Reservation::byDate($dateString)
                        ->with(['patient:id,name', 'doctor:id,name', 'status:id,name', 'reservationType:id,name'])
                        ->get();

                    $label = $targetDate->isToday() ? "Today's" : $dateString;

                    if ($dateReservations->isNotEmpty()) {
                        $dateContext = "--- Reservations for {$label} ---\n";
                        foreach ($dateReservations as $res) {
                            $patientName = $res->patient->name ?? 'Unknown';
                            $doctorName = $res->doctor->name ?? 'Unknown';
                            $statusName = $res->status->name ?? 'Unknown';
                            $typeName = $res->reservationType->name ?? '';
                            $time = $res->reservation_from_time . ' - ' . $res->reservation_to_time;
                            $dateContext .= "- {$time}: Patient {$patientName} with Dr. {$doctorName} (Status: {$statusName}" . ($typeName ? ", Type: {$typeName}" : "") . ")\n";
                        }
                        $context = $dateContext . "\n" . $context;
                    } else {
                        $context = "--- Reservations for {$label} ---\nNo reservations found for {$dateString}.\n\n" . $context;
                    }
                }
            }

            // Build the user message
            $userMessage = $question;
            if (!empty($context)) {
                $userMessage = "Based on the following clinic records, answer this question:\n\n"
                    . "Question: {$question}\n\n"
                    . "Clinic Data Context:\n{$context}";
            }

            // Send to GPT for final answer
            $answer = $this->callOpenAI($apiKey, $model, $systemMessage, $userMessage, 1000);

            // Build source references
            $sources = array_map(function ($record) {
                return [
                'type' => $record['source'],
                'record_id' => $record['record_id'],
                'similarity' => $record['similarity'],
                ];
            }, $originalRecords);

            return [
                'success' => true,
                'question' => $question,
                'answer' => $answer,
                'sources' => $sources,
                'answered_at' => now()->toDateTimeString(),
            ];

        }
        catch (\Exception $e) {
            Log::error('AI Chat Error: ' . $e->getMessage(), [
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
     * Call OpenAI Chat API directly using Laravel Http client.
     */
    private function callOpenAI(string $apiKey, string $model, string $systemMessage, string $userMessage, int $maxTokens = 1000): string
    {
        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        // gpt-5-nano uses different parameter names
        if (str_contains($model, 'gpt-5')) {
            $body['max_completion_tokens'] = $maxTokens;
        // gpt-5-nano does not support custom temperature
        }
        else {
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

        // Handle both null AND empty string
        if (empty($content)) {
            // If gpt-5-nano returns empty, fallback to gpt-4o-mini
            if (str_contains($model, 'gpt-5')) {
                Log::warning('gpt-5-nano returned empty content, retrying with gpt-4o-mini');
                return $this->callOpenAI($apiKey, 'gpt-4o-mini', $systemMessage, $userMessage, $maxTokens);
            }
            return 'I could not generate a response. Please try again.';
        }

        return $content;
    }

    /**
     * Build a text context from the fetched original records.
     */
    private function buildContext(array $records): string
    {
        $contextParts = [];

        foreach ($records as $index => $record) {
            $num = $index + 1;
            $source = ucfirst(str_replace('_', ' ', $record['source']));
            $contextParts[] = "--- Record {$num} ({$source}, ID: {$record['record_id']}, Similarity: {$record['similarity']}) ---";
            $contextParts[] = $record['content'];
            $contextParts[] = '';
        }

        return implode("\n", $contextParts);
    }
}