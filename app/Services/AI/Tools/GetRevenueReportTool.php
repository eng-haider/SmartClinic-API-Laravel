<?php

namespace App\Services\AI\Tools;

use App\Models\Bill;
use App\Models\ClinicExpense;
use Illuminate\Support\Facades\Cache;

class GetRevenueReportTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_revenue_report';
    }

    public function description(): string
    {
        return 'Gets revenue and billing data including totals, paid/unpaid breakdowns, and doctor revenue rankings.';
    }

    public function execute(array $params): string
    {
        $dateRange = $params['date_range'] ?? ['type' => 'none'];
        $lines = [];

        [$start, $end] = $this->resolveDateRange($dateRange);

        if ($start && $end) {
            $label = $start->eq($end) ? $start->toDateString() : $start->toDateString() . ' to ' . $end->toDateString();
            $bills = Bill::whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])->get();

            $lines[] = "--- Revenue Report for {$label} ---";
            $lines[] = "Total Bills: " . $bills->count();
            $lines[] = "Total Paid Revenue: " . $bills->where('is_paid', true)->sum('price');
            $lines[] = "Total Unpaid Amount: " . $bills->where('is_paid', false)->sum('price');
            $lines[] = "Paid Bills: " . $bills->where('is_paid', true)->count();
            $lines[] = "Unpaid Bills: " . $bills->where('is_paid', false)->count();

            // Top doctors by revenue
            $doctorRevenue = $bills->where('is_paid', true)->groupBy('doctor_id')->map(fn($b) => $b->sum('price'))->sortDesc()->take(5);
            if ($doctorRevenue->isNotEmpty()) {
                $lines[] = "Top Doctors by Revenue:";
                foreach ($doctorRevenue as $doctorId => $revenue) {
                    $doctor = \App\Models\User::find($doctorId);
                    $doctorName = $doctor->name ?? "Doctor #{$doctorId}";
                    $lines[] = "  - Dr. {$doctorName}: {$revenue}";
                }
            }

            // Individual bills (max 15)
            if ($bills->isNotEmpty()) {
                $lines[] = "Individual Bills:";
                foreach ($bills->take(15) as $bill) {
                    $patientName = $bill->patient->name ?? 'Unknown';
                    $doctorName = $bill->doctor->name ?? 'Unknown';
                    $status = $bill->is_paid ? 'Paid' : 'Unpaid';
                    $lines[] = "  - {$patientName} | Dr. {$doctorName} | {$bill->price} | {$status}";
                }
            }
        } else {
            // Default: today + all-time
            $today = now()->toDateString();
            $todayBills = Bill::whereDate('created_at', $today)->get();

            $lines[] = "--- Revenue for Today ({$today}) ---";
            $lines[] = "Today's Bills: " . $todayBills->count();
            $lines[] = "Today's Paid Revenue: " . $todayBills->where('is_paid', true)->sum('price');
            $lines[] = "Today's Unpaid: " . $todayBills->where('is_paid', false)->sum('price');

            // All-time summary (cached for 5 minutes)
            $allTimeStats = Cache::remember('revenue_all_time', 300, function () {
                $all = Bill::all();
                return [
                    'total' => $all->count(),
                    'paid' => $all->where('is_paid', true)->sum('price'),
                    'unpaid' => $all->where('is_paid', false)->sum('price'),
                ];
            });

            $lines[] = "";
            $lines[] = "--- All-Time Revenue ---";
            $lines[] = "Total Bills: {$allTimeStats['total']}";
            $lines[] = "Total Paid Revenue: {$allTimeStats['paid']}";
            $lines[] = "Total Unpaid: {$allTimeStats['unpaid']}";
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
