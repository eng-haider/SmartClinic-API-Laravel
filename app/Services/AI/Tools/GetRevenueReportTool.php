<?php

namespace App\Services\AI\Tools;

use App\Models\Bill;
use App\Models\CaseModel;
use App\Models\ClinicExpense;

class GetRevenueReportTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_revenue_report';
    }

    public function description(): string
    {
        return 'Gets financial data: payments received (bills), case prices, unpaid amounts, and expense breakdowns.';
    }

    public function execute(array $params): string
    {
        $dateRange = $params['date_range'] ?? ['type' => 'none'];
        $lines = [];

        [$start, $end] = $this->resolveDateRange($dateRange);

        if ($start && $end) {
            $label = $start->eq($end) ? $start->toDateString() : $start->toDateString() . ' to ' . $end->toDateString();

            // Payments received from auditor (bills table)
            $bills = Bill::whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])->get();

            $lines[] = "--- Financial Report for {$label} ---";
            $lines[] = "";
            $lines[] = "=== Payments Received (Bills) ===";
            $lines[] = "Number of Payments: " . $bills->count();
            $lines[] = "Total Amount Received: " . $bills->sum('price');

            // Top doctors by payments received
            $doctorRevenue = $bills->groupBy('doctor_id')->map(fn($b) => $b->sum('price'))->sortDesc()->take(5);
            if ($doctorRevenue->isNotEmpty()) {
                $lines[] = "Top Doctors by Payments Received:";
                foreach ($doctorRevenue as $doctorId => $revenue) {
                    $doctor = \App\Models\User::find($doctorId);
                    $doctorName = $doctor->name ?? "Doctor #{$doctorId}";
                    $lines[] = "  - Dr. {$doctorName}: {$revenue}";
                }
            }

            // Cases financial summary (doctor-set prices)
            $endCopy = $end->copy();
            $cases = CaseModel::whereBetween('created_at', [$start->startOfDay(), $endCopy->endOfDay()])->get();

            $lines[] = "";
            $lines[] = "=== Cases/Treatments Pricing ===";
            $lines[] = "Total Cases: " . $cases->count();
            $lines[] = "Total Case Prices (Doctor Charges): " . $cases->sum('price');
            $lines[] = "Paid Cases: " . $cases->where('is_paid', true)->count();
            $lines[] = "Unpaid Cases: " . $cases->where('is_paid', false)->count();
            $lines[] = "Unpaid Amount: " . $cases->where('is_paid', false)->sum('price');

            // Individual bills (max 15)
            if ($bills->isNotEmpty()) {
                $lines[] = "";
                $lines[] = "=== Individual Payments ===";
                foreach ($bills->take(15) as $bill) {
                    $patientName = $bill->patient->name ?? 'Unknown';
                    $doctorName = $bill->doctor->name ?? 'Unknown';
                    $lines[] = "  - {$patientName} | Dr. {$doctorName} | Amount: {$bill->price}";
                }
            }
        } else {
            // Default: today + all-time
            $today = now()->toDateString();

            // Today's payments
            $todayBills = Bill::whereDate('created_at', $today)->get();
            $lines[] = "--- Financial Report for Today ({$today}) ---";
            $lines[] = "";
            $lines[] = "=== Today's Payments Received ===";
            $lines[] = "Payments Today: " . $todayBills->count();
            $lines[] = "Amount Received Today: " . $todayBills->sum('price');

            // Today's cases
            $todayCases = CaseModel::whereDate('created_at', $today)->get();
            $lines[] = "";
            $lines[] = "=== Today's Cases ===";
            $lines[] = "Cases Today: " . $todayCases->count();
            $lines[] = "Case Prices Today: " . $todayCases->sum('price');
            $lines[] = "Unpaid Cases Today: " . $todayCases->where('is_paid', false)->count();
            $lines[] = "Unpaid Amount Today: " . $todayCases->where('is_paid', false)->sum('price');

            // All-time summary
            $allTimeBills = Bill::sum('price');
            $allTimeBillCount = Bill::count();
            $allTimeCasePrice = CaseModel::sum('price');
            $allTimeUnpaidPrice = CaseModel::where('is_paid', false)->sum('price');
            $allTimeUnpaidCount = CaseModel::where('is_paid', false)->count();

            $lines[] = "";
            $lines[] = "--- All-Time Financial Summary ---";
            $lines[] = "Total Payments Received (Bills): {$allTimeBillCount} payments, Amount: {$allTimeBills}";
            $lines[] = "Total Case Prices (Doctor Charges): {$allTimeCasePrice}";
            $lines[] = "Total Unpaid Cases: {$allTimeUnpaidCount}";
            $lines[] = "Total Unpaid Amount: {$allTimeUnpaidPrice}";
        }

        // Expenses summary
        $lines[] = "";
        if ($start && $end) {
            $expenses = ClinicExpense::whereBetween('date', [$start->toDateString(), $end->toDateString()])->with('category')->get();
            $label = $start->eq($end) ? $start->toDateString() : $start->format('F Y');

            $lines[] = "--- Expenses for {$label} ---";
            $lines[] = "Total Expenses: " . $expenses->count();
            $lines[] = "Total Expense Amount: " . $expenses->sum(fn($e) => ($e->quantity ?? 1) * $e->price);

            $byCategory = $expenses->groupBy(fn($e) => $e->category->name ?? 'Uncategorized');
            if ($byCategory->isNotEmpty()) {
                $lines[] = "By Category:";
                foreach ($byCategory as $cat => $items) {
                    $catTotal = $items->sum(fn($e) => ($e->quantity ?? 1) * $e->price);
                    $lines[] = "  - {$cat}: {$catTotal} ({$items->count()} items)";
                }
            }
        } else {
            $todayExpenses = ClinicExpense::whereDate('date', now()->toDateString())->get();
            $lines[] = "--- Expenses for Today ---";
            $lines[] = "Total Expenses: " . $todayExpenses->count();
            $lines[] = "Total Amount: " . $todayExpenses->sum(fn($e) => ($e->quantity ?? 1) * $e->price);
        }

        return implode("\n", $lines);
    }

    /**
     * Resolve date range params into Carbon start/end dates.
     *
     * @return array{0: \Carbon\Carbon|null, 1: \Carbon\Carbon|null}
     */
    private function resolveDateRange(array $dateRange): array
    {
        $type = $dateRange['type'] ?? 'none';

        return match ($type) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'tomorrow' => [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'specific_date', 'custom' => $this->parseCustomDates($dateRange),
            default => [null, null],
        };
    }

    private function parseCustomDates(array $dateRange): array
    {
        $start = isset($dateRange['start']) ? \Carbon\Carbon::parse($dateRange['start']) : null;
        $end = isset($dateRange['end']) ? \Carbon\Carbon::parse($dateRange['end']) : $start;
        return [$start, $end];
    }
}
