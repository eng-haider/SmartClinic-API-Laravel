<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use App\Services\VectorSearchService;
use App\Services\EmbeddingService;
use App\Models\Patient;
use App\Models\Reservation;
use App\Models\CaseModel;
use App\Models\Bill;
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

        if (!$clinicId) {
            return response()->json([
                'success' => false,
                'message' => 'Clinic context not found. Please ensure tenant is initialized.',
            ], 400);
        }

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
                    'ai_chat' => 'Ask natural language questions about your clinic data using AI vector search',
                    'generate_reports' => 'Generate AI-powered reports based on database data',
                    'answer_questions' => 'Ask questions about your clinic data and get AI answers',
                    'analyze_trends' => 'Get insights about patient trends, revenue, and operations',
                    'sync_embeddings' => 'Sync all clinic data for AI-powered search',
                ],
                'features' => [
                    'vector_search' => 'Uses pgvector similarity search for accurate results',
                    'rag_pipeline' => 'Retrieval-Augmented Generation for context-aware answers',
                    'auto_sync' => 'Embeddings auto-update when records change',
                    'multi_tenant' => 'Each clinic\'s data is isolated and secure',
                    'real_time_data' => 'Uses current database statistics',
                ]
            ]
        ]);
    }
}
