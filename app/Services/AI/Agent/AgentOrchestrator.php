<?php

namespace App\Services\AI\Agent;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\Flows\BookAppointmentFlow;
use App\Services\AI\Agent\Flows\CancelAppointmentFlow;
use App\Services\AI\Agent\Flows\CheckAvailabilityFlow;
use App\Services\AI\Agent\Flows\CreatePatientFlow;
use App\Services\AI\Agent\Flows\Flow;
use App\Services\AI\Agent\Flows\Halt;
use App\Services\AI\Agent\Flows\PaymentHistoryFlow;
use App\Services\AI\Agent\Flows\RecordPaymentFlow;
use App\Services\AI\Agent\Flows\ReportFlow;
use App\Services\AI\Agent\Flows\RescheduleAppointmentFlow;
use App\Services\AI\Agent\Flows\SearchPatientFlow;
use App\Services\AI\Agent\Flows\ViewAppointmentsFlow;
use App\Services\AI\Agent\Flows\ViewBalanceFlow;
use App\Services\AI\Agent\Flows\ViewCasesFlow;
use App\Services\AI\Agent\Flows\ViewUnpaidFlow;
use App\Services\AI\Agent\Language\ConfirmationMatcher;
use App\Services\AI\Agent\Language\DateTimeResolver;
use App\Services\AI\Agent\Language\LanguageDetector;
use App\Services\AI\Agent\Language\Messages;
use App\Services\AI\Agent\Language\TextNormalizer;
use App\Services\AI\Agent\Nlu\Intent;
use App\Services\AI\Agent\Nlu\IntentParser;
use App\Services\AI\Agent\Nlu\ParsedMessage;
use App\Services\AI\Agent\Tools\ToolExecutor;
use Illuminate\Contracts\Container\Container;

/**
 * The agent's turn logic.
 *
 *  message -> (yes/no for the waiting action, matched without the LLM)
 *          -> (a pick from the options shown)
 *          -> LLM: intent + the user's words for each detail
 *          -> continue the request being filled in, or start a new one
 *          -> flow: API lookups, questions, and finally a confirmation card
 *  confirm -> claim the action once -> flow re-checks and calls the API
 *
 * The LLM never confirms, never chooses a tool's parameters directly and never
 * sees tokens; a "yes" it reads into a longer message is not acted on.
 */
class AgentOrchestrator
{
    private const FLOWS = [
        Intent::BOOK_APPOINTMENT => BookAppointmentFlow::class,
        Intent::RESCHEDULE_APPOINTMENT => RescheduleAppointmentFlow::class,
        Intent::CANCEL_APPOINTMENT => CancelAppointmentFlow::class,
        Intent::VIEW_APPOINTMENTS => ViewAppointmentsFlow::class,
        Intent::CHECK_AVAILABILITY => CheckAvailabilityFlow::class,
        Intent::RECORD_PAYMENT => RecordPaymentFlow::class,
        Intent::VIEW_BALANCE => ViewBalanceFlow::class,
        Intent::VIEW_UNPAID => ViewUnpaidFlow::class,
        Intent::PAYMENT_HISTORY => PaymentHistoryFlow::class,
        Intent::SEARCH_PATIENT => SearchPatientFlow::class,
        Intent::CREATE_PATIENT => CreatePatientFlow::class,
        Intent::VIEW_CASES => ViewCasesFlow::class,
        Intent::REPORT => ReportFlow::class,
    ];

    private const TOOL_INTENTS = [
        'create_appointment' => Intent::BOOK_APPOINTMENT,
        'reschedule_appointment' => Intent::RESCHEDULE_APPOINTMENT,
        'cancel_appointment' => Intent::CANCEL_APPOINTMENT,
        'record_payment' => Intent::RECORD_PAYMENT,
        'create_patient' => Intent::CREATE_PATIENT,
    ];

    // The user's words for a detail, and the resolved values a new value replaces
    private const RAW_SLOTS = [
        'patient_name' => ['patient_id', 'patient_label', 'patient_phone_display', 'patient_ref', 'case_id', 'reservation'],
        'patient_phone' => ['patient_id', 'patient_label', 'patient_phone_display', 'patient_ref', 'case_id', 'reservation'],
        'patient_ref' => [],
        'doctor_name' => ['doctor_id', 'doctor_label', 'doctor_none'],
        'date_text' => ['date', 'date_llm', 'reservation'],
        'time_text' => ['time', 'time_llm', 'reservation'],
        'new_date_text' => ['new_date', 'new_date_llm'],
        'new_time_text' => ['new_time', 'new_time_llm'],
        'amount_text' => ['amount', 'amount_llm', 'pay_remaining'],
        'pay_remaining' => ['amount', 'amount_text', 'amount_llm'],
        'invoice_ref' => ['case_id'],
        'period' => [],
        'notes' => [],
        'name' => [],
        'phone' => [],
        'age' => [],
    ];

    private const ORDINALS = [
        'الاول' => 1, 'الاولى' => 1, 'اول' => 1, 'الاوله' => 1, 'الثاني' => 2, 'الثانيه' => 2, 'ثاني' => 2,
        'الثالث' => 3, 'الثالثه' => 3, 'الرابع' => 4, 'الرابعه' => 4, 'الخامس' => 5, 'الخامسه' => 5,
        'السادس' => 6, 'السابع' => 7, 'الثامن' => 8,
        'first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'fifth' => 5, 'sixth' => 6, 'seventh' => 7, 'eighth' => 8,
    ];

    public function __construct(
        private IntentParser $nlu,
        private ToolExecutor $tools,
        private PendingActions $actions,
        private ConfirmationMatcher $confirmations,
        private LanguageDetector $languages,
        private DateTimeResolver $clock,
        private GeneralAnswerer $general,
        private Container $container,
    ) {
    }

    // ---- Entry points ------------------------------------------------------------

    public function message(AgentSession $s, string $text, ?string $optionId = null): AgentReply
    {
        if ($optionId === null) {
            $s->language = $this->languages->detect($text, $s->language);
        }

        if (!$this->loadUser($s)) {
            return $this->reply($s, AgentReply::ERROR, 'permissions_unavailable');
        }

        // Offered buttons belong to the previous reply only
        $offered = $s->options();
        $s->clearOffers();

        $waitingId = $s->pendingActionId();
        $pending = $this->actions->current($s);
        $expired = $waitingId && !$pending
            && $this->actions->find($waitingId, $s->userId, $s->tenantId)?->status === AiAgentAction::EXPIRED;

        if ($optionId !== null) {
            return $this->pick($s, $optionId, $offered);
        }

        // 1. A plain yes / no, decided without the LLM
        $verdict = $this->confirmations->match($text);
        if ($pending) {
            return match ($verdict) {
                ConfirmationMatcher::CONFIRM => $this->confirm($s, $pending),
                ConfirmationMatcher::DENY => $this->cancel($s, $pending),
                ConfirmationMatcher::AMBIGUOUS => $this->reshow($s, $pending, 'ambiguous_confirmation'),
                default => $this->understand($s, $text, $pending, $offered),
            };
        }

        // "نعم" to a yes/no question that offered exactly one thing (e.g. "add them
        // as a new patient?"): that thing. It only prepares a card; nothing runs.
        if ($verdict === ConfirmationMatcher::CONFIRM && ($yes = collect($offered)->firstWhere('on_yes', true))) {
            return $this->pick($s, $yes['id'], $offered);
        }

        if ($verdict === ConfirmationMatcher::CONFIRM || $verdict === ConfirmationMatcher::AMBIGUOUS) {
            // A "yes" with nothing waiting is never applied to anything
            return $expired ? $this->expiredReply($s) : $this->reply($s, AgentReply::INFO, 'no_pending');
        }
        if ($verdict === ConfirmationMatcher::DENY) {
            if ($s->draft()) {
                $s->clearDraft();

                return $this->reply($s, AgentReply::CANCELLED, 'action_cancelled');
            }

            return $this->reply($s, AgentReply::INFO, 'no_pending');
        }

        return $this->understand($s, $text, null, $offered);
    }

    /** Confirmation by button or by a plain "yes": runs the action at most once. */
    public function confirm(AgentSession $s, AiAgentAction $action, bool $retried = false): AgentReply
    {
        if ($s->permissions === null && !$this->loadUser($s)) {
            return $this->reply($s, AgentReply::ERROR, 'permissions_unavailable');
        }

        $flow = $this->flowForTool($action->tool);

        switch ($action->status) {
            case AiAgentAction::EXECUTED:
                $reply = $flow->successReply($s, $action, $action->result ?? []);
                $reply->kind = AgentReply::INFO;

                return $reply->prepend($this->t($s, 'action_already_executed'));

            case AiAgentAction::EXECUTING:
                return $this->reply($s, AgentReply::INFO, 'action_in_progress');

            case AiAgentAction::UNKNOWN:
                return $flow->recover($s, $action);

            case AiAgentAction::EXPIRED:
                return $this->expiredReply($s);

            case AiAgentAction::PENDING:
                break;

            default:
                return AgentReply::make(AgentReply::ERROR, $this->t($s, 'action_already_closed', [
                    'status' => Messages::status($action->status, $s->language),
                ]), $s->language);
        }

        if ($action->isExpired()) {
            $this->actions->expire($action);
            $s->setPendingActionId(null);

            return $this->expiredReply($s);
        }

        if (!$this->actions->claim($action)) {
            // Someone (a second click, a duplicate delivery) changed it first
            return $retried ? $this->reply($s, AgentReply::INFO, 'action_in_progress') : $this->confirm($s, $action->refresh(), true);
        }

        if ($s->pendingActionId() === $action->id) {
            $s->setPendingActionId(null);
        }
        $this->restoreDraft($s, $action);

        try {
            return $flow->execute($s, $action);
        } catch (Halt $halt) {
            if ($action->refresh()->status === AiAgentAction::EXECUTING) {
                $this->actions->finish($action, AiAgentAction::FAILED, null, null, $halt->reply->text);
            }

            return $halt->reply;
        } catch (\Throwable $e) {
            report($e);
            if ($action->refresh()->status === AiAgentAction::EXECUTING) {
                // The API may or may not have been reached: check before anything else
                $this->actions->finish($action, AiAgentAction::UNKNOWN, null, null, $e->getMessage());

                return $flow->recover($s, $action);
            }

            return $this->reply($s, AgentReply::ERROR, 'api_unavailable');
        }
    }

    public function cancel(AgentSession $s, AiAgentAction $action): AgentReply
    {
        if ($action->status === AiAgentAction::PENDING) {
            $this->actions->cancel($action, 'user');
        }

        if (in_array($action->status, [AiAgentAction::CANCELLED, AiAgentAction::EXPIRED], true)) {
            if ($s->pendingActionId() === $action->id) {
                $s->setPendingActionId(null);
            }
            $s->clearDraft();

            return $this->reply($s, AgentReply::CANCELLED, 'action_cancelled');
        }

        return AgentReply::make(AgentReply::ERROR, $this->t($s, 'action_already_closed', [
            'status' => Messages::status($action->status, $s->language),
        ]), $s->language);
    }

    /** The user's roles and permissions, from GET /auth/me as the user. */
    public function loadUser(AgentSession $s): bool
    {
        if ($s->permissions !== null) {
            return true;
        }

        $r = $this->tools->run($s, 'get_current_user');
        if (!$r->ok()) {
            return false;
        }

        $s->permissions = array_values(array_map('strval', (array) $r->get('data.permissions', [])));
        $s->roles = array_values(array_map('strval', (array) $r->get('data.roles', [])));
        $s->userName = $r->get('data.name');

        return true;
    }

    // ---- Understanding ---------------------------------------------------------------

    private function understand(AgentSession $s, string $text, ?AiAgentAction $pending, array $options): AgentReply
    {
        // A one- or two-digit reply is an option number, except where a number is the answer itself
        $choosing = in_array($s->awaiting(), ['choice', 'patient_name', 'name'], true);

        // 2. A pick from the options on screen ("2", "الثاني")
        if ($options && ($option = $this->selection($text, $options, $choosing))) {
            return $this->pick($s, $option['id'], $options);
        }

        // 3. The LLM reads the message
        $parsed = $this->nlu->parse($text, $this->context($s, $pending));

        if (!$parsed->llmAvailable) {
            return $this->withoutLlm($s, $text, $pending);
        }

        $selection = $parsed->entity('selection');
        if ($selection && $options && ($choosing || !preg_match('/\d/', $text)) && isset($options[$selection - 1])) {
            return $this->pick($s, $options[$selection - 1]['id'], $options);
        }

        // "ضيفه كمراجع جديد" right after "not found": the offered add-patient
        // option, which keeps the name and returns to the request afterwards
        if ($parsed->intent === Intent::CREATE_PATIENT && !$parsed->entity('name') && !$parsed->entity('patient_name')
            && ($add = collect($options)->firstWhere('intent', Intent::CREATE_PATIENT))) {
            return $this->pick($s, $add['id'], $options);
        }

        // A yes/no the LLM found inside a longer message is not acted on
        if (in_array($parsed->intent, [Intent::CONFIRM, Intent::DENY], true)) {
            if ($pending) {
                return $this->reshow($s, $pending, $parsed->injectionAttempt ? 'injection_refused' : 'ambiguous_confirmation');
            }

            return $this->reply($s, AgentReply::INFO, $parsed->injectionAttempt ? 'injection_refused' : 'no_pending');
        }

        $draft = $s->draft();
        $continues = $draft && (in_array($parsed->intent, [Intent::PROVIDE_INFO, Intent::UNKNOWN], true) || $parsed->intent === $draft['intent']);

        $notes = [];
        if ($pending) {
            // Anything else replaces the waiting action: a change gets a new card
            $this->actions->cancelOpen($s);
            if (!$continues) {
                $notes[] = $this->t($s, 'action_superseded');
            }
        }
        if ($parsed->injectionAttempt) {
            $notes[] = $this->t($s, 'injection_refused');
        }

        if ($continues) {
            $this->merge($s, $parsed, $text);
            $reply = $this->run($s);
        } else {
            $reply = $this->start($s, $parsed, $text);
        }

        foreach ($notes as $note) {
            $reply->note($note);
        }

        return $reply;
    }

    private function start(AgentSession $s, ParsedMessage $parsed, string $text): AgentReply
    {
        switch ($parsed->intent) {
            case Intent::GREETING:
                return $this->greeting($s);

            case Intent::PAY_NOW:
                $reply = $this->reply($s, AgentReply::INFO, 'pay_now_unsupported');
                if ($this->tools->can($s, 'record_payment')) {
                    $option = ['id' => 'record_payment', 'label' => Messages::t('confirm_payment_btn', $s->language),
                        'intent' => Intent::RECORD_PAYMENT, 'with' => array_filter([
                            'patient_name' => $parsed->entity('patient_name'),
                            'amount_text' => $parsed->entity('amount_text'),
                        ])];
                    $s->offer([$option]);
                    $reply->options([$option]);
                }

                return $reply;

            case Intent::UNSUPPORTED:
                return $this->greeting($s, $this->t($s, 'unsupported'));

            case Intent::GENERAL_QUESTION:
            case Intent::UNKNOWN:
            case Intent::PROVIDE_INFO:
                return $this->generalAnswer($s, $text);
        }

        if (!isset(self::FLOWS[$parsed->intent])) {
            return $this->reply($s, AgentReply::TEXT, 'not_understood');
        }

        $this->actions->cancelOpen($s);
        $s->startDraft($parsed->intent);
        $this->merge($s, $parsed, $text);

        return $this->run($s);
    }

    /** Merges what the user just said into the request being filled in. */
    private function merge(AgentSession $s, ParsedMessage $parsed, string $text): void
    {
        $entities = $parsed->entities;
        $awaiting = $s->awaiting();

        // Answering "when should it move to?" with "باچر الساعة 6"
        if ($awaiting === 'new_datetime') {
            $entities['new_date_text'] ??= $entities['date_text'] ?? null;
            $entities['new_time_text'] ??= $entities['time_text'] ?? null;
            unset($entities['date_text'], $entities['time_text']);
            if (empty($entities['new_date_text']) && empty($entities['new_time_text'])) {
                $entities['new_date_text'] = $entities['new_time_text'] = $this->clip($text);
            }
            $entities = array_filter($entities, fn ($v) => $v !== null);
        } elseif ($awaiting && $awaiting !== 'choice' && empty($entities[$awaiting])) {
            // The LLM may file an answer under a neighbouring key, or not at all
            $aliases = ['patient_name' => ['name', 'patient_phone'], 'name' => ['patient_name'], 'date_text' => ['new_date_text'], 'time_text' => ['new_time_text']];
            $value = null;
            foreach ($aliases[$awaiting] ?? [] as $alias) {
                $value ??= $entities[$alias] ?? null;
            }
            $entities[$awaiting] = $value ?? $this->clip($text);
        }

        foreach (self::RAW_SLOTS as $key => $replaces) {
            if (!array_key_exists($key, $entities)) {
                continue;
            }
            if ($key === 'patient_name' && $this->samePatient($s, (string) $entities[$key])) {
                continue;
            }
            $s->forget(...$replaces);
            $s->setSlots([$key => $entities[$key]]);
        }

        // The LLM's own reading, used only when the words cannot be read
        if (isset($entities['date_text'], $entities['date'])) {
            $s->setSlots(['date_llm' => $entities['date']]);
        }
        if (isset($entities['time_text'], $entities['time'])) {
            $s->setSlots(['time_llm' => $entities['time']]);
        }
        if (isset($entities['amount_text'], $entities['amount'])) {
            $s->setSlots(['amount_llm' => $entities['amount']]);
        }
        if (isset($entities['currency'])) {
            $s->setSlots(['currency_stated' => $entities['currency']]);
        }

        $s->await(null);
    }

    private function run(AgentSession $s): AgentReply
    {
        $draft = $s->draft();
        if (!$draft || !isset(self::FLOWS[$draft['intent']])) {
            $s->clearDraft();

            return $this->reply($s, AgentReply::TEXT, 'not_understood');
        }

        try {
            $reply = $this->flow($draft['intent'])->handle($s);
            if (!in_array($draft['intent'], Intent::ACTIONS, true)) {
                $s->clearDraft();
            }

            return $reply;
        } catch (Halt $halt) {
            if ($halt->reply->kind !== AgentReply::CLARIFICATION) {
                $s->clearDraft();
            }

            return $halt->reply;
        }
    }

    /** Applies an option the user picked (button or typed). */
    private function pick(AgentSession $s, string $optionId, array $options): AgentReply
    {
        $option = collect($options)->firstWhere('id', $optionId);
        if (!$option) {
            return $this->reply($s, AgentReply::CLARIFICATION, 'not_understood');
        }

        $this->actions->cancelOpen($s);

        if (!empty($option['refresh'])) {
            $s->await(null);

            return $this->run($s);
        }

        if (!empty($option['intent'])) {
            $resume = !empty($option['resume']) ? $s->draft() : null;
            $s->startDraft($option['intent'], $option['with'] ?? []);
            if ($resume) {
                $s->state['resume'] = $resume;
            }

            return $this->run($s);
        }

        $s->setSlots($option['set'] ?? []);
        $s->await(null);

        return $this->run($s);
    }

    private function selection(string $text, array $options, bool $allowDigits): ?array
    {
        $norm = TextNormalizer::normalize(trim($text));
        $norm = trim(preg_replace('/^(رقم|الخيار|خيار|option|number|no\.?)\s*/u', '', $norm) ?? $norm);

        $index = null;
        if ($allowDigits && preg_match('/^\d{1,2}$/', $norm)) {
            $index = (int) $norm;
        } elseif (isset(self::ORDINALS[$norm])) {
            $index = self::ORDINALS[$norm];
        } elseif (in_array($norm, ['الاخير', 'last'], true)) {
            $index = count($options);
        }

        if ($index !== null) {
            return $options[$index - 1] ?? null;
        }

        foreach ($options as $option) {
            if (TextNormalizer::normalize($option['label']) === $norm) {
                return $option;
            }
        }

        return null;
    }

    /** Without the LLM only a direct answer to the question asked can be used. */
    private function withoutLlm(AgentSession $s, string $text, ?AiAgentAction $pending): AgentReply
    {
        if ($pending) {
            return $this->reshow($s, $pending, 'ambiguous_confirmation');
        }

        $awaiting = $s->awaiting();
        if ($s->draft() && $awaiting && $awaiting !== 'choice') {
            $this->merge($s, new ParsedMessage(Intent::PROVIDE_INFO, [], null, false), $text);

            return $this->run($s);
        }

        return $this->reply($s, AgentReply::TEXT, 'llm_unavailable');
    }

    private function context(AgentSession $s, ?AiAgentAction $pending): array
    {
        $now = $this->clock->now();
        $draft = $s->draft();
        $known = [];
        foreach (['patient_label', 'date', 'time', 'amount', 'doctor_label', 'case_id'] as $key) {
            if ($value = $s->slot($key)) {
                $known[$key] = $value;
            }
        }

        $history = $s->conversation->messages()
            ->latest('id')
            ->limit((int) config('ai_agent.history_turns', 6))
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'text' => mb_substr($m->content, 0, 200)])
            ->values()
            ->all();

        return [
            'today' => $now->toDateString(),
            'weekday' => $now->englishDayOfWeek,
            'time' => $now->format('H:i'),
            'timezone' => $this->clock->timezone(),
            'awaiting' => $draft ? $s->awaiting() : null,
            'draft_intent' => $draft['intent'] ?? null,
            'known' => $known,
            'focus_patient' => $s->focusPatient()['name'] ?? null,
            'pending_action' => $pending ? ($pending->summary['title'] ?? $pending->tool) : null,
            'options' => array_column($s->options(), 'label'),
            'history' => $history,
        ];
    }

    // ---- Replies ---------------------------------------------------------------------

    private function reshow(AgentSession $s, AiAgentAction $pending, string $key): AgentReply
    {
        $reply = AgentReply::make(AgentReply::CONFIRMATION, $this->t($s, $key), $s->language);
        $reply->fields = $pending->summary['fields'] ?? [];

        return $reply
            ->ask($this->t($s, $pending->summary['question'] ?? 'confirm_booking_question'))
            ->withAction($pending, $pending->summary['button'] ?? 'confirm_booking_btn');
    }

    private function expiredReply(AgentSession $s): AgentReply
    {
        $reply = $this->reply($s, AgentReply::ERROR, 'action_expired');
        if ($s->draft() && in_array($s->draft()['intent'], Intent::ACTIONS, true)) {
            $option = ['id' => 'refresh', 'label' => $this->t($s, 'refresh_option'), 'refresh' => true];
            $s->await('choice', [$option]);
            $reply->options([$option]);
        }

        return $reply;
    }

    private function greeting(AgentSession $s, ?string $intro = null): AgentReply
    {
        $reply = AgentReply::make(AgentReply::TEXT, $intro ?? $this->t($s, 'greeting'), $s->language);
        $capabilities = [
            'cap.book' => $this->tools->can($s, 'create_appointment') || $this->tools->can($s, 'cancel_appointment'),
            'cap.slots' => $this->tools->can($s, 'list_appointments') || $this->tools->can($s, 'get_available_slots'),
            'cap.payment' => $this->tools->can($s, 'record_payment'),
            'cap.balance' => $this->tools->can($s, 'get_patient_balance') || $this->tools->can($s, 'list_patient_cases'),
            'cap.patients' => $this->tools->can($s, 'search_patients'),
            'cap.reports' => true,
        ];
        foreach (array_keys(array_filter($capabilities)) as $key) {
            $reply->item($this->t($s, $key));
        }

        return $reply->note($this->t($s, 'cap.footer'));
    }

    private function generalAnswer(AgentSession $s, string $text): AgentReply
    {
        $answer = $this->general->answer($s->tenantId, $text);
        if (!$answer) {
            return $this->reply($s, AgentReply::TEXT, 'not_understood');
        }

        $reply = AgentReply::make(AgentReply::TEXT, $answer['answer'], $s->language);
        $reply->sources = $answer['sources'];

        return $reply;
    }

    private function reply(AgentSession $s, string $kind, string $key, array $vars = []): AgentReply
    {
        return AgentReply::make($kind, $this->t($s, $key, $vars), $s->language);
    }

    private function t(AgentSession $s, string $key, array $vars = []): string
    {
        return Messages::t($key, $s->language, $vars);
    }

    // ---- Helpers -----------------------------------------------------------------------

    private function flow(string $intent): Flow
    {
        return $this->container->make(self::FLOWS[$intent]);
    }

    private function flowForTool(string $tool): Flow
    {
        return $this->flow(self::TOOL_INTENTS[$tool] ?? throw new \InvalidArgumentException("No flow for [{$tool}]"));
    }

    /** Puts back the request an action came from, for re-checks that need it. */
    private function restoreDraft(AgentSession $s, AiAgentAction $action): void
    {
        $intent = self::TOOL_INTENTS[$action->tool];
        if (($s->draft()['intent'] ?? null) !== $intent) {
            $s->state['draft'] = ['intent' => $intent, 'slots' => $action->entities ?? [], 'awaiting' => null, 'options' => []];
        }
    }

    private function samePatient(AgentSession $s, string $name): bool
    {
        $label = $s->slot('patient_label');
        if (!$label || !$s->slot('patient_id')) {
            return false;
        }
        $a = TextNormalizer::normalize($name);
        $b = TextNormalizer::normalize($label);

        return $a !== '' && ($a === $b || str_starts_with($b, $a . ' '));
    }

    private function clip(string $text): string
    {
        return mb_substr(trim($text), 0, 120);
    }
}
