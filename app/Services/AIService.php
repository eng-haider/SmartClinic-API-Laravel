<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenAI;

class AIService
{
    private $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.api_key'));
    }

    /**
     * Generate database report using AI
     */
    public function generateDatabaseReport(string $reportType = 'general'): array
    {
        try {
            // Get database statistics
            $stats = $this->getDatabaseStats();
            
            // Build context for AI
            $context = $this->buildDatabaseContext($stats);
            
            // Generate report based on type
            $prompt = $this->buildReportPrompt($context, $reportType);
            
            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a medical clinic database analyst. Provide detailed, professional reports based on the given data.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1500,
                'temperature' => 0.7,
            ]);

            return [
                'success' => true,
                'report' => $response->choices[0]->message->content,
                'stats' => $stats,
                'report_type' => $reportType,
                'generated_at' => now()->toDateTimeString()
            ];

        } catch (\Exception $e) {
            Log::error('AI Report Generation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate AI report: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Answer questions about the database
     */
    public function askQuestion(string $question): array
    {
        try {
            // Get relevant database data
            $context = $this->getRelevantDataForQuestion($question);
            
            // Build prompt with context
            $prompt = $this->buildQuestionPrompt($context, $question);
            
            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a medical clinic database assistant. Answer questions based on the provided database data. Be accurate and helpful.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.3,
            ]);

            return [
                'success' => true,
                'question' => $question,
                'answer' => $response->choices[0]->message->content,
                'context_used' => $context,
                'answered_at' => now()->toDateTimeString()
            ];

        } catch (\Exception $e) {
            Log::error('AI Question Answering Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to answer question: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get database statistics
     */
    private function getDatabaseStats(): array
    {
        return [
            'patients' => [
                'total' => DB::table('patients')->count(),
                'active' => DB::table('patients')->whereNull('deleted_at')->count(),
                'new_this_month' => DB::table('patients')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ],
            'cases' => [
                'total' => DB::table('cases')->count(),
                'active' => DB::table('cases')->whereNull('deleted_at')->count(),
                'paid' => DB::table('cases')->where('is_paid', true)->count(),
                'unpaid' => DB::table('cases')->where('is_paid', false)->count(),
                'total_revenue' => DB::table('cases')->where('is_paid', true)->sum('price'),
            ],
            'bills' => [
                'total' => DB::table('bills')->count(),
                'paid' => DB::table('bills')->where('is_paid', true)->count(),
                'unpaid' => DB::table('bills')->where('is_paid', false)->count(),
                'total_revenue' => DB::table('bills')->where('is_paid', true)->sum('price'),
                'total_outstanding' => DB::table('bills')->where('is_paid', false)->sum('price'),
            ],
            'doctors' => [
                'total' => DB::table('users')->whereHas('roles', function($query) {
                    $query->where('name', 'doctor');
                })->count(),
            ],
            'recent_activity' => [
                'today_cases' => DB::table('cases')
                    ->whereDate('created_at', today())
                    ->count(),
                'today_bills' => DB::table('bills')
                    ->whereDate('created_at', today())
                    ->count(),
                'today_revenue' => DB::table('bills')
                    ->whereDate('created_at', today())
                    ->where('is_paid', true)
                    ->sum('price'),
            ]
        ];
    }

    /**
     * Build database context for AI
     */
    private function buildDatabaseContext(array $stats): string
    {
        return "
MEDICAL CLINIC DATABASE STATISTICS:

PATIENTS:
- Total Patients: {$stats['patients']['total']}
- Active Patients: {$stats['patients']['active']}
- New Patients This Month: {$stats['patients']['new_this_month']}

CASES:
- Total Cases: {$stats['cases']['total']}
- Active Cases: {$stats['cases']['active']}
- Paid Cases: {$stats['cases']['paid']}
- Unpaid Cases: {$stats['cases']['unpaid']}
- Total Revenue from Cases: ${$stats['cases']['total_revenue']}

BILLS:
- Total Bills: {$stats['bills']['total']}
- Paid Bills: {$stats['bills']['paid']}
- Unpaid Bills: {$stats['bills']['unpaid']}
- Total Revenue from Bills: ${$stats['bills']['total_revenue']}
- Total Outstanding Amount: ${$stats['bills']['total_outstanding']}

DOCTORS:
- Total Doctors: {$stats['doctors']['total']}

TODAY'S ACTIVITY:
- Cases Today: {$stats['recent_activity']['today_cases']}
- Bills Today: {$stats['recent_activity']['today_bills']}
- Revenue Today: ${$stats['recent_activity']['today_revenue']}

Current Date: " . now()->toDateString() . "
        ";
    }

    /**
     * Build report prompt
     */
    private function buildReportPrompt(string $context, string $reportType): string
    {
        $prompts = [
            'general' => "Generate a comprehensive medical clinic report based on the following data. Include insights, trends, and recommendations.",
            'financial' => "Generate a financial report for the medical clinic based on the following data. Focus on revenue, payments, and financial health.",
            'operational' => "Generate an operational report for the medical clinic based on the following data. Focus on patient flow, case management, and doctor workload.",
            'summary' => "Generate a brief executive summary report for the medical clinic based on the following data. Keep it concise but informative."
        ];

        $prompt = $prompts[$reportType] ?? $prompts['general'];
        
        return $prompt . "\n\n" . $context;
    }

    /**
     * Get relevant data for specific questions
     */
    private function getRelevantDataForQuestion(string $question): string
    {
        $question = strtolower($question);
        $context = $this->buildDatabaseContext($this->getDatabaseStats());

        // Add specific data based on question type
        if (str_contains($question, 'patient') || str_contains($question, 'patients')) {
            $context .= "\n\nRECENT PATIENTS:\n";
            $recentPatients = DB::table('patients')
                ->select('name', 'phone', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
            
            foreach ($recentPatients as $patient) {
                $context .= "- {$patient->name} ({$patient->phone}) - Created: {$patient->created_at}\n";
            }
        }

        if (str_contains($question, 'doctor') || str_contains($question, 'doctors')) {
            $context .= "\n\nDOCTOR PERFORMANCE:\n";
            $doctorStats = DB::table('cases')
                ->join('users', 'cases.doctor_id', '=', 'users.id')
                ->select('users.name', DB::raw('COUNT(*) as case_count'), DB::raw('SUM(price) as total_revenue'))
                ->groupBy('users.id', 'users.name')
                ->orderBy('case_count', 'desc')
                ->limit(5)
                ->get();
            
            foreach ($doctorStats as $doctor) {
                $context .= "- Dr. {$doctor->name}: {$doctor->case_count} cases, ${$doctor->total_revenue} revenue\n";
            }
        }

        if (str_contains($question, 'revenue') || str_contains($question, 'income') || str_contains($question, 'money')) {
            $context .= "\n\nREVENUE BREAKDOWN:\n";
            $revenueByMonth = DB::table('bills')
                ->select(DB::raw('MONTH(created_at) as month'), DB::raw('SUM(price) as revenue'))
                ->where('is_paid', true)
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy(DB::raw('MONTH(created_at)'))
                ->orderBy('month')
                ->get();
            
            foreach ($revenueByMonth as $revenue) {
                $monthName = date('F', mktime(0, 0, 0, $revenue->month, 1));
                $context .= "- {$monthName}: ${$revenue->revenue}\n";
            }
        }

        return $context;
    }

    /**
     * Build question prompt
     */
    private function buildQuestionPrompt(string $context, string $question): string
    {
        return "Based on the following medical clinic database data, answer this question: '{$question}'\n\n{$context}\n\nPlease provide a clear, accurate answer based on the data provided.";
    }
}
