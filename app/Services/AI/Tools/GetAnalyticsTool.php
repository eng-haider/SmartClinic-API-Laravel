<?php

namespace App\Services\AI\Tools;

use App\Models\Bill;
use App\Models\CaseModel;
use App\Models\Patient;
use App\Models\Reservation;
use App\Models\ClinicExpense;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAnalyticsTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_analytics';
    }

    public function description(): string
    {
        return 'Gets analytics data: top doctors, revenue trends, comparisons, growth metrics, and insights.';
    }

    public function execute(array $params): string
    {
        $dateRange = $params['date_range'] ?? ['type' => 'none'];
        $lines = [];

        $lines[] = "--- Clinic Analytics Dashboard ---";
        $lines[] = "Generated: " . now()->toDateTimeString();
        $lines[] = "";

        // Top doctors by revenue (this month)
        $lines[] = $this->getTopDoctors();
        $lines[] = "";

        // Revenue trend (last 6 months)
        $lines[] = $this->getRevenueTrend();
        $lines[] = "";

        // Month comparison (this vs last)
        $lines[] = $this->getMonthComparison();
        $lines[] = "";

        // Patient growth
        $lines[] = $this->getPatientGrowth();
        $lines[] = "";

        // Appointment stats
        $lines[] = $this->getAppointmentStats();

        return implode("\n", $lines);
    }

    private function getTopDoctors(): string
    {
        $lines = ["--- Top Doctors (This Month) ---"];

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Top by revenue
        $topByRevenue = Bill::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->where('is_paid', true)
            ->select('doctor_id', DB::raw('SUM(price) as total_revenue'), DB::raw('COUNT(*) as bill_count'))
            ->groupBy('doctor_id')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        if ($topByRevenue->isNotEmpty()) {
            $lines[] = "By Revenue:";
            foreach ($topByRevenue as $row) {
                $doctor = \App\Models\User::find($row->doctor_id);
                $name = $doctor->name ?? "Doctor #{$row->doctor_id}";
                $lines[] = "  - Dr. {$name}: Revenue {$row->total_revenue} ({$row->bill_count} bills)";
            }
        }

        // Top by cases
        $topByCases = CaseModel::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->select('doctor_id', DB::raw('COUNT(*) as case_count'))
            ->groupBy('doctor_id')
            ->orderByDesc('case_count')
            ->limit(5)
            ->get();

        if ($topByCases->isNotEmpty()) {
            $lines[] = "By Cases:";
            foreach ($topByCases as $row) {
                $doctor = \App\Models\User::find($row->doctor_id);
                $name = $doctor->name ?? "Doctor #{$row->doctor_id}";
                $lines[] = "  - Dr. {$name}: {$row->case_count} cases";
            }
        }

        // Top by appointments
        $topByAppointments = Reservation::whereBetween('reservation_start_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->select('doctor_id', DB::raw('COUNT(*) as appointment_count'))
            ->groupBy('doctor_id')
            ->orderByDesc('appointment_count')
            ->limit(5)
            ->get();

        if ($topByAppointments->isNotEmpty()) {
            $lines[] = "By Appointments:";
            foreach ($topByAppointments as $row) {
                $doctor = \App\Models\User::find($row->doctor_id);
                $name = $doctor->name ?? "Doctor #{$row->doctor_id}";
                $lines[] = "  - Dr. {$name}: {$row->appointment_count} appointments";
            }
        }

        return implode("\n", $lines);
    }

    private function getRevenueTrend(): string
    {
        $lines = ["--- Revenue Trend (Last 6 Months) ---"];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
            $monthLabel = $date->format('M Y');

            $revenue = Bill::whereBetween('created_at', [$start, $end])
                ->where('is_paid', true)
                ->sum('price');

            $billCount = Bill::whereBetween('created_at', [$start, $end])->count();

            $lines[] = "  {$monthLabel}: Revenue {$revenue} ({$billCount} bills)";
        }

        return implode("\n", $lines);
    }

    private function getMonthComparison(): string
    {
        $lines = ["--- This Month vs Last Month ---"];

        $thisMonthStart = now()->startOfMonth();
        $thisMonthEnd = now()->endOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        // Revenue
        $thisRevenue = Bill::whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])->where('is_paid', true)->sum('price');
        $lastRevenue = Bill::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->where('is_paid', true)->sum('price');
        $revenueChange = $lastRevenue > 0 ? round((($thisRevenue - $lastRevenue) / $lastRevenue) * 100, 1) : 0;
        $revenueDir = $revenueChange >= 0 ? '↑' : '↓';
        $lines[] = "Revenue: {$thisRevenue} vs {$lastRevenue} ({$revenueDir} {$revenueChange}%)";

        // Patients
        $thisPatients = Patient::whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])->count();
        $lastPatients = Patient::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $patientChange = $lastPatients > 0 ? round((($thisPatients - $lastPatients) / $lastPatients) * 100, 1) : 0;
        $patientDir = $patientChange >= 0 ? '↑' : '↓';
        $lines[] = "New Patients: {$thisPatients} vs {$lastPatients} ({$patientDir} {$patientChange}%)";

        // Cases
        $thisCases = CaseModel::whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])->count();
        $lastCases = CaseModel::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $casesChange = $lastCases > 0 ? round((($thisCases - $lastCases) / $lastCases) * 100, 1) : 0;
        $casesDir = $casesChange >= 0 ? '↑' : '↓';
        $lines[] = "Cases: {$thisCases} vs {$lastCases} ({$casesDir} {$casesChange}%)";

        // Appointments
        $thisAppts = Reservation::whereBetween('reservation_start_date', [$thisMonthStart->toDateString(), $thisMonthEnd->toDateString()])->count();
        $lastAppts = Reservation::whereBetween('reservation_start_date', [$lastMonthStart->toDateString(), $lastMonthEnd->toDateString()])->count();
        $apptsChange = $lastAppts > 0 ? round((($thisAppts - $lastAppts) / $lastAppts) * 100, 1) : 0;
        $apptsDir = $apptsChange >= 0 ? '↑' : '↓';
        $lines[] = "Appointments: {$thisAppts} vs {$lastAppts} ({$apptsDir} {$apptsChange}%)";

        // Expenses
        $thisExpenses = ClinicExpense::whereBetween('date', [$thisMonthStart->toDateString(), $thisMonthEnd->toDateString()])->sum(DB::raw('COALESCE(quantity, 1) * price'));
        $lastExpenses = ClinicExpense::whereBetween('date', [$lastMonthStart->toDateString(), $lastMonthEnd->toDateString()])->sum(DB::raw('COALESCE(quantity, 1) * price'));
        $expensesChange = $lastExpenses > 0 ? round((($thisExpenses - $lastExpenses) / $lastExpenses) * 100, 1) : 0;
        $expensesDir = $expensesChange >= 0 ? '↑' : '↓';
        $lines[] = "Expenses: {$thisExpenses} vs {$lastExpenses} ({$expensesDir} {$expensesChange}%)";

        // Net profit
        $thisProfit = $thisRevenue - $thisExpenses;
        $lastProfit = $lastRevenue - $lastExpenses;
        $lines[] = "Net Profit: {$thisProfit} vs {$lastProfit}";

        return implode("\n", $lines);
    }

    private function getPatientGrowth(): string
    {
        $lines = ["--- Patient Growth ---"];

        $thisWeek = Patient::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $lastWeek = Patient::whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])->count();
        $lines[] = "This Week: {$thisWeek} new patients (Last Week: {$lastWeek})";

        $thisMonth = Patient::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $lines[] = "This Month: {$thisMonth} new patients";

        $total = Patient::count();
        $lines[] = "Total Patients: {$total}";

        return implode("\n", $lines);
    }

    private function getAppointmentStats(): string
    {
        $lines = ["--- Appointment Statistics ---"];

        $today = now()->toDateString();
        $todayCount = Reservation::byDate($today)->count();
        $lines[] = "Today's Appointments: {$todayCount}";

        $thisWeek = Reservation::whereBetween('reservation_start_date', [
            now()->startOfWeek()->toDateString(),
            now()->endOfWeek()->toDateString(),
        ])->count();
        $lines[] = "This Week: {$thisWeek}";

        $thisMonth = Reservation::whereBetween('reservation_start_date', [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ])->count();
        $lines[] = "This Month: {$thisMonth}";

        return implode("\n", $lines);
    }
}
