<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use Illuminate\Http\Request;

class AIController extends Controller
{
    private $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
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
                    'generate_reports' => 'Generate AI-powered reports based on database data',
                    'answer_questions' => 'Ask questions about your clinic data and get AI answers',
                    'analyze_trends' => 'Get insights about patient trends, revenue, and operations',
                    'financial_analysis' => 'Detailed financial reporting and analysis',
                    'operational_insights' => 'Analysis of clinic operations and doctor performance'
                ],
                'features' => [
                    'real_time_data' => 'Uses current database statistics',
                    'multiple_report_types' => 'General, financial, operational, and summary reports',
                    'natural_language' => 'Ask questions in natural language',
                    'context_aware' => 'AI understands medical clinic context',
                    'secure' => 'Only uses your clinic data, no external data sharing'
                ]
            ]
        ]);
    }
}
