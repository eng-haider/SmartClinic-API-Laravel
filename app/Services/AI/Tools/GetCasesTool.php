<?php

namespace App\Services\AI\Tools;

use App\Models\CaseModel;

class GetCasesTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_cases';
    }

    public function description(): string
    {
        return 'Gets cases/treatments data with patient, doctor, category, and status details.';
    }

    public function execute(array $params): string
    {
        $dateRange = $params['date_range'] ?? ['type' => 'none'];
        $doctorName = $params['entities']['doctor_name'] ?? '';
        $lines = [];

        [$start, $end] = $this->resolveDateRange($dateRange);

        if ($start && $end) {
            $label = $start->eq($end) ? $start->toDateString() : $start->toDateString() . ' to ' . $end->toDateString();

            $query = CaseModel::whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
                ->with(['patient:id,name', 'doctor:id,name', 'category:id,name', 'status:id,name']);

            if (!empty($doctorName)) {
                $query->whereHas('doctor', fn($q) => $q->where('name', 'like', "%{$doctorName}%"));
            }

            $cases = $query->get();

            $lines[] = "--- Cases/Treatments for {$label} ---";
            $lines[] = "Total Cases: " . $cases->count();
            $lines[] = "Paid: " . $cases->where('is_paid', true)->count();
            $lines[] = "Unpaid: " . $cases->where('is_paid', false)->count();
            $lines[] = "Total Revenue: " . $cases->sum('price');

            // Category breakdown
            $byCategory = $cases->groupBy(fn($c) => $c->category->name ?? 'Unknown');
            if ($byCategory->isNotEmpty()) {
                $lines[] = "By Category:";
                foreach ($byCategory as $cat => $items) {
                    $catRevenue = $items->sum('price');
                    $lines[] = "  - {$cat}: {$items->count()} cases, Revenue: {$catRevenue}";
                }
            }

            // Individual cases (max 15)
            if ($cases->isNotEmpty()) {
                $lines[] = "Case Details:";
                foreach ($cases->take(15) as $c) {
                    $patientName = $c->patient->name ?? 'Unknown';
                    $doctorN = $c->doctor->name ?? 'Unknown';
                    $categoryName = $c->category->name ?? 'Unknown';
                    $statusName = $c->status->name ?? 'Unknown';
                    $paid = $c->is_paid ? 'Paid' : 'Unpaid';
                    $lines[] = "  - {$patientName} | Dr. {$doctorN} | {$categoryName} | {$statusName} | {$c->price} | {$paid}";
                }
            }
        } else {
            $today = now()->toDateString();
            $todayCases = CaseModel::whereDate('created_at', $today)->count();
            $total = CaseModel::count();

            $lines[] = "--- Cases Summary (All-Time) ---";
            $lines[] = "Total Cases (All-Time): {$total}";
            $lines[] = "Cases Today ({$today}): {$todayCases}";

            // Fetch latest cases for context since no date was specified
            $query = CaseModel::with(['patient:id,name', 'doctor:id,name', 'category:id,name', 'status:id,name'])
                ->latest();
            
            if (!empty($doctorName)) {
                $query->whereHas('doctor', fn($q) => $q->where('name', 'like', "%{$doctorName}%"));
            }
            
            $recentCases = $query->take(15)->get();
            
            if ($recentCases->isNotEmpty()) {
                $lines[] = "";
                $lines[] = "Recent Cases:";
                foreach ($recentCases as $c) {
                    $patientName = $c->patient->name ?? 'Unknown';
                    $doctorN = $c->doctor->name ?? 'Unknown';
                    $categoryName = $c->category->name ?? 'Unknown';
                    $statusName = $c->status->name ?? 'Unknown';
                    $paid = $c->is_paid ? 'Paid' : 'Unpaid';
                    $lines[] = "  - {$c->created_at->toDateString()} | {$patientName} | Dr. {$doctorN} | {$categoryName} | {$statusName} | {$c->price} | {$paid}";
                }
            }
        }

        return implode("\n", $lines);
    }

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
            'specific_date', 'custom' => [
                isset($dateRange['start']) ? \Carbon\Carbon::parse($dateRange['start']) : null,
                isset($dateRange['end']) ? \Carbon\Carbon::parse($dateRange['end']) : (isset($dateRange['start']) ? \Carbon\Carbon::parse($dateRange['start']) : null),
            ],
            default => [null, null],
        };
    }
}
