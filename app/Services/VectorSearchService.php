<?php

namespace App\Services;

use App\Models\Embedding;
use App\Models\Patient;
use App\Models\Reservation;
use App\Models\CaseModel;
use App\Models\Bill;
use App\Models\ClinicExpense;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI;

class VectorSearchService
{
    private $client;
    private EmbeddingService $embeddingService;

    public function __construct(EmbeddingService $embeddingService)
    {
        $this->client = OpenAI::client(config('services.openai.api_key'));
        $this->embeddingService = $embeddingService;
    }

    /**
     * Search for the most similar embeddings to a query string.
     */
    public function searchSimilar(string $clinicId, string $query, int $limit = 5): Collection
    {
        $queryVector = $this->embeddingService->generateEmbedding($query);
        $vectorString = '[' . implode(',', $queryVector) . ']';

        $results = DB::connection('pgsql_embeddings')
            ->select(
            "SELECT id, clinic_id, table_name, record_id, content,
                        1 - (embedding <=> ?::vector) as similarity
                 FROM embeddings
                 WHERE clinic_id = ?
                 ORDER BY embedding <=> ?::vector
                 LIMIT ?",
        [$vectorString, $clinicId, $vectorString, $limit]
        );

        return collect($results);
    }

    /**
     * Fetch the original records from the tenant database using table_name + record_id.
     */
    public function fetchOriginalRecords(Collection $embeddingResults): array
    {
        $records = [];

        $modelMap = [
            'patients' => Patient::class,
            'reservations' => Reservation::class,
            'cases' => CaseModel::class,
            'bills' => Bill::class,
        ];

        foreach ($embeddingResults as $embedding) {
            $modelClass = $modelMap[$embedding->table_name] ?? null;

            if (!$modelClass) {
                $records[] = [
                    'source' => $embedding->table_name,
                    'record_id' => $embedding->record_id,
                    'content' => $embedding->content,
                    'similarity' => round($embedding->similarity, 4),
                ];
                continue;
            }

            try {
                $query = $modelClass::query()->where('id', $embedding->record_id);

                switch ($embedding->table_name) {
                    case 'patients':
                        $query->with(['doctor:id,name']);
                        break;
                    case 'reservations':
                        $query->with(['patient:id,name', 'doctor:id,name', 'status:id,name', 'reservationType:id,name']);
                        break;
                    case 'cases':
                        $query->with(['patient:id,name', 'doctor:id,name', 'category:id,name', 'status:id,name']);
                        break;
                    case 'bills':
                        $query->with(['patient:id,name', 'doctor:id,name']);
                        break;
                }

                $record = $query->first();

                if ($record) {
                    $records[] = [
                        'source' => $embedding->table_name,
                        'record_id' => $embedding->record_id,
                        'data' => $record->toArray(),
                        'content' => $embedding->content,
                        'similarity' => round($embedding->similarity, 4),
                    ];
                } else {
                    $records[] = [
                        'source' => $embedding->table_name,
                        'record_id' => $embedding->record_id,
                        'content' => $embedding->content,
                        'similarity' => round($embedding->similarity, 4),
                        'note' => 'Original record no longer exists',
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch original record', [
                    'table' => $embedding->table_name,
                    'record_id' => $embedding->record_id,
                    'error' => $e->getMessage(),
                ]);

                $records[] = [
                    'source' => $embedding->table_name,
                    'record_id' => $embedding->record_id,
                    'content' => $embedding->content,
                    'similarity' => round($embedding->similarity, 4),
                ];
            }
        }

        return $records;
    }

    // =========================================================================
    // SMART INTENT DETECTION
    // =========================================================================

    /**
     * Check if the question is a simple greeting that doesn't need database search.
     */
    private function isSimpleGreeting(string $question): bool
    {
        $greetings = [
            'hello', 'hi', 'hey', 'how are you', 'good morning', 'good evening',
            'good afternoon', 'what can you do', 'who are you', 'help',
            'مرحبا', 'اهلا', 'السلام عليكم', 'كيف حالك', 'شلونك', 'هلو',
        ];
        $lower = mb_strtolower(trim($question));
        foreach ($greetings as $greeting) {
            if (str_contains($lower, $greeting)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect the intent(s) of the user's question.
     * Returns an array of matched intents: revenue, expenses, patients, cases, reservations, dashboard.
     *
     * @return string[]
     */
    private function detectIntent(string $question): array
    {
        $q = mb_strtolower(trim($question));
        $intents = [];

        // Revenue / Bills intent
        $revenueWords = [
            'revenue', 'income', 'bill', 'bills', 'invoice', 'invoices', 'payment', 'payments', 'paid', 'unpaid',
            'إيرادات', 'إيراد', 'دخل', 'فواتير', 'فاتورة', 'مدفوعات', 'مدفوع', 'غير مدفوع',
            'أرباح', 'ربح', 'مبيعات', 'تحصيل', 'ايرادات', 'ايراد', 'فلوس', 'مبلغ', 'مبالغ',
        ];
        foreach ($revenueWords as $word) {
            if (str_contains($q, $word)) {
                $intents[] = 'revenue';
                break;
            }
        }

        // Expenses intent
        $expenseWords = [
            'expense', 'expenses', 'cost', 'costs', 'spending',
            'مصاريف', 'مصروف', 'نفقات', 'تكاليف', 'تكلفة', 'مصروفات', 'صرف',
        ];
        foreach ($expenseWords as $word) {
            if (str_contains($q, $word)) {
                $intents[] = 'expenses';
                break;
            }
        }

        // Patients intent
        $patientWords = [
            'patient', 'patients', 'new patient', 'registered',
            'مرضى', 'مريض', 'مراجعين', 'مراجع', 'مسجلين', 'مسجل',
        ];
        foreach ($patientWords as $word) {
            if (str_contains($q, $word)) {
                $intents[] = 'patients';
                break;
            }
        }

        // Cases / Treatments intent
        $caseWords = [
            'case', 'cases', 'treatment', 'treatments', 'procedure', 'procedures',
            'حالات', 'حالة', 'علاج', 'علاجات', 'إجراء', 'إجراءات', 'معالجة',
        ];
        foreach ($caseWords as $word) {
            if (str_contains($q, $word)) {
                $intents[] = 'cases';
                break;
            }
        }

        // Reservations / Appointments intent
        $reservationWords = [
            'appointment', 'appointments', 'reservation', 'reservations', 'schedule', 'booking', 'bookings',
            'مواعيد', 'موعد', 'حجوزات', 'حجز', 'جدول',
        ];
        foreach ($reservationWords as $word) {
            if (str_contains($q, $word)) {
                $intents[] = 'reservations';
                break;
            }
        }

        // Dashboard / Summary intent
        $dashboardWords = [
            'summary', 'overview', 'dashboard', 'report', 'statistics', 'stats', 'total',
            'ملخص', 'تقرير', 'إحصائيات', 'نظرة عامة', 'إجمالي', 'احصائيات', 'ملخص عام',
        ];
        foreach ($dashboardWords as $word) {
            if (str_contains($q, $word)) {
                $intents[] = 'dashboard';
                break;
            }
        }

        return array_unique($intents);
    }

    /**
     * Extract a target date from the user's question.
     * Supports: today, tomorrow, yesterday, specific day numbers, full dates, and month references.
     *
     * @return \Carbon\Carbon|null
     */
    private function extractDateFromQuestion(string $question): ?\Carbon\Carbon
    {
        $q = mb_strtolower(trim($question));

        // Today
        foreach (['today', 'اليوم', 'النهارده', 'النهاردة'] as $word) {
            if (str_contains($q, $word)) return now()->startOfDay();
        }

        // Tomorrow
        foreach (['tomorrow', 'غدا', 'غداً', 'باچر', 'باجر', 'بكرة', 'بكره'] as $word) {
            if (str_contains($q, $word)) return now()->addDay()->startOfDay();
        }

        // Yesterday
        foreach (['yesterday', 'أمس', 'امس', 'البارحة', 'البارحه'] as $word) {
            if (str_contains($q, $word)) return now()->subDay()->startOfDay();
        }

        // Full date: YYYY-MM-DD or YYYY/MM/DD
        if (preg_match('/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $q, $m)) {
            try { return \Carbon\Carbon::createFromDate((int)$m[1], (int)$m[2], (int)$m[3])->startOfDay(); } catch (\Exception $e) {}
        }
        // Full date: DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $q, $m)) {
            try { return \Carbon\Carbon::createFromDate((int)$m[3], (int)$m[2], (int)$m[1])->startOfDay(); } catch (\Exception $e) {}
        }

        // "يوم 25", "day 25"
        if (preg_match('/(?:يوم|day)\s*(\d{1,2})/u', $q, $m)) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= 31) {
                try { return now()->startOfMonth()->day($day)->startOfDay(); } catch (\Exception $e) {}
            }
        }

        // "25 لهاذا", "25 من هذا الشهر", "25 this month"
        if (preg_match('/(\d{1,2})\s*(?:لهاذا|لهذا|من هذا|من هاذا|الشهر|this month)/u', $q, $m)) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= 31) {
                try { return now()->startOfMonth()->day($day)->startOfDay(); } catch (\Exception $e) {}
            }
        }

        // "مواعيد 25", "إيرادات 25" — keyword followed by day number
        if (preg_match('/(?:مواعيد|موعد|حجوزات|حجز|إيرادات|ايرادات|مصاريف|فواتير)\s*(\d{1,2})/u', $q, $m)) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= 31) {
                try { return now()->startOfMonth()->day($day)->startOfDay(); } catch (\Exception $e) {}
            }
        }

        return null;
    }

    /**
     * Detect if user is asking about a whole month (e.g., "this month", "هذا الشهر").
     *
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon}|null
     */
    private function extractMonthRange(string $question): ?array
    {
        $q = mb_strtolower(trim($question));

        $thisMonthWords = ['this month', 'هذا الشهر', 'هاذا الشهر', 'الشهر الحالي', 'الشهر هذا'];
        foreach ($thisMonthWords as $word) {
            if (str_contains($q, $word)) {
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
            }
        }

        $lastMonthWords = ['last month', 'الشهر الماضي', 'الشهر السابق'];
        foreach ($lastMonthWords as $word) {
            if (str_contains($q, $word)) {
                return [
                    'start' => now()->subMonth()->startOfMonth(),
                    'end' => now()->subMonth()->endOfMonth(),
                ];
            }
        }

        return null;
    }

    // =========================================================================
    // REALTIME CONTEXT BUILDERS (one per intent)
    // =========================================================================

    /**
     * Gather realtime data context based on detected intents.
     */
    private function gatherRealtimeContext(array $intents, string $question): string
    {
        $parts = [];
        $targetDate = $this->extractDateFromQuestion($question);
        $monthRange = $this->extractMonthRange($question);

        // If dashboard intent is the ONLY intent (or no other specific intent), gather everything
        if (in_array('dashboard', $intents) && count($intents) === 1) {
            $intents = ['revenue', 'expenses', 'patients', 'cases', 'reservations'];
        }

        foreach ($intents as $intent) {
            switch ($intent) {
                case 'revenue':
                    $parts[] = $this->buildRevenueContext($targetDate, $monthRange);
                    break;
                case 'expenses':
                    $parts[] = $this->buildExpensesContext($targetDate, $monthRange);
                    break;
                case 'patients':
                    $parts[] = $this->buildPatientsContext($targetDate, $monthRange);
                    break;
                case 'cases':
                    $parts[] = $this->buildCasesContext($targetDate, $monthRange);
                    break;
                case 'reservations':
                    $parts[] = $this->buildReservationsContext($targetDate, $monthRange);
                    break;
                case 'dashboard':
                    // Already expanded above
                    break;
            }
        }

        return implode("\n", array_filter($parts));
    }

    /**
     * Build revenue/bills context.
     */
    private function buildRevenueContext(?\Carbon\Carbon $date, ?array $monthRange): string
    {
        $lines = [];

        if ($date) {
            $dateStr = $date->toDateString();
            $label = $date->isToday() ? "Today ({$dateStr})" : $dateStr;

            $dayBills = Bill::whereDate('created_at', $dateStr)->get();
            $totalBills = $dayBills->count();
            $totalRevenue = $dayBills->where('is_paid', true)->sum('price');
            $totalUnpaid = $dayBills->where('is_paid', false)->sum('price');
            $paidCount = $dayBills->where('is_paid', true)->count();
            $unpaidCount = $dayBills->where('is_paid', false)->count();

            $lines[] = "--- Revenue Summary for {$label} ---";
            $lines[] = "Total Bills: {$totalBills}";
            $lines[] = "Total Paid Revenue: {$totalRevenue}";
            $lines[] = "Total Unpaid Amount: {$totalUnpaid}";
            $lines[] = "Paid Bills: {$paidCount}, Unpaid Bills: {$unpaidCount}";

            // Top doctors by revenue for this day
            $doctorRevenue = $dayBills->where('is_paid', true)->groupBy('doctor_id')->map(function ($bills) {
                return $bills->sum('price');
            })->sortDesc()->take(5);

            if ($doctorRevenue->isNotEmpty()) {
                $lines[] = "Top Doctors by Revenue:";
                foreach ($doctorRevenue as $doctorId => $revenue) {
                    $doctor = \App\Models\User::find($doctorId);
                    $doctorName = $doctor->name ?? "Doctor #{$doctorId}";
                    $lines[] = "  - Dr. {$doctorName}: {$revenue}";
                }
            }

            // List individual bills
            if ($dayBills->isNotEmpty()) {
                $lines[] = "Individual Bills:";
                foreach ($dayBills->take(20) as $bill) {
                    $patientName = $bill->patient->name ?? 'Unknown';
                    $doctorName = $bill->doctor->name ?? 'Unknown';
                    $status = $bill->is_paid ? 'Paid' : 'Unpaid';
                    $lines[] = "  - {$patientName} | Dr. {$doctorName} | {$bill->price} | {$status}";
                }
            }
        } elseif ($monthRange) {
            $monthBills = Bill::whereBetween('created_at', [$monthRange['start'], $monthRange['end']])->get();
            $label = $monthRange['start']->format('F Y');

            $lines[] = "--- Revenue Summary for {$label} ---";
            $lines[] = "Total Bills: " . $monthBills->count();
            $lines[] = "Total Paid Revenue: " . $monthBills->where('is_paid', true)->sum('price');
            $lines[] = "Total Unpaid Amount: " . $monthBills->where('is_paid', false)->sum('price');
            $lines[] = "Paid Bills: " . $monthBills->where('is_paid', true)->count();
            $lines[] = "Unpaid Bills: " . $monthBills->where('is_paid', false)->count();
        } else {
            // Default to today
            $today = now()->toDateString();
            $dayBills = Bill::whereDate('created_at', $today)->get();

            $lines[] = "--- Revenue Summary for Today ({$today}) ---";
            $lines[] = "Total Bills: " . $dayBills->count();
            $lines[] = "Total Paid Revenue: " . $dayBills->where('is_paid', true)->sum('price');
            $lines[] = "Total Unpaid Amount: " . $dayBills->where('is_paid', false)->sum('price');
            $lines[] = "Paid Bills: " . $dayBills->where('is_paid', true)->count();
            $lines[] = "Unpaid Bills: " . $dayBills->where('is_paid', false)->count();

            // Also include all-time totals for general context
            $allBills = Bill::all();
            $lines[] = "";
            $lines[] = "--- All-Time Revenue ---";
            $lines[] = "Total Bills Ever: " . $allBills->count();
            $lines[] = "Total Paid Revenue (All-Time): " . $allBills->where('is_paid', true)->sum('price');
            $lines[] = "Total Unpaid (All-Time): " . $allBills->where('is_paid', false)->sum('price');
        }

        $lines[] = "";
        return implode("\n", $lines);
    }

    /**
     * Build expenses context.
     */
    private function buildExpensesContext(?\Carbon\Carbon $date, ?array $monthRange): string
    {
        $lines = [];

        if ($date) {
            $dateStr = $date->toDateString();
            $label = $date->isToday() ? "Today ({$dateStr})" : $dateStr;

            $dayExpenses = ClinicExpense::whereDate('date', $dateStr)->with('category')->get();

            $lines[] = "--- Expenses Summary for {$label} ---";
            $lines[] = "Total Expenses: " . $dayExpenses->count();
            $lines[] = "Total Expense Amount: " . $dayExpenses->sum(fn($e) => ($e->quantity ?? 1) * $e->price);
            $lines[] = "Paid: " . $dayExpenses->where('is_paid', true)->count();
            $lines[] = "Unpaid: " . $dayExpenses->where('is_paid', false)->count();

            if ($dayExpenses->isNotEmpty()) {
                $lines[] = "Expense Details:";
                foreach ($dayExpenses->take(20) as $expense) {
                    $categoryName = $expense->category->name ?? 'Uncategorized';
                    $total = ($expense->quantity ?? 1) * $expense->price;
                    $status = $expense->is_paid ? 'Paid' : 'Unpaid';
                    $lines[] = "  - {$expense->name} | {$categoryName} | {$total} | {$status}";
                }
            }
        } elseif ($monthRange) {
            $monthExpenses = ClinicExpense::whereBetween('date', [$monthRange['start'], $monthRange['end']])->with('category')->get();
            $label = $monthRange['start']->format('F Y');

            $lines[] = "--- Expenses Summary for {$label} ---";
            $lines[] = "Total Expenses: " . $monthExpenses->count();
            $lines[] = "Total Amount: " . $monthExpenses->sum(fn($e) => ($e->quantity ?? 1) * $e->price);

            // Group by category
            $byCategory = $monthExpenses->groupBy(fn($e) => $e->category->name ?? 'Uncategorized');
            if ($byCategory->isNotEmpty()) {
                $lines[] = "By Category:";
                foreach ($byCategory as $cat => $expenses) {
                    $catTotal = $expenses->sum(fn($e) => ($e->quantity ?? 1) * $e->price);
                    $lines[] = "  - {$cat}: {$catTotal} ({$expenses->count()} items)";
                }
            }
        } else {
            $today = now()->toDateString();
            $dayExpenses = ClinicExpense::whereDate('date', $today)->get();

            $lines[] = "--- Expenses Summary for Today ({$today}) ---";
            $lines[] = "Total Expenses: " . $dayExpenses->count();
            $lines[] = "Total Amount: " . $dayExpenses->sum(fn($e) => ($e->quantity ?? 1) * $e->price);

            $allExpenses = ClinicExpense::all();
            $lines[] = "";
            $lines[] = "--- All-Time Expenses ---";
            $lines[] = "Total Expenses Ever: " . $allExpenses->count();
            $lines[] = "Total Amount (All-Time): " . $allExpenses->sum(fn($e) => ($e->quantity ?? 1) * $e->price);
        }

        $lines[] = "";
        return implode("\n", $lines);
    }

    /**
     * Build patients context.
     */
    private function buildPatientsContext(?\Carbon\Carbon $date, ?array $monthRange): string
    {
        $lines = [];

        if ($date) {
            $dateStr = $date->toDateString();
            $label = $date->isToday() ? "Today ({$dateStr})" : $dateStr;

            $newPatients = Patient::whereDate('created_at', $dateStr)->get();

            $lines[] = "--- Patients Summary for {$label} ---";
            $lines[] = "New Patients Registered: " . $newPatients->count();

            if ($newPatients->isNotEmpty()) {
                $lines[] = "New Patient List:";
                foreach ($newPatients->take(20) as $p) {
                    $doctorName = $p->doctor->name ?? 'No Doctor';
                    $lines[] = "  - {$p->name} | {$p->sex_label} | Age: {$p->age} | Dr. {$doctorName}";
                }
            }
        } elseif ($monthRange) {
            $label = $monthRange['start']->format('F Y');
            $monthPatients = Patient::whereBetween('created_at', [$monthRange['start'], $monthRange['end']])->get();

            $lines[] = "--- Patients Summary for {$label} ---";
            $lines[] = "New Patients: " . $monthPatients->count();
        } else {
            $today = now()->toDateString();
            $newToday = Patient::whereDate('created_at', $today)->count();
            $total = Patient::count();

            $lines[] = "--- Patients Summary for Today ({$today}) ---";
            $lines[] = "New Patients Today: {$newToday}";
            $lines[] = "Total Patients (All-Time): {$total}";
        }

        $lines[] = "";
        return implode("\n", $lines);
    }

    /**
     * Build cases/treatments context.
     */
    private function buildCasesContext(?\Carbon\Carbon $date, ?array $monthRange): string
    {
        $lines = [];

        if ($date) {
            $dateStr = $date->toDateString();
            $label = $date->isToday() ? "Today ({$dateStr})" : $dateStr;

            $dayCases = CaseModel::whereDate('created_at', $dateStr)
                ->with(['patient:id,name', 'doctor:id,name', 'category:id,name', 'status:id,name'])
                ->get();

            $lines[] = "--- Cases/Treatments for {$label} ---";
            $lines[] = "Total Cases: " . $dayCases->count();
            $lines[] = "Paid: " . $dayCases->where('is_paid', true)->count();
            $lines[] = "Unpaid: " . $dayCases->where('is_paid', false)->count();
            $lines[] = "Total Revenue (cases): " . $dayCases->sum('price');

            if ($dayCases->isNotEmpty()) {
                $lines[] = "Case Details:";
                foreach ($dayCases->take(20) as $c) {
                    $patientName = $c->patient->name ?? 'Unknown';
                    $doctorName = $c->doctor->name ?? 'Unknown';
                    $categoryName = $c->category->name ?? 'Unknown';
                    $statusName = $c->status->name ?? 'Unknown';
                    $paid = $c->is_paid ? 'Paid' : 'Unpaid';
                    $lines[] = "  - {$patientName} | Dr. {$doctorName} | {$categoryName} | {$statusName} | {$c->price} | {$paid}";
                }
            }
        } elseif ($monthRange) {
            $label = $monthRange['start']->format('F Y');
            $monthCases = CaseModel::whereBetween('created_at', [$monthRange['start'], $monthRange['end']])->get();

            $lines[] = "--- Cases/Treatments for {$label} ---";
            $lines[] = "Total Cases: " . $monthCases->count();
            $lines[] = "Paid: " . $monthCases->where('is_paid', true)->count();
            $lines[] = "Unpaid: " . $monthCases->where('is_paid', false)->count();
            $lines[] = "Total Revenue: " . $monthCases->sum('price');
        } else {
            $today = now()->toDateString();
            $todayCases = CaseModel::whereDate('created_at', $today)->count();
            $total = CaseModel::count();

            $lines[] = "--- Cases Summary for Today ({$today}) ---";
            $lines[] = "Cases Today: {$todayCases}";
            $lines[] = "Total Cases (All-Time): {$total}";
        }

        $lines[] = "";
        return implode("\n", $lines);
    }

    /**
     * Build reservations/appointments context.
     */
    private function buildReservationsContext(?\Carbon\Carbon $date, ?array $monthRange): string
    {
        $lines = [];

        if ($date) {
            $dateStr = $date->toDateString();
            $label = $date->isToday() ? "Today ({$dateStr})" : $dateStr;

            $dateReservations = Reservation::byDate($dateStr)
                ->with(['patient:id,name', 'doctor:id,name', 'status:id,name', 'reservationType:id,name'])
                ->get();

            $lines[] = "--- Reservations for {$label} ---";
            $lines[] = "Total Appointments: " . $dateReservations->count();

            if ($dateReservations->isNotEmpty()) {
                foreach ($dateReservations as $res) {
                    $patientName = $res->patient->name ?? 'Unknown';
                    $doctorName = $res->doctor->name ?? 'Unknown';
                    $statusName = $res->status->name ?? 'Unknown';
                    $typeName = $res->reservationType->name ?? '';
                    $time = $res->reservation_from_time . ' - ' . $res->reservation_to_time;
                    $waiting = $res->is_waiting ? ' [WAITING]' : '';
                    $lines[] = "  - {$time}: {$patientName} | Dr. {$doctorName} | {$statusName}" . ($typeName ? " | {$typeName}" : "") . $waiting;
                }
            } else {
                $lines[] = "No reservations found for this date.";
            }
        } elseif ($monthRange) {
            $label = $monthRange['start']->format('F Y');
            $monthRes = Reservation::whereBetween('reservation_start_date', [$monthRange['start'], $monthRange['end']])->get();

            $lines[] = "--- Reservations for {$label} ---";
            $lines[] = "Total Appointments: " . $monthRes->count();
        } else {
            $today = now()->toDateString();
            $todayRes = Reservation::byDate($today)
                ->with(['patient:id,name', 'doctor:id,name', 'status:id,name', 'reservationType:id,name'])
                ->get();

            $lines[] = "--- Reservations for Today ({$today}) ---";
            $lines[] = "Total Appointments: " . $todayRes->count();

            if ($todayRes->isNotEmpty()) {
                foreach ($todayRes as $res) {
                    $patientName = $res->patient->name ?? 'Unknown';
                    $doctorName = $res->doctor->name ?? 'Unknown';
                    $statusName = $res->status->name ?? 'Unknown';
                    $time = $res->reservation_from_time . ' - ' . $res->reservation_to_time;
                    $lines[] = "  - {$time}: {$patientName} | Dr. {$doctorName} | {$statusName}";
                }
            } else {
                $lines[] = "No reservations for today.";
            }
        }

        $lines[] = "";
        return implode("\n", $lines);
    }

    // =========================================================================
    // MAIN CHAT METHOD
    // =========================================================================

    public function chat(string $clinicId, string $question): array
    {
        try {
            $apiKey = config('services.openai.api_key');
            $model = 'gpt-4o-mini';

            $systemMessage = 'You are a smart AI assistant for a dental/medical clinic management system called SmartClinic. '
                . 'You have direct access to real-time clinic data including: revenue/bills, expenses, patients, cases/treatments, and reservations/appointments. '
                . 'When real-time data context is provided, use it to give accurate, data-driven answers with specific numbers. '
                . 'Always present financial data clearly with totals. '
                . 'Be professional, friendly, and concise. Always respond in the same language the user asks in (Arabic or English). '
                . 'The current date and time is: ' . now()->toDateTimeString() . '.';

            // For simple greetings, skip everything
            if ($this->isSimpleGreeting($question)) {
                $answer = $this->callOpenAI($apiKey, $model, $systemMessage, $question, 300);

                return [
                    'success' => true,
                    'question' => $question,
                    'answer' => $answer,
                    'sources' => [],
                    'answered_at' => now()->toDateTimeString(),
                ];
            }

            // Detect intent(s)
            $intents = $this->detectIntent($question);

            // Build realtime context from intent detection
            $realtimeContext = '';
            if (!empty($intents)) {
                $realtimeContext = $this->gatherRealtimeContext($intents, $question);
            }

            // Vector search for additional context (skip if we have strong intent matches)
            $originalRecords = [];
            $vectorContext = '';
            if (empty($intents)) {
                // No specific intent detected — rely on vector search
                $similarEmbeddings = $this->searchSimilar($clinicId, $question, 5);
                $relevantEmbeddings = $similarEmbeddings->filter(fn($item) => $item->similarity >= 0.3);

                if ($relevantEmbeddings->isNotEmpty()) {
                    $originalRecords = $this->fetchOriginalRecords($relevantEmbeddings);
                    $vectorContext = $this->buildContext($originalRecords);
                }
            }

            // Combine contexts
            $context = trim($realtimeContext . "\n" . $vectorContext);

            // Build the user message
            $userMessage = $question;
            if (!empty($context)) {
                $userMessage = "Based on the following real-time clinic data, answer this question accurately:\n\n"
                    . "Question: {$question}\n\n"
                    . "Clinic Data:\n{$context}";
            }

            // Send to GPT
            $answer = $this->callOpenAI($apiKey, $model, $systemMessage, $userMessage, 1000);

            // Build source references
            $sources = array_map(fn($record) => [
                'type' => $record['source'],
                'record_id' => $record['record_id'],
                'similarity' => $record['similarity'],
            ], $originalRecords);

            return [
                'success' => true,
                'question' => $question,
                'answer' => $answer,
                'sources' => $sources,
                'answered_at' => now()->toDateTimeString(),
            ];

        } catch (\Exception $e) {
            Log::error('AI Chat Error: ' . $e->getMessage(), [
                'clinic_id' => $clinicId,
                'question' => $question,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process your question: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Call OpenAI Chat API directly using Laravel Http client.
     */
    private function callOpenAI(string $apiKey, string $model, string $systemMessage, string $userMessage, int $maxTokens = 1000): string
    {
        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        if (str_contains($model, 'gpt-5')) {
            $body['max_completion_tokens'] = $maxTokens;
        } else {
            $body['max_tokens'] = $maxTokens;
            $body['temperature'] = 0.3;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', $body);

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \Exception($data['error']['message'] ?? 'OpenAI API error');
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (empty($content)) {
            if (str_contains($model, 'gpt-5')) {
                Log::warning('gpt-5-nano returned empty content, retrying with gpt-4o-mini');
                return $this->callOpenAI($apiKey, 'gpt-4o-mini', $systemMessage, $userMessage, $maxTokens);
            }
            return 'I could not generate a response. Please try again.';
        }

        return $content;
    }

    /**
     * Build a text context from the fetched original records.
     */
    private function buildContext(array $records): string
    {
        $contextParts = [];

        foreach ($records as $index => $record) {
            $num = $index + 1;
            $source = ucfirst(str_replace('_', ' ', $record['source']));
            $contextParts[] = "--- Record {$num} ({$source}, ID: {$record['record_id']}, Similarity: {$record['similarity']}) ---";
            $contextParts[] = $record['content'];
            $contextParts[] = '';
        }

        return implode("\n", $contextParts);
    }
}