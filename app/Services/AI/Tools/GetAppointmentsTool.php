<?php

namespace App\Services\AI\Tools;

use App\Models\Reservation;

class GetAppointmentsTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_appointments';
    }

    public function description(): string
    {
        return 'Gets reservation/appointment data with doctor and status info.';
    }

    public function execute(array $params): string
    {
        $dateRange = $params['date_range'] ?? ['type' => 'none'];
        $doctorName = $params['entities']['doctor_name'] ?? '';
        $lines = [];

        [$start, $end] = $this->resolveDateRange($dateRange);

        if ($start && $end) {
            $label = $start->eq($end) ? $start->toDateString() : $start->toDateString() . ' to ' . $end->toDateString();

            $query = Reservation::whereBetween('reservation_start_date', [$start->toDateString(), $end->toDateString()])
                ->with(['patient:id,name', 'doctor:id,name', 'status:id,name', 'reservationType:id,name']);

            if (!empty($doctorName)) {
                $query->whereHas('doctor', fn($q) => $q->where('name', 'like', "%{$doctorName}%"));
            }

            $reservations = $query->get();

            $lines[] = "--- Appointments for {$label} ---";
            $lines[] = "Total Appointments: " . $reservations->count();

            // Status breakdown
            $statusGroups = $reservations->groupBy(fn($r) => $r->status->name ?? 'Unknown');
            if ($statusGroups->isNotEmpty()) {
                $lines[] = "By Status:";
                foreach ($statusGroups as $status => $items) {
                    $lines[] = "  - {$status}: {$items->count()}";
                }
            }

            // Doctor breakdown
            $doctorGroups = $reservations->groupBy(fn($r) => $r->doctor->name ?? 'Unknown');
            if ($doctorGroups->isNotEmpty()) {
                $lines[] = "By Doctor:";
                foreach ($doctorGroups as $doctor => $items) {
                    $lines[] = "  - Dr. {$doctor}: {$items->count()} appointments";
                }
            }

            // Individual appointments (max 20)
            if ($reservations->isNotEmpty()) {
                $lines[] = "Appointment Details:";
                foreach ($reservations->take(20) as $res) {
                    $patientName = $res->patient->name ?? 'Unknown';
                    $doctorN = $res->doctor->name ?? 'Unknown';
                    $statusName = $res->status->name ?? 'Unknown';
                    $typeName = $res->reservationType->name ?? '';
                    $time = $res->reservation_from_time . ' - ' . $res->reservation_to_time;
                    $waiting = $res->is_waiting ? ' [WAITING]' : '';
                    $lines[] = "  - {$time}: {$patientName} | Dr. {$doctorN} | {$statusName}" . ($typeName ? " | {$typeName}" : "") . $waiting;
                }
            } else {
                $lines[] = "No appointments found for this period.";
            }
        } else {
            // Default: today
            $today = now()->toDateString();
            $todayRes = Reservation::byDate($today)
                ->with(['patient:id,name', 'doctor:id,name', 'status:id,name', 'reservationType:id,name'])
                ->get();

            $lines[] = "--- Appointments for Today ({$today}) ---";
            $lines[] = "Total Appointments: " . $todayRes->count();

            if ($todayRes->isNotEmpty()) {
                foreach ($todayRes->take(20) as $res) {
                    $patientName = $res->patient->name ?? 'Unknown';
                    $doctorN = $res->doctor->name ?? 'Unknown';
                    $statusName = $res->status->name ?? 'Unknown';
                    $time = $res->reservation_from_time . ' - ' . $res->reservation_to_time;
                    $lines[] = "  - {$time}: {$patientName} | Dr. {$doctorN} | {$statusName}";
                }
            } else {
                $lines[] = "No appointments for today.";
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
