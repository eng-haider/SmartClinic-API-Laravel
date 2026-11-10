<?php

namespace App\Services\AI\Agent\Flows;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Api\ApiResponse;
use App\Services\AI\Agent\Language\AmountParser;
use App\Services\AI\Agent\Language\DateTimeResolver;
use App\Services\AI\Agent\Language\Messages;
use App\Services\AI\Agent\Language\TextNormalizer;
use App\Services\AI\Agent\Nlu\Intent;
use App\Services\AI\Agent\PendingActions;
use App\Services\AI\Agent\Tools\ToolExecutor;
use App\Services\AI\Agent\Tools\ToolForbidden;
use Carbon\CarbonImmutable;

/**
 * One kind of request (book, pay, cancel...). handle() works through the
 * draft's details, asking for whatever is missing or ambiguous, and ends in an
 * answer or a confirmation card. execute() runs a confirmed action.
 *
 * Every lookup and every change goes through ToolExecutor (the REST API).
 */
abstract class Flow
{
    public function __construct(
        protected ToolExecutor $tools,
        protected PendingActions $actions,
        protected DateTimeResolver $clock,
        protected AmountParser $amounts,
    ) {
    }

    abstract public function handle(AgentSession $s): AgentReply;

    /** Runs a confirmed action that this request has claimed. */
    public function execute(AgentSession $s, AiAgentAction $action): AgentReply
    {
        throw new \LogicException(static::class . ' has no action to execute');
    }

    /**
     * After a write got no answer: the record it created or changed, [] when it
     * certainly did not happen, null when that cannot be checked.
     */
    public function verify(AgentSession $s, AiAgentAction $action): ?array
    {
        return null;
    }

    /** The reply for an action the API carried out. */
    public function successReply(AgentSession $s, AiAgentAction $action, array $data): AgentReply
    {
        return AgentReply::make(AgentReply::SUCCESS, $action->summary['title'] ?? '', $s->language);
    }

    // ---- Wording ------------------------------------------------------------

    protected function t(AgentSession $s, string $key, array $vars = []): string
    {
        return Messages::t($key, $s->language, $vars);
    }

    protected function money(AgentSession $s, int|float|null $amount, bool $long = false): string
    {
        return Messages::money($amount, $this->clinicCurrency($s), $s->language, $long);
    }

    protected function doctorLabel(AgentSession $s, ?string $name): string
    {
        if (!$name) {
            return $this->t($s, 'no_doctor');
        }
        if ($s->language === 'en' || preg_match('/^(د\.|د |دكتور|الدكتور|Dr)/u', $name)) {
            return $name;
        }

        return 'د. ' . $name;
    }

    // ---- API calls ------------------------------------------------------------

    protected function call(AgentSession $s, string $tool, array $params = [], ?AiAgentAction $claimed = null): ApiResponse
    {
        try {
            return $this->tools->run($s, $tool, $params, $claimed);
        } catch (ToolForbidden $e) {
            throw new Halt($this->forbidden($s, $e->tool->operation ?: 'op.view_patients'));
        }
    }

    /** A read; any failure ends the flow with an error reply. Cached for the request. */
    protected function read(AgentSession $s, string $tool, array $params = []): ApiResponse
    {
        $key = $tool . ':' . json_encode($params);
        if (isset($s->cache[$key])) {
            return $s->cache[$key];
        }

        $response = $this->call($s, $tool, $params);
        if (!$response->ok()) {
            throw new Halt($this->apiFailure($s, $response));
        }

        return $s->cache[$key] = $response;
    }

    protected function requirePermission(AgentSession $s, string $tool): void
    {
        if (!$this->tools->can($s, $tool)) {
            throw new Halt($this->forbidden($s, $this->tools->registry()->get($tool)->operation));
        }
    }

    protected function forbidden(AgentSession $s, string $operation): AgentReply
    {
        return AgentReply::make(AgentReply::ERROR, $this->t($s, 'no_permission', [
            'operation' => $this->t($s, $operation ?: 'op.view_patients'),
        ]), $s->language);
    }

    protected function apiFailure(AgentSession $s, ApiResponse $r): AgentReply
    {
        $text = match (true) {
            $r->serverError() => $this->t($s, 'api_unavailable'),
            $r->forbidden() => $this->t($s, 'forbidden'),
            $r->invalid() => $this->t($s, 'validation_error', ['message' => $r->message()]),
            default => $this->t($s, 'api_error', ['message' => $r->message()]),
        };

        return AgentReply::make(AgentReply::ERROR, $text, $s->language);
    }

    // ---- Questions -------------------------------------------------------------

    /** Ask for one detail; the next message fills $awaiting (a draft slot, or "choice"). */
    protected function ask(AgentSession $s, string $awaiting, string $text, array $options = []): AgentReply
    {
        $s->await($awaiting, $options);

        return AgentReply::make(AgentReply::CLARIFICATION, $text, $s->language)->options($options);
    }

    /** Buttons that come with an answer; picking one starts its own request. */
    protected function offer(AgentSession $s, AgentReply $reply, array $options): AgentReply
    {
        $s->offer($options);

        return $reply->options($options);
    }

    /** A choice; picking it merges $set into the draft (or starts $extra['intent']). */
    protected function option(string $label, array $set = [], array $extra = []): array
    {
        return ['id' => 'o' . substr(md5($label . json_encode($set) . json_encode($extra)), 0, 10), 'label' => $label, 'set' => $set] + $extra;
    }

    // ---- Confirmation ------------------------------------------------------------

    /**
     * Stores the action as pending and returns its confirmation card.
     *
     * @param list<array{0: string, 1: ?string}> $fields label key and value
     */
    protected function confirm(
        AgentSession $s,
        string $tool,
        array $params,
        string $title,
        string $questionKey,
        string $buttonKey,
        array $fields,
        array $snapshot = [],
        array $notes = [],
    ): AgentReply {
        $reply = AgentReply::make(AgentReply::CONFIRMATION, $title, $s->language);
        foreach ($fields as [$label, $value]) {
            $reply->field($label, $value);
        }
        foreach ($notes as $note) {
            $reply->note($note);
        }

        $action = $this->actions->create($s, $tool, array_filter($params, fn ($v) => $v !== null), [
            'title' => $title,
            'fields' => $reply->fields,
            'question' => $questionKey,
            'button' => $buttonKey,
        ], $snapshot, $s->slots());

        $s->await(null);

        return $reply->ask($this->t($s, $questionKey))->withAction($action, $buttonKey);
    }

    /** Records the API's answer to a confirmed write and builds the reply. */
    protected function conclude(AgentSession $s, AiAgentAction $action, ApiResponse $r): AgentReply
    {
        if ($r->ok()) {
            $data = is_array($r->data()) ? $r->data() : [];
            $this->actions->finish($action, AiAgentAction::EXECUTED, $r->status, $data);
            $s->clearDraft();

            return $this->successReply($s, $action, $data);
        }

        if ($r->noAnswer()) {
            // The write may or may not have happened: look before saying anything
            $this->actions->finish($action, AiAgentAction::UNKNOWN, null, null, $r->transportError);

            return $this->recover($s, $action);
        }

        $this->actions->finish($action, AiAgentAction::FAILED, $r->status, $r->body, $r->message());

        return $this->apiFailure($s, $r);
    }

    /** Settles an action whose outcome is unknown by reading it back from the API. */
    public function recover(AgentSession $s, AiAgentAction $action): AgentReply
    {
        $found = $this->verify($s, $action);

        if (is_array($found) && $found !== []) {
            $this->actions->finish($action, AiAgentAction::EXECUTED, 200, $found);
            $s->clearDraft();

            return $this->successReply($s, $action, $found);
        }

        if ($found === []) {
            $this->actions->finish($action, AiAgentAction::FAILED, null, null, 'No answer; verified not executed');

            return AgentReply::make(AgentReply::ERROR, $this->t($s, 'action_unknown'), $s->language);
        }

        return AgentReply::make(AgentReply::ERROR, $this->t($s, 'action_unknown_unverified'), $s->language);
    }

    // ---- Patients -----------------------------------------------------------------

    /**
     * The patient the request is about: already chosen, found by name or phone
     * through the API, or (with $implicitFocus, or "him/her") the one being
     * discussed. Several matches are listed for the user to pick; nobody is
     * chosen for them.
     *
     * @return array{id: int, name: string, phone: ?string}
     */
    protected function resolvePatient(AgentSession $s, bool $implicitFocus = false): array
    {
        if ($id = $s->slot('patient_id')) {
            return ['id' => (int) $id, 'name' => (string) $s->slot('patient_label', ''), 'phone' => $s->slot('patient_phone_display')];
        }

        $phone = $s->slot('patient_phone');
        $name = $s->slot('patient_name');
        $query = $phone ?: $name;
        $focus = $s->focusPatient();

        if (!$query && $focus && ($implicitFocus || $s->slot('patient_ref') === 'previous')) {
            $this->setPatient($s, $focus);

            return ['id' => (int) $focus['id'], 'name' => $focus['name'], 'phone' => $focus['phone'] ?? null];
        }

        if (!$query) {
            throw new Halt($this->ask($s, 'patient_name', $this->t($s, 'ask_patient')));
        }

        $this->requirePermission($s, 'search_patients');

        $variants = $phone
            ? [preg_replace('/\D+/', '', TextNormalizer::digits($phone)) ?: $phone]
            : array_slice(TextNormalizer::nameVariants($name), 0, 4);

        $found = [];
        foreach ($variants as $variant) {
            foreach ($this->read($s, 'search_patients', ['search' => $variant, 'per_page' => 10])->data([]) as $patient) {
                $found[$patient['id']] = $patient;
            }
        }

        if ($found === []) {
            $s->forget('patient_name', 'patient_phone', 'patient_ref');
            $options = [];
            if (!$phone && $this->tools->can($s, 'create_patient')) {
                $options[] = $this->option($this->t($s, 'patient_create_option', ['name' => $name]), [], [
                    'intent' => Intent::CREATE_PATIENT,
                    'with' => ['name' => $name],
                    'resume' => true,
                ]);
            }

            throw new Halt($this->ask($s, 'patient_name', $this->t($s, 'patient_not_found', ['name' => $query]), $options));
        }

        if (count($found) === 1) {
            $patient = reset($found);
            $this->setPatient($s, $patient);

            return ['id' => (int) $patient['id'], 'name' => $patient['name'], 'phone' => $patient['phone'] ?? null];
        }

        $options = array_map(fn ($p) => $this->option(
            $p['name'] . (!empty($p['phone']) ? ' — ' . $p['phone'] : ''),
            ['patient_id' => $p['id'], 'patient_label' => $p['name'], 'patient_phone_display' => $p['phone'] ?? null],
        ), array_slice(array_values($found), 0, 8));

        throw new Halt($this->ask($s, 'choice', $this->t($s, 'choose_patient', ['name' => $query]), $options));
    }

    protected function setPatient(AgentSession $s, array $patient): void
    {
        $s->setSlots([
            'patient_id' => (int) $patient['id'],
            'patient_label' => $patient['name'],
            'patient_phone_display' => $patient['phone'] ?? null,
        ]);
        $s->forget('patient_name', 'patient_phone', 'patient_ref');
        $s->setFocusPatient((int) $patient['id'], (string) $patient['name'], $patient['phone'] ?? null);
    }

    protected function patientLine(array $patient): string
    {
        return $patient['name'] . (!empty($patient['phone']) ? ' (' . $patient['phone'] . ')' : '');
    }

    // ---- Dates, times and free slots ---------------------------------------------------

    /**
     * The date in $dateKey, resolving the user's words in $textKey; asks when
     * they are missing, in the past or ambiguous.
     */
    protected function resolveDate(AgentSession $s, string $dateKey, string $textKey, string $askKey = 'ask_date'): string
    {
        if ($date = $s->slot($dateKey)) {
            return $date;
        }

        $text = $s->slot($textKey);
        if (!$text) {
            throw new Halt($this->ask($s, $textKey, $this->t($s, $askKey)));
        }

        $result = $this->clock->resolveDate($text);
        $s->forget($textKey);

        switch ($result['status']) {
            case 'ok':
                $s->setSlots([$dateKey => $result['date']]);

                return $result['date'];

            case 'past':
                throw new Halt($this->ask($s, $textKey, $this->t($s, 'date_past', ['date' => Messages::date($result['date'], $s->language)])));

            case 'ambiguous':
                if (($result['reason'] ?? '') === 'same_weekday') {
                    [$a, $b] = $result['options'];
                    $options = [
                        $this->option(Messages::date($a, $s->language), [$dateKey => $a]),
                        $this->option(Messages::date($b, $s->language), [$dateKey => $b]),
                    ];

                    throw new Halt($this->ask($s, 'choice', $this->t($s, 'choose_same_weekday', [
                        'a' => Messages::date($a, $s->language), 'b' => Messages::date($b, $s->language),
                    ]), $options));
                }

                throw new Halt($this->ask($s, $textKey, $this->t($s, 'ask_day_of_week')));
        }

        // The resolver could not read it; accept the LLM's date only if it is a real future date
        $llm = $s->slot($dateKey . '_llm');
        if ($llm && preg_match('/^\d{4}-\d{2}-\d{2}$/', $llm) && $llm >= $this->clock->now()->toDateString()) {
            $s->setSlots([$dateKey => $llm]);

            return $llm;
        }

        throw new Halt($this->ask($s, $textKey, $this->t($s, 'date_unclear', ['text' => $text])));
    }

    /** The day's free times from the API; asks for another day when the clinic is closed. */
    protected function openDay(AgentSession $s, string $date, string $dateKey, string $textKey): array
    {
        $day = $this->read($s, 'get_available_slots', ['date' => $date])->data([]);

        if (empty($day['is_open'])) {
            $s->forget($dateKey, $textKey);

            throw new Halt($this->ask($s, $textKey, $this->t($s, 'clinic_closed', [
                'date' => Messages::date($date, $s->language),
            ]), $this->nextDayOptions($s, $dateKey)));
        }

        return $day;
    }

    protected function nextDayOptions(AgentSession $s, string $dateKey): array
    {
        $days = $this->read($s, 'get_available_slots', ['days' => 5])->data()['days'] ?? [];

        return array_map(fn ($d) => $this->option(
            Messages::date($d['date'], $s->language),
            [$dateKey => $d['date']],
        ), $days);
    }

    /**
     * A free time on the day: already chosen, read from the user's words, or
     * asked for. "الساعة 5" is settled by opening hours when only one reading
     * fits them; otherwise the user picks.
     */
    protected function resolveTime(AgentSession $s, array $day, string $timeKey, string $textKey): string
    {
        $free = $day['free_times'] ?? [];
        $date = $day['date'];

        $time = $s->slot($timeKey);
        if (!$time) {
            $text = $s->slot($textKey);

            if (!$text) {
                if ($free === []) {
                    throw new Halt($this->ask($s, $textKey, $this->t($s, 'no_free_times', ['date' => Messages::date($date, $s->language)])));
                }

                throw new Halt($this->ask($s, $textKey, $this->t($s, 'ask_time_free', [
                    'date' => Messages::date($date, $s->language),
                ]), $this->timeOptions($s, array_slice($free, 0, 8), $timeKey)));
            }

            $result = $this->clock->resolveTime($text);
            $s->forget($textKey);

            switch ($result['status']) {
                case 'ok':
                    $time = $result['time'];
                    break;

                case 'ambiguous_meridiem':
                    $candidates = $result['candidates'];
                    $inHours = array_values(array_filter($candidates, fn ($c) => $this->withinHours($c, $day['opening_hours'] ?? null)));
                    if (count($candidates) === 1 || count($inHours) === 1) {
                        $time = $inHours[0] ?? $candidates[0];
                        break;
                    }
                    if ($inHours === []) {
                        $time = $candidates[count($candidates) - 1];
                        break; // outside hours: reported as unavailable below
                    }

                    throw new Halt($this->ask($s, 'choice', $this->t($s, 'ask_meridiem', [
                        'a' => Messages::time($inHours[0], $s->language),
                        'b' => Messages::time($inHours[1], $s->language),
                    ]), $this->timeOptions($s, $inHours, $timeKey)));

                case 'period':
                    [$from, $to] = DateTimeResolver::PERIOD_RANGES[$result['period']];
                    $inPeriod = array_values(array_filter($free, fn ($t) => $t >= $from && $t < $to));
                    if ($inPeriod === []) {
                        throw new Halt($this->ask($s, $textKey, $this->t($s, 'no_free_times', ['date' => Messages::date($date, $s->language)]),
                            $this->timeOptions($s, array_slice($free, 0, 8), $timeKey)));
                    }

                    throw new Halt($this->ask($s, $textKey, $this->t($s, 'ask_time_free', [
                        'date' => Messages::date($date, $s->language),
                    ]), $this->timeOptions($s, array_slice($inPeriod, 0, 8), $timeKey)));

                default:
                    $llm = $s->slot($timeKey . '_llm');
                    if ($llm && preg_match('/^\d{2}:\d{2}$/', $llm)) {
                        $time = $llm;
                        break;
                    }

                    throw new Halt($this->ask($s, $textKey, $this->t($s, 'time_unclear', ['text' => $text]),
                        $this->timeOptions($s, array_slice($free, 0, 8), $timeKey)));
            }
        }

        if (!in_array($time, $free, true)) {
            $s->forget($timeKey, $textKey);
            $reply = $this->ask($s, $textKey, $this->t($s, 'slot_taken', ['time' => Messages::time($time, $s->language)]),
                $this->timeOptions($s, $this->nearest($free, $time), $timeKey));
            if ($reply->options) {
                $reply->ask($this->t($s, 'nearest_times'));
            }

            throw new Halt($reply);
        }

        $s->setSlots([$timeKey => $time]);

        return $time;
    }

    protected function timeOptions(AgentSession $s, array $times, string $timeKey): array
    {
        return array_map(fn ($t) => $this->option(Messages::time($t, $s->language), [$timeKey => $t]), $times);
    }

    /** Free times closest to the wanted one. */
    protected function nearest(array $free, string $wanted, int $limit = 6): array
    {
        $minutes = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $target = $minutes($wanted);
        usort($free, fn ($a, $b) => abs($minutes($a) - $target) <=> abs($minutes($b) - $target));
        $picked = array_slice($free, 0, $limit);
        sort($picked);

        return $picked;
    }

    protected function withinHours(string $time, ?array $hours): bool
    {
        if (!$hours) {
            return false;
        }
        $to = $hours['to'] <= $hours['from'] ? '24:00' : $hours['to'];

        return $time >= $hours['from'] && $time < $to;
    }

    protected function addMinutes(string $time, int $minutes): ?string
    {
        $start = CarbonImmutable::createFromFormat('H:i', substr($time, 0, 5));
        $end = $start->addMinutes($minutes);

        return $end->isSameDay($start) ? $end->format('H:i:s') : null;
    }

    // ---- Money -----------------------------------------------------------------------

    /** The clinic's currency setting, when the user may read it. */
    protected function clinicCurrency(AgentSession $s): ?string
    {
        if (array_key_exists('currency', $s->cache)) {
            return $s->cache['currency'];
        }

        $currency = null;
        if ($this->tools->can($s, 'get_clinic_setting')) {
            $r = $this->tools->run($s, 'get_clinic_setting', ['key' => 'currency']);
            $value = $r->ok() ? strtoupper(trim((string) $r->get('data.setting_value'))) : '';
            $currency = $value !== '' ? $value : null;
        }

        return $s->cache['currency'] = $currency;
    }

    // ---- Appointments ------------------------------------------------------------------

    /** The reservation fields the agent keeps between turns. */
    protected function compactReservation(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'patient_id' => (int) ($r['patient_id'] ?? $r['patient']['id'] ?? 0),
            'patient_name' => $r['patient']['name'] ?? null,
            'patient_phone' => $r['patient']['phone'] ?? null,
            'doctor_id' => $r['doctor_id'] ?? null,
            'doctor_name' => $r['doctor']['name'] ?? null,
            'status_id' => $r['status_id'] ?? null,
            'date' => $r['reservation_start_date'] ?? null,
            'from' => isset($r['reservation_from_time']) ? substr($r['reservation_from_time'], 0, 5) : null,
            'to' => isset($r['reservation_to_time']) ? substr($r['reservation_to_time'], 0, 5) : null,
        ];
    }

    protected function reservationLabel(AgentSession $s, array $r): string
    {
        return trim(($r['patient_name'] ?? '') . ' — ' . Messages::date($r['date'], $s->language) . ' ' . Messages::time($r['from'], $s->language));
    }

    /**
     * The upcoming appointment the user means, found through the API from the
     * patient, day and time they gave. Several matches are listed to pick from.
     */
    protected function findAppointment(AgentSession $s): array
    {
        if ($reservation = $s->slot('reservation')) {
            return $reservation;
        }

        $this->requirePermission($s, 'list_appointments');
        $today = $this->clock->now()->toDateString();

        $hasPatient = $s->slot('patient_id') || $s->slot('patient_name') || $s->slot('patient_phone') || $s->slot('patient_ref');
        $date = null;
        if ($s->slot('date') || $s->slot('date_text')) {
            $date = $this->resolveDate($s, 'date', 'date_text', 'ask_which_appointment');
        }
        $timeText = $s->slot('time_text');

        $patient = null;
        if ($hasPatient || (!$date && !$timeText)) {
            $patient = $this->resolvePatient($s, true);
        }

        $list = $this->read($s, 'list_appointments', array_filter([
            'patient_id' => $patient['id'] ?? null,
            'from_date' => $date ?? $today,
            'to_date' => $date ?? $this->clock->now()->addDays(90)->toDateString(),
            'per_page' => 100,
        ]))->data([]);

        $closed = [(int) config('ai_agent.statuses.cancelled', 4), (int) config('ai_agent.statuses.completed', 3)];
        $candidates = [];
        if ($timeText) {
            $time = $this->clock->resolveTime($timeText);
            $candidates = $time['status'] === 'ok' ? [$time['time']] : ($time['candidates'] ?? []);
        }

        $matches = array_values(array_filter(array_map(fn ($r) => $this->compactReservation($r), $list), function ($r) use ($closed, $candidates) {
            if (in_array((int) $r['status_id'], $closed, true)) {
                return false;
            }

            return $candidates === [] || in_array($r['from'], $candidates, true);
        }));

        usort($matches, fn ($a, $b) => [$a['date'], $a['from']] <=> [$b['date'], $b['from']]);

        if ($matches === []) {
            $s->forget('date', 'date_text', 'time_text');
            throw new Halt(AgentReply::make(AgentReply::INFO, $this->t($s, 'no_upcoming_appointments'), $s->language));
        }

        if (count($matches) === 1) {
            $s->setSlots(['reservation' => $matches[0]]);
            $s->forget('time_text');

            return $matches[0];
        }

        $options = array_map(fn ($r) => $this->option($this->reservationLabel($s, $r), ['reservation' => $r]), array_slice($matches, 0, 8));

        throw new Halt($this->ask($s, 'choice', $this->t($s, 'ask_which_appointment'), $options));
    }

    /** The reservation as the API has it now, or null when it is gone. */
    protected function currentReservation(AgentSession $s, array $snapshot): ?array
    {
        $r = $this->call($s, 'list_appointments', [
            'patient_id' => $snapshot['patient_id'],
            'from_date' => $snapshot['date'],
            'to_date' => $snapshot['date'],
            'per_page' => 100,
        ]);
        if (!$r->ok()) {
            throw new Halt($this->apiFailure($s, $r));
        }

        foreach ($r->data([]) as $row) {
            if ((int) $row['id'] === (int) $snapshot['id']) {
                return $this->compactReservation($row);
            }
        }

        return null;
    }
}
