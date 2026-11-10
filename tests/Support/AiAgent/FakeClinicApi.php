<?php

namespace Tests\Support\AiAgent;

use App\Services\AI\Agent\Api\ApiClient;
use App\Services\AI\Agent\Api\ApiContext;
use App\Services\AI\Agent\Api\ApiResponse;
use Carbon\CarbonImmutable;

/**
 * An in-memory clinic behind the agent's API client. It answers the same paths
 * and JSON shapes as the real tenant routes (see AgentApiContractTest for the
 * real ones), applies the API's own permission checks, records every request,
 * and can be told to fail or time out.
 */
class FakeClinicApi implements ApiClient
{
    public array $requests = [];

    public array $user = [
        'id' => 7,
        'name' => 'Sara',
        'roles' => ['clinic_super_doctor'],
        'permissions' => [
            'view-clinic-patients', 'create-patient', 'search-patient',
            'view-clinic-reservations', 'create-reservation', 'edit-reservation', 'delete-reservation',
            'view-clinic-cases', 'view-all-bills', 'create-bill', 'edit-bill',
            'view-clinic-settings', 'view-reports', 'view-doctors',
        ],
    ];

    public array $patients = [];
    public array $doctors = [['id' => 3, 'name' => 'محمد حسن']];
    public array $reservations = [];
    public array $cases = [];
    public array $bills = [];
    public array $hours = ['from' => '09:00', 'to' => '21:00'];
    public array $closedWeekdays = [5]; // Friday
    public int $duration = 30;
    public ?string $currency = 'IQD';

    /** @var array<string, list<array{response: ApiResponse, perform: bool}>> */
    private array $failures = [];

    private int $nextId = 500;

    public function addPatient(string $name, ?string $phone = null): int
    {
        $id = ++$this->nextId;
        $this->patients[$id] = ['id' => $id, 'name' => $name, 'phone' => $phone, 'age' => null, 'created_at' => '2026-01-01 10:00:00'];

        return $id;
    }

    public function addCase(int $patientId, int $price, string $category = 'حشوة'): int
    {
        $id = ++$this->nextId;
        $this->cases[$id] = ['id' => $id, 'patient_id' => $patientId, 'price' => $price, 'category' => $category, 'case_date' => '2026-11-01 10:00:00'];

        return $id;
    }

    public function addBill(int $caseId, int $price): int
    {
        $id = ++$this->nextId;
        $this->bills[$id] = ['id' => $id, 'billable_id' => $caseId, 'patient_id' => $this->cases[$caseId]['patient_id'],
            'price' => $price, 'is_paid' => true, 'created_at' => '2026-11-02 10:00:00'];

        return $id;
    }

    public function addReservation(int $patientId, string $date, string $time, int $statusId = 1, ?int $doctorId = 3): int
    {
        $id = ++$this->nextId;
        $this->reservations[$id] = [
            'id' => $id, 'patient_id' => $patientId, 'doctor_id' => $doctorId, 'status_id' => $statusId,
            'reservation_start_date' => $date, 'reservation_end_date' => $date,
            'reservation_from_time' => $time . ':00', 'reservation_to_time' => null, 'created_at' => '2026-11-01 09:00:00',
        ];

        return $id;
    }

    /** The next call to "METHOD path" fails with $response; with $perform the write still happens (a lost answer). */
    public function failNext(string $route, ApiResponse $response, bool $perform = false): void
    {
        $this->failures[$route][] = ['response' => $response, 'perform' => $perform];
    }

    public function writes(): array
    {
        return array_values(array_filter($this->requests, fn ($r) => $r['method'] !== 'GET'));
    }

    public function calls(string $method, string $path): array
    {
        return array_values(array_filter($this->requests, fn ($r) => $r['method'] === $method && $r['path'] === $path));
    }

    public function send(ApiContext $context, string $method, string $path, array $query = [], ?array $body = null, array $headers = []): ApiResponse
    {
        $this->requests[] = ['method' => $method, 'path' => $path, 'query' => $query, 'body' => $body, 'headers' => $headers, 'tenant' => $context->tenantId];

        $route = $method . ' ' . preg_replace('/\d+/', '{id}', $path);
        if (!empty($this->failures[$route])) {
            $failure = array_shift($this->failures[$route]);
            if ($failure['perform']) {
                $this->handle($method, $path, $query, $body ?? []);
            }

            return $failure['response'];
        }

        return $this->handle($method, $path, $query, $body ?? []);
    }

    private function handle(string $method, string $path, array $query, array $body): ApiResponse
    {
        return match (true) {
            $method === 'GET' && $path === 'auth/me' => $this->ok($this->user),
            $method === 'GET' && $path === 'patients' => $this->guard('view-clinic-patients') ?? $this->searchPatients($query),
            $method === 'POST' && $path === 'patients' => $this->guard('create-patient') ?? $this->createPatient($body),
            $method === 'GET' && $path === 'doctors-active' => $this->ok($this->doctors),
            $method === 'GET' && $path === 'reservations/available-slots' => $this->slots($query),
            $method === 'GET' && $path === 'reservations' => $this->ok(array_values(array_map(fn ($r) => $this->reservation($r), $this->filterReservations($query)))),
            $method === 'POST' && $path === 'reservations' => $this->createReservation($body),
            $method === 'PUT' && (bool) preg_match('#^reservations/(\d+)$#', $path, $m) => $this->updateReservation((int) $m[1], $body),
            $method === 'PATCH' && (bool) preg_match('#^reservations/(\d+)/status$#', $path, $m) => $this->changeStatus((int) $m[1], $body),
            $method === 'GET' && $path === 'cases' => $this->guardAny(['view-clinic-cases', 'create-bill']) ?? $this->listCases($query),
            $method === 'GET' && (bool) preg_match('#^bills/patient-balances/(\d+)/bills$#', $path, $m) => $this->guard('view-all-bills') ?? $this->patientBalance((int) $m[1]),
            $method === 'GET' && $path === 'bills/patient-balances' => $this->guard('view-all-bills') ?? $this->outstanding(),
            $method === 'GET' && $path === 'bills/payments' => $this->guard('view-all-bills') ?? $this->payments($query),
            $method === 'POST' && $path === 'bills' => $this->guard('create-bill') ?? $this->createBill($body),
            $method === 'GET' && $path === 'clinic-settings/currency' => $this->guard('view-clinic-settings') ?? $this->ok(['setting_key' => 'currency', 'setting_value' => $this->currency]),
            $method === 'GET' && $path === 'reports/dashboard/today' => $this->guard('view-reports') ?? new ApiResponse(200, ['success' => true,
                'data' => ['new_patients' => 2, 'reservations_today' => 5, 'revenue_today' => 250000, 'cases_today' => 3, 'expenses_today' => 40000],
                'display' => ['hide_numbers' => false]]),
            default => new ApiResponse(404, ['success' => false, 'message' => "No route {$method} {$path}"]),
        };
    }

    // ---- Handlers ---------------------------------------------------------------

    private function searchPatients(array $query): ApiResponse
    {
        $search = (string) ($query['search'] ?? '');
        // Like SQL LIKE: exact characters, so أحمد and احمد differ
        $found = array_filter($this->patients, fn ($p) => $search !== '' && (mb_stripos($p['name'], $search) !== false || str_contains((string) $p['phone'], $search)));

        return $this->ok(array_values($found));
    }

    private function createPatient(array $body): ApiResponse
    {
        if (empty($body['name'])) {
            return $this->invalid('name', 'The name field is required.');
        }
        $id = $this->addPatient($body['name'], $body['phone'] ?? null);
        $this->patients[$id]['created_at'] = CarbonImmutable::now()->format('Y-m-d H:i:s');

        return new ApiResponse(201, ['success' => true, 'data' => $this->patients[$id]]);
    }

    private function slots(array $query): ApiResponse
    {
        if (empty($query['date'])) {
            $days = [];
            $day = $this->today();
            for ($i = 0; $i < 14 && count($days) < (int) ($query['days'] ?? 7); $i++, $day = $day->addDay()) {
                $free = $this->freeTimes($day->toDateString());
                if ($free) {
                    $days[] = ['date' => $day->toDateString(), 'free_times_count' => count($free), 'first_free_time' => $free[0]];
                }
            }

            return $this->ok(['days' => $days, 'duration' => $this->duration]);
        }

        $date = $query['date'];
        $open = !in_array(CarbonImmutable::parse($date)->dayOfWeek, $this->closedWeekdays, true);

        return $this->ok([
            'date' => $date,
            'is_open' => $open,
            'opening_hours' => $open ? $this->hours : null,
            'duration' => $this->duration,
            'free_times' => $open ? $this->freeTimes($date) : [],
        ]);
    }

    public function freeTimes(string $date): array
    {
        if (in_array(CarbonImmutable::parse($date)->dayOfWeek, $this->closedWeekdays, true)) {
            return [];
        }
        $taken = array_map(fn ($r) => substr($r['reservation_from_time'], 0, 5), array_filter($this->reservations,
            fn ($r) => $r['reservation_start_date'] === $date && (int) $r['status_id'] !== 4));
        $now = $this->now();
        $times = [];
        $slot = CarbonImmutable::parse("{$date} {$this->hours['from']}", $now->timezone);
        $end = CarbonImmutable::parse("{$date} {$this->hours['to']}", $now->timezone);
        for (; $slot->addMinutes($this->duration)->lte($end); $slot = $slot->addMinutes($this->duration)) {
            if ($slot->gt($now) && !in_array($slot->format('H:i'), $taken, true)) {
                $times[] = $slot->format('H:i');
            }
        }

        return $times;
    }

    private function filterReservations(array $query): array
    {
        return array_filter($this->reservations, function ($r) use ($query) {
            $patient = $query['filter']['patient_id'] ?? null;

            return (!$patient || (int) $r['patient_id'] === (int) $patient)
                && (empty($query['from_date']) || $r['reservation_start_date'] >= $query['from_date'])
                && (empty($query['to_date']) || $r['reservation_start_date'] <= $query['to_date']);
        });
    }

    private function createReservation(array $body): ApiResponse
    {
        foreach (['patient_id', 'reservation_start_date', 'reservation_from_time'] as $field) {
            if (empty($body[$field])) {
                return $this->invalid($field, "The {$field} field is required.");
            }
        }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $body['reservation_from_time'])) {
            return $this->invalid('reservation_from_time', 'Reservation start time must be in HH:MM:SS format');
        }

        $id = $this->addReservation((int) $body['patient_id'], $body['reservation_start_date'], substr($body['reservation_from_time'], 0, 5), 1, $body['doctor_id'] ?? null);
        $this->reservations[$id]['reservation_to_time'] = $body['reservation_to_time'] ?? null;
        $this->reservations[$id]['created_at'] = CarbonImmutable::now()->format('Y-m-d H:i:s');

        return new ApiResponse(201, ['success' => true, 'message' => 'Reservation created successfully', 'data' => $this->reservation($this->reservations[$id])]);
    }

    private function updateReservation(int $id, array $body): ApiResponse
    {
        if (!isset($this->reservations[$id])) {
            return new ApiResponse(422, ['success' => false, 'message' => "Reservation with ID {$id} not found"]);
        }
        foreach (['status_id', 'doctor_id', 'reservation_start_date', 'reservation_end_date', 'reservation_from_time', 'reservation_to_time'] as $field) {
            if (array_key_exists($field, $body)) {
                $this->reservations[$id][$field] = $body[$field];
            }
        }
        if (!array_key_exists('status_id', $body)) {
            $this->reservations[$id]['status_id'] = 1; // what the real endpoint does
        }

        return $this->ok($this->reservation($this->reservations[$id]));
    }

    private function changeStatus(int $id, array $body): ApiResponse
    {
        if (!isset($this->reservations[$id])) {
            return new ApiResponse(422, ['success' => false, 'message' => "Reservation with ID {$id} not found"]);
        }
        $this->reservations[$id]['status_id'] = (int) $body['status_id'];

        return $this->ok($this->reservation($this->reservations[$id]));
    }

    private function listCases(array $query): ApiResponse
    {
        $patient = (int) ($query['filter']['patient_id'] ?? 0);
        $rows = [];
        foreach ($this->cases as $case) {
            if ($case['patient_id'] !== $patient) {
                continue;
            }
            $bills = array_values(array_filter($this->bills, fn ($b) => $b['billable_id'] === $case['id']));
            $rows[] = [
                'id' => $case['id'],
                'patient_id' => $case['patient_id'],
                'price' => $case['price'],
                'is_paid' => array_sum(array_column($bills, 'price')) >= $case['price'],
                'category' => ['id' => 1, 'name' => $case['category']],
                'status' => ['id' => 1, 'name_ar' => 'جديد', 'name_en' => 'New'],
                'case_date' => $case['case_date'],
                'bills' => array_map(fn ($b) => ['id' => $b['id'], 'price' => $b['price'], 'is_paid' => $b['is_paid'], 'created_at' => $b['created_at']], $bills),
            ];
        }

        return $this->ok($rows);
    }

    private function balance(int $patientId): array
    {
        $cases = array_filter($this->cases, fn ($c) => $c['patient_id'] === $patientId);
        $total = $paid = $unpaid = $unpaidCount = 0;
        foreach ($cases as $c) {
            $p = array_sum(array_column(array_filter($this->bills, fn ($b) => $b['billable_id'] === $c['id'] && $b['is_paid']), 'price'));
            $total += $c['price'];
            $paid += $p;
            $unpaid += max($c['price'] - $p, 0);
            $unpaidCount += $c['price'] > $p ? 1 : 0;
        }

        return ['id' => $patientId, 'name' => $this->patients[$patientId]['name'] ?? '', 'phone' => $this->patients[$patientId]['phone'] ?? null,
            'case_count' => count($cases), 'unpaid_case_count' => $unpaidCount, 'total_price' => $total, 'paid_amount' => $paid,
            'unpaid_amount' => $unpaid, 'last_payment_at' => null, 'payment_status' => $unpaid === 0 ? 'paid' : 'unpaid'];
    }

    private function patientBalance(int $patientId): ApiResponse
    {
        if (!isset($this->patients[$patientId])) {
            return new ApiResponse(404, ['success' => false, 'message' => 'Not found']);
        }

        return new ApiResponse(200, ['success' => true, 'data' => [], 'patient_balance' => $this->balance($patientId)]);
    }

    private function outstanding(): ApiResponse
    {
        $rows = array_values(array_filter(array_map(fn ($id) => $this->balance($id), array_keys($this->patients)), fn ($b) => $b['unpaid_amount'] > 0));
        usort($rows, fn ($a, $b) => $b['unpaid_amount'] <=> $a['unpaid_amount']);

        return new ApiResponse(200, ['success' => true, 'data' => $rows,
            'summary' => ['unpaid_amount' => array_sum(array_column($rows, 'unpaid_amount')), 'patient_count' => count($rows)]]);
    }

    private function payments(array $query): ApiResponse
    {
        $rows = array_values(array_filter($this->bills, fn ($b) => $b['is_paid'] && (empty($query['patient_id']) || $b['patient_id'] === (int) $query['patient_id'])));
        $rows = array_map(fn ($b) => $b + ['patient' => ['id' => $b['patient_id'], 'name' => $this->patients[$b['patient_id']]['name'] ?? '']], $rows);

        return new ApiResponse(200, ['success' => true, 'data' => $rows, 'summary' => ['paid_amount' => array_sum(array_column($rows, 'price')), 'payment_count' => count($rows)]]);
    }

    private function createBill(array $body): ApiResponse
    {
        if (!isset($body['price']) || !is_int($body['price'])) {
            return $this->invalid('price', 'Price must be a number');
        }
        $id = $this->addBill((int) $body['billable_id'], $body['price']);
        $this->bills[$id]['billable_type'] = $body['billable_type'] ?? null;
        $this->bills[$id]['created_at'] = CarbonImmutable::now()->format('Y-m-d H:i:s');

        return new ApiResponse(201, ['success' => true, 'message' => 'Bill created successfully', 'data' => $this->bills[$id]]);
    }

    // ---- Helpers ---------------------------------------------------------------

    private function reservation(array $r): array
    {
        $patient = $this->patients[$r['patient_id']] ?? null;
        $doctor = collect($this->doctors)->firstWhere('id', $r['doctor_id']);

        return $r + [
            'patient' => $patient ? ['id' => $patient['id'], 'name' => $patient['name'], 'phone' => $patient['phone']] : null,
            'doctor' => $doctor,
            'status' => ['id' => $r['status_id'], 'name' => (int) $r['status_id'] === 4 ? 'Cancelled' : 'New', 'name_ar' => (int) $r['status_id'] === 4 ? 'ملغي' : 'جديد'],
        ];
    }

    private function guard(string $permission): ?ApiResponse
    {
        return $this->guardAny([$permission]);
    }

    private function guardAny(array $permissions): ?ApiResponse
    {
        return array_intersect($permissions, $this->user['permissions']) === []
            ? new ApiResponse(403, ['success' => false, 'message' => 'You do not have permission to perform this action'])
            : null;
    }

    private function ok(mixed $data): ApiResponse
    {
        return new ApiResponse(200, ['success' => true, 'data' => $data]);
    }

    private function invalid(string $field, string $message): ApiResponse
    {
        return new ApiResponse(422, ['message' => $message, 'errors' => [$field => [$message]]]);
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('ai_agent.timezone') ?: config('app.timezone'));
    }

    private function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }
}
