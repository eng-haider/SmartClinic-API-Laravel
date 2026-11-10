<?php

namespace App\Services\AI\Agent\Tools;

/**
 * Every API operation the agent can use, mapped to the clinic's real routes
 * (routes/tenant.php, under /api/tenant). Permission names are the ones the
 * controllers and config/rolesAndPermissions.php use.
 *
 * Deliberately absent:
 *  - GET reservations/{id}: it marks the appointment Completed as a side effect.
 *  - PATCH bills/{id}/mark-paid: it flags one payment row, it does not settle an
 *    invoice. "Mark the invoice paid" records the remaining amount instead.
 *  - Any payment gateway: the system has none.
 */
class ToolRegistry
{
    private const RESERVATION_READ = ['view-clinic-reservations', 'view-own-reservations', 'view-all-reservations'];

    /** @var array<string, ToolDefinition> */
    private array $tools = [];

    public function __construct()
    {
        $cancelled = (int) config('ai_agent.statuses.cancelled', 4);

        $definitions = [
            new ToolDefinition(
                'get_current_user', "The signed-in user's roles and permissions",
                'GET', 'auth/me',
            ),
            new ToolDefinition(
                'search_patients', 'Find patients by name, phone or identifier',
                'GET', 'patients',
                params: [
                    'search' => ['type' => 'string', 'required' => true],
                    'per_page' => ['type' => 'int'],
                ],
                permissions: ['view-clinic-patients'],
                operation: 'op.view_patients',
            ),
            new ToolDefinition(
                'create_patient', 'Add a new patient record',
                'POST', 'patients',
                params: [
                    'name' => ['type' => 'string', 'required' => true],
                    'phone' => ['type' => 'string'],
                    'age' => ['type' => 'int'],
                    'sex' => ['type' => 'int'],
                ],
                permissions: ['create-patient'],
                requiresConfirmation: true,
                operation: 'op.create_patient',
            ),
            new ToolDefinition(
                'list_active_doctors', "The clinic's active doctors",
                'GET', 'doctors-active',
            ),
            new ToolDefinition(
                'get_available_slots', 'Free appointment times on a day, or the next days that have some',
                'GET', 'reservations/available-slots',
                params: [
                    'date' => ['type' => 'date'],
                    'days' => ['type' => 'int'],
                ],
                permissions: array_merge(['create-reservation'], self::RESERVATION_READ),
                operation: 'op.view_appointments',
            ),
            new ToolDefinition(
                'list_appointments', 'Appointments, optionally for one patient or doctor, in a date range',
                'GET', 'reservations',
                params: [
                    'patient_id' => ['type' => 'int', 'as' => 'filter.patient_id'],
                    'doctor_id' => ['type' => 'int', 'as' => 'filter.doctor_id'],
                    'from_date' => ['type' => 'date'],
                    'to_date' => ['type' => 'date'],
                    'per_page' => ['type' => 'int'],
                ],
                fixed: ['include' => 'patient,doctor,status', 'sort' => 'reservation_start_date'],
                permissions: self::RESERVATION_READ,
                operation: 'op.view_appointments',
            ),
            new ToolDefinition(
                'create_appointment', 'Book an appointment',
                'POST', 'reservations',
                params: [
                    'patient_id' => ['type' => 'int', 'required' => true],
                    'doctor_id' => ['type' => 'int'],
                    'reservation_start_date' => ['type' => 'date', 'required' => true],
                    'reservation_end_date' => ['type' => 'date'],
                    'reservation_from_time' => ['type' => 'time', 'required' => true],
                    'reservation_to_time' => ['type' => 'time'],
                    'notes' => ['type' => 'string'],
                ],
                permissions: ['create-reservation'],
                requiresConfirmation: true,
                operation: 'op.book',
            ),
            new ToolDefinition(
                'reschedule_appointment', 'Move an appointment to another date or time',
                'PUT', 'reservations/{id}',
                params: [
                    'id' => ['type' => 'int', 'required' => true],
                    'patient_id' => ['type' => 'int', 'required' => true],
                    // PUT resets the status to "New" unless it is sent, so it is required
                    'status_id' => ['type' => 'int', 'required' => true],
                    'doctor_id' => ['type' => 'int'],
                    'reservation_start_date' => ['type' => 'date', 'required' => true],
                    'reservation_end_date' => ['type' => 'date'],
                    'reservation_from_time' => ['type' => 'time', 'required' => true],
                    'reservation_to_time' => ['type' => 'time'],
                ],
                permissions: ['edit-reservation'],
                requiresConfirmation: true,
                operation: 'op.edit_appointment',
            ),
            new ToolDefinition(
                'cancel_appointment', 'Cancel an appointment (status Cancelled; the record is kept)',
                'PATCH', 'reservations/{id}/status',
                params: ['id' => ['type' => 'int', 'required' => true]],
                fixed: ['status_id' => $cancelled],
                permissions: ['edit-reservation'],
                requiresConfirmation: true,
                operation: 'op.edit_appointment',
            ),
            new ToolDefinition(
                'list_patient_cases', "A patient's cases (the invoices) with the payments recorded on each",
                'GET', 'cases',
                params: [
                    'patient_id' => ['type' => 'int', 'required' => true, 'as' => 'filter.patient_id'],
                    'per_page' => ['type' => 'int'],
                ],
                fixed: ['include' => 'bills,category,status'],
                permissions: ['view-clinic-cases', 'create-bill'],
                operation: 'op.view_cases',
            ),
            new ToolDefinition(
                'get_patient_balance', "A patient's lifetime balance and bill history",
                'GET', 'bills/patient-balances/{patient_id}/bills',
                params: [
                    'patient_id' => ['type' => 'int', 'required' => true],
                    'per_page' => ['type' => 'int'],
                ],
                permissions: ['view-all-bills'],
                operation: 'op.view_bills',
            ),
            new ToolDefinition(
                'list_outstanding_balances', 'Patients who still owe money, largest balance first',
                'GET', 'bills/patient-balances',
                params: [
                    'search' => ['type' => 'string'],
                    'per_page' => ['type' => 'int'],
                ],
                fixed: ['payment_status' => 'unpaid', 'sort' => '-unpaid_amount'],
                permissions: ['view-all-bills'],
                operation: 'op.view_bills',
            ),
            new ToolDefinition(
                'list_payments', 'Recorded payments, newest first',
                'GET', 'bills/payments',
                params: [
                    'patient_id' => ['type' => 'int'],
                    'date_from' => ['type' => 'date'],
                    'date_to' => ['type' => 'date'],
                    'per_page' => ['type' => 'int'],
                ],
                fixed: ['sort' => '-created_at'],
                permissions: ['view-all-bills'],
                operation: 'op.view_bills',
            ),
            new ToolDefinition(
                'record_payment', 'Record money the clinic received against a case (POST /bills)',
                'POST', 'bills',
                params: [
                    'patient_id' => ['type' => 'int', 'required' => true],
                    'billable_id' => ['type' => 'int', 'required' => true],
                    'price' => ['type' => 'int', 'required' => true],
                ],
                fixed: ['billable_type' => config('ai_agent.case_billable_type', 'App\\Models\\Case')],
                permissions: ['create-bill'],
                requiresConfirmation: true,
                operation: 'op.record_payment',
            ),
            new ToolDefinition(
                'get_clinic_setting', 'One clinic setting (e.g. currency)',
                'GET', 'clinic-settings/{key}',
                params: ['key' => ['type' => 'string', 'required' => true]],
                permissions: ['view-clinic-settings'],
            ),
            new ToolDefinition(
                'get_today_report', "Today's dashboard numbers",
                'GET', 'reports/dashboard/today',
                permissions: ['view-reports'],
                operation: 'op.reports',
            ),
        ];

        foreach ($definitions as $tool) {
            $this->tools[$tool->name] = $tool;
        }
    }

    public function get(string $name): ToolDefinition
    {
        if (!isset($this->tools[$name])) {
            throw new \InvalidArgumentException("Unknown agent tool [{$name}]");
        }

        return $this->tools[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /** @return array<string, ToolDefinition> */
    public function all(): array
    {
        return $this->tools;
    }
}
