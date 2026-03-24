<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use App\Services\VectorSearchService;
use App\Services\EmbeddingService;
use App\Models\Patient;
use App\Models\Reservation;
use App\Models\CaseModel;
use App\Models\Bill;
use App\Models\MedicalKnowledge;
use Illuminate\Http\Request;

class AIController extends Controller
{
    private AIService $aiService;
    private VectorSearchService $vectorSearchService;

    public function __construct(AIService $aiService, VectorSearchService $vectorSearchService)
    {
        $this->aiService = $aiService;
        $this->vectorSearchService = $vectorSearchService;
    }

    /**
     * AI Chat - Ask questions about clinic data using RAG.
     *
     * Flow:
     * 1. Convert question to embedding
     * 2. Search embeddings filtered by clinic_id
     * 3. Find top 5 nearest vectors
     * 4. Retrieve original records
     * 5. Send context to GPT for final answer
     */
    public function chat(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000',
        ]);

        $clinicId = tenant('id');

        if (!$clinicId || !tenant()) {
            return response()->json([
                'success' => false,
                'message' => 'Clinic context not found. Please ensure tenant is initialized.',
            ], 400);
        }

        // if (!tenant('has_ai_bot')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'AI Chatbot is not enabled for this clinic. Please contact support.',
        //         'message_ar' => 'خدمة الذكاء الاصطناعي غير مفعلة لهذه العيادة. يرجى التواصل مع الدعم الفني.',
        //     ], 403);
        // }

        $result = $this->vectorSearchService->chat($clinicId, $request->input('question'));

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process your question',
                'error' => $result['error'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Question answered successfully',
            'data' => [
                'question' => $result['question'],
                'answer' => $result['answer'],
                'sources' => $result['sources'],
                'answered_at' => $result['answered_at'],
            ],
        ]);
    }

    /**
     * Sync all existing records to embeddings.
     * Used for initial setup or full re-sync.
     */
    public function syncEmbeddings(Request $request)
    {
        $clinicId = tenant('id');

        if (!$clinicId) {
            return response()->json([
                'success' => false,
                'message' => 'Clinic context not found.',
            ], 400);
        }

        $embeddingService = app(EmbeddingService::class);

        $results = [
            'patients' => $embeddingService->bulkSync(Patient::class, $clinicId),
            'reservations' => $embeddingService->bulkSync(Reservation::class, $clinicId),
            'cases' => $embeddingService->bulkSync(CaseModel::class, $clinicId),
            'bills' => $embeddingService->bulkSync(Bill::class, $clinicId),
        ];

        $totalSynced = array_sum(array_column($results, 'synced'));
        $totalFailed = array_sum(array_column($results, 'failed'));

        return response()->json([
            'success' => true,
            'message' => "Synced {$totalSynced} records, {$totalFailed} failed.",
            'data' => $results,
        ]);
    }

    /**
     * Sync medical knowledge entries with embeddings.
     */
    public function syncMedicalKnowledge(Request $request)
    {
        $request->validate([
            'entries' => 'required|array|min:1',
            'entries.*.title' => 'required|string|max:255',
            'entries.*.content' => 'required|string',
        ]);

        $clinicId = tenant('id');
        if (!$clinicId) {
            return response()->json(['success' => false, 'message' => 'Clinic context not found.'], 400);
        }

        $embeddingService = app(EmbeddingService::class);
        $synced = 0;
        $failed = 0;

        foreach ($request->input('entries') as $entry) {
            try {
                $vector = $embeddingService->generateEmbedding($entry['content']);
                $vectorString = '[' . implode(',', $vector) . ']';

                \Illuminate\Support\Facades\DB::connection('pgsql_embeddings')->statement(
                    "INSERT INTO medical_knowledge (clinic_id, title, content, embedding, created_at, updated_at)
                     VALUES (?, ?, ?, ?::vector, NOW(), NOW())",
                    [$clinicId, $entry['title'], $entry['content'], $vectorString]
                );
                $synced++;
            } catch (\Exception $e) {
                $failed++;
                \Illuminate\Support\Facades\Log::warning('Medical knowledge sync failed', [
                    'title' => $entry['title'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Synced {$synced} entries, {$failed} failed.",
            'data' => ['synced' => $synced, 'failed' => $failed],
        ]);
    }

    /**
     * Generate AI database report
     */
    public function generateReport(Request $request)
    {
        $request->validate([
            'report_type' => 'sometimes|in:general,financial,operational,summary'
        ]);

        $reportType = $request->input('report_type', 'general');

        $result = $this->aiService->generateDatabaseReport($reportType);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'AI report generated successfully' : 'Failed to generate report',
            'data' => $result['success'] ? [
                'report' => $result['report'],
                'stats' => $result['stats'],
                'report_type' => $result['report_type'],
                'generated_at' => $result['generated_at']
            ] : null,
            'error' => $result['success'] ? null : $result['error']
        ], $result['success'] ? 200 : 500);
    }

    /**
     * Ask AI a question about the database
     */
    public function askQuestion(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000'
        ]);

        // if (!tenant() || !tenant('has_ai_bot')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'AI Chatbot is not enabled for this clinic. Please contact support.',
        //         'message_ar' => 'خدمة الذكاء الاصطناعي غير مفعلة لهذه العيادة. يرجى التواصل مع الدعم الفني.',
        //     ], 403);
        // }

        $question = $request->input('question');

        $result = $this->aiService->askQuestion($question);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Question answered successfully' : 'Failed to answer question',
            'data' => $result['success'] ? [
                'question' => $result['question'],
                'answer' => $result['answer'],
                'answered_at' => $result['answered_at']
            ] : null,
            'error' => $result['success'] ? null : $result['error']
        ], $result['success'] ? 200 : 500);
    }

    /**
     * Get available report types
     */
    public function getReportTypes()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'report_types' => [
                    'general' => 'Comprehensive clinic report with insights and recommendations',
                    'financial' => 'Financial focus on revenue, payments, and financial health',
                    'operational' => 'Operational focus on patient flow and doctor workload',
                    'summary' => 'Brief executive summary report'
                ]
            ]
        ]);
    }

    /**
     * Get AI capabilities
     */
    public function getCapabilities()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'capabilities' => [
                    'ai_chat' => 'Smart AI assistant with natural language understanding and real-time clinic data analysis',
                    'ai_intent_analysis' => 'AI-powered question analysis using GPT-4o-mini for accurate intent detection',
                    'analytics' => 'Advanced analytics: top doctors, revenue trends, month comparisons, growth metrics',
                    'hybrid_search' => 'Hybrid search combining vector similarity, database queries, and knowledge base',
                    'medical_knowledge' => 'Medical knowledge base with vector search for dental/medical Q&A',
                    'sync_embeddings' => 'Sync all clinic data for AI-powered search',
                ],
                'features' => [
                    'smart_pipeline' => 'Analyze → Tools → Vector Search → Context → GPT → Answer',
                    'tool_based' => '8 specialized tools for revenue, patients, appointments, cases, analytics, and more',
                    'vector_search' => 'pgvector similarity search with 0.75 threshold',
                    'context_builder' => 'Intelligent context merging from multiple data sources',
                    'auto_sync' => 'Embeddings auto-update when records change',
                    'multi_tenant' => 'Each clinic\'s data is isolated and secure',
                    'caching' => 'Performance caching for heavy queries and analytics',
                    'multilingual' => 'Full Arabic and English support',
                ]
            ]
        ]);
    }
}
