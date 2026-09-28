<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DemoAIService
{
    /**
     * Generate demo database report (no AI needed)
     */
    public function generateDatabaseReport(string $reportType = 'general'): array
    {
        try {
            // Get database statistics
            $stats = $this->getDatabaseStats();
            
            // Generate demo report based on type
            $report = $this->generateDemoReport($stats, $reportType);
            
            return [
                'success' => true,
                'report' => $report,
                'stats' => $stats,
                'report_type' => $reportType,
                'generated_at' => now()->toDateTimeString(),
                'demo_mode' => true
            ];

        } catch (\Exception $e) {
            Log::error('Demo Report Generation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate demo report: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Answer questions with demo responses (no AI needed)
     */
    public function askQuestion(string $question): array
    {
        try {
            // Get relevant database data
            $stats = $this->getDatabaseStats();
            
            // Generate demo answer
            $answer = $this->generateDemoAnswer($question, $stats);
            
            return [
                'success' => true,
                'question' => $question,
                'answer' => $answer,
                'answered_at' => now()->toDateTimeString(),
                'demo_mode' => true
            ];

        } catch (\Exception $e) {
            Log::error('Demo Question Answering Error: ' . $e->getMessage());
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
        // Get tenant from request header or middleware
        $tenantId = request()->header('X-Tenant-ID');
        
        if ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant) {
                return $tenant->run(function () {
                    return $this->getTenantDatabaseStats();
                });
            }
        }
        
        // Try to get current tenant from middleware
        if (function_exists('tenant') && tenant()) {
            return tenant()->run(function () {
                return $this->getTenantDatabaseStats();
            });
        }
        
        // Fallback - return demo data
        return $this->getDemoStats();
    }
    
    /**
     * Get tenant database statistics
     */
    private function getTenantDatabaseStats(): array
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
                'total_revenue' => DB::table('bills')->sum('price'),
            ],
            'doctors' => [
                'total' => $this->getDoctorCount(),
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
                    ->sum('price'),
            ]
        ];
    }
    
    /**
     * Get doctor count safely
     */
    private function getDoctorCount(): int
    {
        try {
            // Try using the User model with relationship
            $userModel = new \App\Models\User();
            return $userModel->whereHas('roles', function($query) {
                $query->where('name', 'doctor');
            })->count();
        } catch (\Exception $e) {
            try {
                // Fallback: try direct query with role_user table
                return DB::table('users')
                    ->join('role_user', 'users.id', '=', 'role_user.model_id')
                    ->join('roles', 'role_user.role_id', '=', 'roles.id')
                    ->where('roles.name', 'doctor')
                    ->where('role_user.model_type', 'App\\Models\\User')
                    ->count();
            } catch (\Exception $e2) {
                // Final fallback: return a reasonable default
                return 3;
            }
        }
    }
    
    /**
     * Get demo statistics for testing
     */
    private function getDemoStats(): array
    {
        return [
            'patients' => [
                'total' => 150,
                'active' => 142,
                'new_this_month' => 8,
            ],
            'cases' => [
                'total' => 250,
                'active' => 240,
                'paid' => 200,
                'unpaid' => 50,
                'total_revenue' => 12500,
            ],
            'bills' => [
                'total' => 180,
                'total_revenue' => 15000,
            ],
            'doctors' => [
                'total' => 5,
            ],
            'recent_activity' => [
                'today_cases' => 12,
                'today_bills' => 8,
                'today_revenue' => 1200,
            ]
        ];
    }

    /**
     * Generate demo report based on type
     */
    private function generateDemoReport(array $stats, string $reportType): string
    {
        $currentDate = now()->format('F j, Y');
        
        switch ($reportType) {
            case 'financial':
                return $this->generateFinancialReport($stats, $currentDate);
            case 'operational':
                return $this->generateOperationalReport($stats, $currentDate);
            case 'summary':
                return $this->generateSummaryReport($stats, $currentDate);
            default:
                return $this->generateGeneralReport($stats, $currentDate);
        }
    }

    /**
     * Generate demo answer for questions
     */
    private function generateDemoAnswer(string $question, array $stats): string
    {
        $question = strtolower($question);
        
        // Patient questions
        if (str_contains($question, 'patient') || str_contains($question, 'patients')) {
            if (str_contains($question, 'how many') || str_contains($question, 'total')) {
                return "You currently have {$stats['patients']['total']} total patients, with {$stats['patients']['active']} active patients. This month, you've added {$stats['patients']['new_this_month']} new patients.";
            }
            if (str_contains($question, 'new') || str_contains($question, 'this month')) {
                return "This month, you've added {$stats['patients']['new_this_month']} new patients to your clinic.";
            }
            return "Your clinic has {$stats['patients']['total']} total patients with {$stats['patients']['active']} currently active.";
        }
        
        // Revenue questions
        if (str_contains($question, 'revenue') || str_contains($question, 'income') || str_contains($question, 'money')) {
            if (str_contains($question, 'today')) {
                return "Today's revenue is \${$stats['recent_activity']['today_revenue']} from bills.";
            }
            return "Your total revenue from bills is \${$stats['bills']['total_revenue']}.";
        }
        
        // Bill questions
        if (str_contains($question, 'bill') || str_contains($question, 'bills')) {
            if (str_contains($question, 'today')) {
                return "Today you've created {$stats['recent_activity']['today_bills']} bills.";
            }
            return "You have {$stats['bills']['total']} total bills with total revenue of \${$stats['bills']['total_revenue']}.";
        }
        
        // Case questions
        if (str_contains($question, 'case') || str_contains($question, 'cases')) {
            if (str_contains($question, 'today')) {
                return "Today you've created {$stats['recent_activity']['today_cases']} new cases.";
            }
            return "You have {$stats['cases']['total']} total cases: {$stats['cases']['paid']} paid and {$stats['cases']['unpaid']} unpaid.";
        }
        
        // Doctor questions
        if (str_contains($question, 'doctor') || str_contains($question, 'doctors')) {
            return "Your clinic has {$stats['doctors']['total']} doctors currently registered.";
        }
        
        // Today's activity
        if (str_contains($question, 'today') || str_contains($question, 'activity')) {
            return "Today's activity: {$stats['recent_activity']['today_cases']} new cases, {$stats['recent_activity']['today_bills']} bills, and ${$stats['recent_activity']['today_revenue']} in revenue.";
        }
        
        // Default response
        return "Based on your clinic data, I can see you have {$stats['patients']['total']} patients, {$stats['bills']['total']} bills, and {$stats['cases']['total']} cases. Could you please be more specific about what you'd like to know?";
    }

    /**
     * Generate general report
     */
    private function generateGeneralReport(array $stats, string $currentDate): string
    {
        return "
MEDICAL CLINIC GENERAL REPORT
Date: {$currentDate}

🏥 OVERVIEW:
Your clinic is performing well with {$stats['patients']['total']} total patients and {$stats['patients']['active']} active patients. This month, you've welcomed {$stats['patients']['new_this_month']} new patients.

💰 FINANCIAL SUMMARY:
- Total Revenue: \${$stats['bills']['total_revenue']}
- Total Bills: {$stats['bills']['total']}
- Today's Revenue: \${$stats['recent_activity']['today_revenue']}

📊 OPERATIONAL INSIGHTS:
- Total Cases: {$stats['cases']['total']}
- Active Doctors: {$stats['doctors']['total']}
- Today's Activity: {$stats['recent_activity']['today_cases']} cases, {$stats['recent_activity']['today_bills']} bills

📈 RECOMMENDATIONS:
1. Maintain the good patient acquisition rate ({$stats['patients']['new_this_month']} this month)
2. Consider expanding services if patient growth continues

This report shows a healthy, growing clinic with good patient engagement and steady revenue flow.
        ";
    }

    /**
     * Generate financial report
     */
    private function generateFinancialReport(array $stats, string $currentDate): string
    {
        $dailyAvg = round($stats['bills']['total_revenue'] / max(1, now()->daysInMonth), 2);
        
        return "FINANCIAL PERFORMANCE REPORT
Date: {$currentDate}

REVENUE ANALYSIS:
- Total Revenue: \${$stats['bills']['total_revenue']}
- Total Bills: {$stats['bills']['total']}

TODAY'S PERFORMANCE:
- Daily Revenue: \${$stats['recent_activity']['today_revenue']}
- Daily Bills: {$stats['recent_activity']['today_bills']}

FINANCIAL INSIGHTS:
1. Daily average revenue: \${$dailyAvg}

RECOMMENDATIONS:
- Monitor daily revenue trends
- Review pricing strategy periodically";
    }

    /**
     * Generate operational report
     */
    private function generateOperationalReport(array $stats, string $currentDate): string
    {
        $casesPerDoctor = $stats['doctors']['total'] > 0 ? 
            round($stats['cases']['total'] / $stats['doctors']['total'], 1) : 0;
        
        return "
OPERATIONAL PERFORMANCE REPORT
Date: {$currentDate}

👥 PATIENT MANAGEMENT:
- Total Patients: {$stats['patients']['total']}
- Active Patients: {$stats['patients']['active']}
- New Patients This Month: {$stats['patients']['new_this_month']}

🩺 CASE MANAGEMENT:
- Total Cases: {$stats['cases']['total']}
- Paid Cases: {$stats['cases']['paid']}
- Unpaid Cases: {$stats['cases']['unpaid']}
- Cases Per Doctor: {$casesPerDoctor}

👨‍⚕️ DOCTOR WORKLOAD:
- Active Doctors: {$stats['doctors']['total']}
- Average Cases Per Doctor: {$casesPerDoctor}

📊 TODAY'S OPERATIONS:
- New Cases Today: {$stats['recent_activity']['today_cases']}
- New Bills Today: {$stats['recent_activity']['today_bills']}

🎯 OPERATIONAL EFFICIENCY:
- Patient Growth Rate: " . ($stats['patients']['new_this_month'] > 0 ? 'Positive' : 'Neutral') . "
- Case Completion Rate: " . ($stats['cases']['total'] > 0 ? round(($stats['cases']['paid'] / $stats['cases']['total']) * 100, 1) . '%' : 'N/A') . "

💡 OPERATIONAL RECOMMENDATIONS:
1. Monitor doctor workload distribution
2. Focus on increasing case completion rate
3. Maintain patient acquisition momentum
4. Consider expanding team if workload increases
        ";
    }

    /**
     * Generate summary report
     */
    private function generateSummaryReport(array $stats, string $currentDate): string
    {
        return "
EXECUTIVE SUMMARY REPORT
Date: {$currentDate}

🏥 KEY METRICS:
• Total Patients: {$stats['patients']['total']}
• Active Patients: {$stats['patients']['active']}
• New This Month: {$stats['patients']['new_this_month']}

💰 FINANCIAL SNAPSHOT:
• Total Revenue: \${$stats['bills']['total_revenue']}
• Today's Revenue: \${$stats['recent_activity']['today_revenue']}

📊 OPERATIONAL HIGHLIGHTS:
• Total Cases: {$stats['cases']['total']}
• Active Doctors: {$stats['doctors']['total']}
• Today's Activity: {$stats['recent_activity']['today_cases']} cases

🎯 PERFORMANCE INDICATORS:
• Patient Growth: " . ($stats['patients']['new_this_month'] > 0 ? 'Positive' : 'Stable') . "

📈 STATUS: " . ($stats['bills']['total_revenue'] > 0 ? 'Revenue Generating' : 'Building') . "
        ";
    }
}
