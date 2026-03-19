<?php

namespace App\Http\Controllers;

use App\Services\DemoAIService;
use Illuminate\Http\Request;

class DemoAIController extends Controller
{
    private $demoAIService;

    public function __construct(DemoAIService $demoAIService)
    {
        $this->demoAIService = $demoAIService;
    }

    /**
     * Generate demo database report (FREE - no AI needed)
     */
    public function generateReport(Request $request)
    {
        $request->validate([
            'report_type' => 'sometimes|in:general,financial,operational,summary'
        ]);

        $reportType = $request->input('report_type', 'general');
        
        $result = $this->demoAIService->generateDatabaseReport($reportType);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Demo report generated successfully' : 'Failed to generate report',
            'data' => $result['success'] ? [
                'report' => $result['report'],
                'stats' => $result['stats'],
                'report_type' => $result['report_type'],
                'generated_at' => $result['generated_at'],
                'demo_mode' => $result['demo_mode']
            ] : null,
            'error' => $result['success'] ? null : $result['error']
        ], $result['success'] ? 200 : 500);
    }

    /**
     * Ask demo AI a question (FREE - no AI needed)
     */
    public function askQuestion(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000'
        ]);

        $question = $request->input('question');
        
        $result = $this->demoAIService->askQuestion($question);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Question answered successfully' : 'Failed to answer question',
            'data' => $result['success'] ? [
                'question' => $result['question'],
                'answer' => $result['answer'],
                'answered_at' => $result['answered_at'],
                'demo_mode' => $result['demo_mode']
            ] : null,
            'error' => $result['success'] ? null : $result['error']
        ], $result['success'] ? 200 : 500);
    }

    /**
     * Get demo capabilities
     */
    public function getCapabilities()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'capabilities' => [
                    'generate_reports' => 'Generate demo reports based on database data (FREE)',
                    'answer_questions' => 'Ask questions about your clinic data (FREE)',
                    'analyze_trends' => 'Get insights about patient trends, revenue, and operations',
                    'financial_analysis' => 'Detailed financial reporting and analysis',
                    'operational_insights' => 'Analysis of clinic operations and doctor performance'
                ],
                'features' => [
                    'real_time_data' => 'Uses current database statistics',
                    'multiple_report_types' => 'General, financial, operational, and summary reports',
                    'natural_language' => 'Ask questions in natural language',
                    'context_aware' => 'Understands medical clinic context',
                    'completely_free' => 'No API keys needed, no costs involved',
                    'demo_mode' => 'Smart responses based on your actual data'
                ],
                'demo_mode' => true,
                'cost' => 'FREE - No external API calls',
                'setup' => 'No configuration required'
            ]
        ]);
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
                ],
                'demo_mode' => true
            ]
        ]);
    }
}
