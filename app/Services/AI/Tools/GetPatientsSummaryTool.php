<?php

namespace App\Services\AI\Tools;

use App\Models\Patient;
use Illuminate\Support\Facades\Cache;

class GetPatientsSummaryTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_patients_summary';
    }

    public function description(): string
    {
        return 'Gets patient statistics including counts, demographics, and new registrations.';
    }

    public function execute(array $params): string
    {
        $dateRange = $params['date_range'] ?? ['type' => 'none'];
        $lines = [];

        [$start, $end] = $this->resolveDateRange($dateRange);

        if ($start && $end) {
            $label = $start->eq($end) ? $start->toDateString() : $start->toDateString() . ' to ' . $end->toDateString();
            $patients = Patient::whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])->get();

            $lines[] = "--- Patients Summary for {$label} ---";
            $lines[] = "New Patients Registered: " . $patients->count();

            if ($patients->isNotEmpty()) {
                // Gender breakdown
                $males = $patients->where('sex', 1)->count();
                $females = $patients->where('sex', 2)->count();
                $lines[] = "Male: {$males}, Female: {$females}";

                // Average age
                $avgAge = round($patients->avg('age'), 1);
                $lines[] = "Average Age: {$avgAge}";

                // List patients (max 20)
                $lines[] = "New Patient List:";
                foreach ($patients->take(20) as $p) {
                    $doctorName = $p->doctor->name ?? 'No Doctor';
                    $lines[] = "  - {$p->name} | {$p->sex_label} | Age: {$p->age} | Dr. {$doctorName}";
                }
            }
        } else {
            $today = now()->toDateString();
            $newToday = Patient::whereDate('created_at', $today)->count();
            $total = Cache::remember('patients_total', 300, fn() => Patient::count());
            $activeCount = Cache::remember('patients_active', 300, fn() => Patient::whereNull('deleted_at')->count());

            $lines[] = "--- Patients Summary for Today ({$today}) ---";
            $lines[] = "New Patients Today: {$newToday}";
            $lines[] = "Total Patients (All-Time): {$total}";
            $lines[] = "Active Patients: {$activeCount}";

            // New this month
            $newThisMonth = Patient::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            $lines[] = "New Patients This Month: {$newThisMonth}";
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
