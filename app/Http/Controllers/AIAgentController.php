<?php

namespace App\Http\Controllers;

use App\Models\AiAgentAction;
use App\Models\AiAgentConversation;
use App\Models\AiAgentMessage;
use App\Services\AI\Agent\AgentOrchestrator;
use App\Services\AI\Agent\AgentReply;
use App\Services\AI\Agent\AgentSchema;
use App\Services\AI\Agent\AgentSession;
use App\Services\AI\Agent\Api\ApiContext;
use App\Services\AI\Agent\CurrentTenant;
use App\Services\AI\Agent\GeneralAnswerer;
use App\Services\AI\Agent\Language\LanguageDetector;
use App\Services\AI\Agent\Language\Messages;
use App\Services\AI\Agent\PendingActions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The clinic chatbot. One chatbot, one place: it answers questions and, after
 * the user confirms, performs actions (bookings, payments...) through this API.
 *
 * POST ai/chat                              a message (or an option the user picked)
 * POST ai/chat/actions/{id}/confirm         run a prepared action (idempotent)
 * POST ai/chat/actions/{id}/cancel          drop a prepared action
 * GET  ai/chat/actions/{id}                 an action's status (after a timeout)
 * GET  ai/chat/conversations/{id}           the conversation, to restore the chat
 *
 * Older clients keep working: they send {question} and read data.answer (the
 * full reply as text, with the "reply yes / no" hint), and without a
 * conversation_id they continue the user's recent conversation, so a typed
 * "نعم" confirms what they were just shown. answer_only=true is a one-shot
 * question with no conversation and no actions (the patient analysis drawer).
 *
 * Conversations and actions belong to the signed-in user in the current
 * clinic; anything else is reported as not found.
 */
class AIAgentController extends Controller
{
    public function __construct(
        private AgentOrchestrator $agent,
        private PendingActions $actions,
        private CurrentTenant $tenant,
        private GeneralAnswerer $general,
        private AgentSchema $schema,
    ) {
    }

    public function message(Request $request): JsonResponse
    {
        $input = $request->validate([
            'message' => 'required_without_all:question,option_id|nullable|string|max:1000',
            'question' => 'nullable|string|max:1000', // older clients
            'option_id' => 'nullable|string|max:64',
            'conversation_id' => 'nullable|uuid',
            'new_conversation' => 'nullable|boolean',
            'client_message_id' => 'nullable|string|max:100',
            'answer_only' => 'nullable|boolean',
        ]);

        $tenantId = $this->tenant->id();
        if (!$tenantId) {
            return $this->noTenant();
        }
        $userId = (int) Auth::id();
        $text = trim((string) ($input['message'] ?? $input['question'] ?? ''));

        if (!empty($input['answer_only'])) {
            return $this->answerOnly($tenantId, $text);
        }

        // A clinic the migration missed (or left half-done) sets its chatbot
        // tables up now. If even that fails, say so plainly: never hand an
        // action request to the question-only answerer, which can't do it.
        if (!$this->schema->prepare()) {
            report(new \RuntimeException("AI chatbot tables missing for tenant [{$tenantId}] and could not be created"));

            return $this->notReadyReply($text);
        }

        $conversation = $this->conversationFor($input, $userId, $tenantId);
        if (!$conversation) {
            return $this->notFound();
        }

        return $this->locked($conversation, function () use ($request, $input, $text, $conversation, $tenantId, $userId) {
            $clientId = $input['client_message_id'] ?? null;

            // The same message delivered twice gets the first answer back
            if ($clientId && ($earlier = $this->earlierDelivery($conversation, $clientId))) {
                return $earlier;
            }

            try {
                $userMessage = AiAgentMessage::create([
                    'conversation_id' => $conversation->id,
                    'client_message_id' => $clientId,
                    'role' => 'user',
                    'content' => $text !== '' ? $text : '[option ' . $input['option_id'] . ']',
                ]);
            } catch (UniqueConstraintViolationException) {
                return $this->earlierDelivery($conversation, $clientId) ?? $this->stillProcessing();
            }

            $session = $this->session($request, $conversation, $tenantId, $userId);
            $reply = $this->agent->message($session, $text, $input['option_id'] ?? null);

            return $this->respond($session, $reply, $userMessage, null, $text);
        });
    }

    /**
     * The conversation a message belongs to: the one named; otherwise (an older
     * client that sends no id) the user's conversation from the last minutes;
     * otherwise, or when new_conversation is set, a new one.
     */
    private function conversationFor(array $input, int $userId, string $tenantId): ?AiAgentConversation
    {
        if (!empty($input['conversation_id'])) {
            return $this->ownedConversation($input['conversation_id'], $userId, $tenantId);
        }

        if (empty($input['new_conversation'])) {
            $recent = AiAgentConversation::query()
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('last_activity_at', '>=', now()->subMinutes((int) config('ai_agent.context_ttl_minutes', 30)))
                ->latest('last_activity_at')
                ->first();
            if ($recent) {
                return $recent;
            }
        }

        return AiAgentConversation::create(['tenant_id' => $tenantId, 'user_id' => $userId, 'state' => [], 'language' => 'ar']);
    }

    /** One question, one answer: no conversation, no actions (as /ai/chat always was). */
    private function answerOnly(string $tenantId, string $question): JsonResponse
    {
        $result = $this->general->answer($tenantId, $question);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process your question',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Question answered successfully',
            'data' => [
                'question' => $question,
                'answer' => $result['answer'],
                'sources' => $result['sources'],
                'answered_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    public function confirm(Request $request, string $action): JsonResponse
    {
        return $this->onAction($request, $action, fn (AgentSession $s, AiAgentAction $a) => $this->agent->confirm($s, $a), 'confirm');
    }

    public function cancel(Request $request, string $action): JsonResponse
    {
        return $this->onAction($request, $action, function (AgentSession $s, AiAgentAction $a) {
            $this->agent->loadUser($s);

            return $this->agent->cancel($s, $a);
        }, 'cancel');
    }

    public function showAction(string $action): JsonResponse
    {
        $tenantId = $this->tenant->id();
        if (!$tenantId) {
            return $this->noTenant();
        }
        if (!$this->schema->prepare()) {
            return $this->notReady();
        }

        $found = $this->actions->find($action, (int) Auth::id(), $tenantId);
        if (!$found) {
            return $this->notFound();
        }

        return response()->json(['success' => true, 'data' => $this->actionData($found)]);
    }

    public function conversation(string $conversation): JsonResponse
    {
        $tenantId = $this->tenant->id();
        if (!$tenantId) {
            return $this->noTenant();
        }
        if (!$this->schema->prepare()) {
            return $this->notReady();
        }

        $found = $this->ownedConversation($conversation, (int) Auth::id(), $tenantId);
        if (!$found) {
            return $this->notFound();
        }

        $messages = $found->messages()->latest('id')->limit(50)->get()->reverse()->values()
            ->map(fn (AiAgentMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->role === 'user' ? $m->content : null,
                'reply' => $m->role === 'assistant' ? $m->reply : null,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => [
            'conversation_id' => $found->id,
            'language' => $found->language,
            'messages' => $messages,
        ]]);
    }

    // -------------------------------------------------------------------------

    private function onAction(Request $request, string $actionId, callable $handle, string $label): JsonResponse
    {
        $request->validate(['conversation_id' => 'nullable|uuid']);

        $tenantId = $this->tenant->id();
        if (!$tenantId) {
            return $this->noTenant();
        }
        if (!$this->schema->prepare()) {
            return $this->notReady();
        }
        $userId = (int) Auth::id();

        $action = $this->actions->find($actionId, $userId, $tenantId, $request->input('conversation_id'));
        $conversation = $action ? $this->ownedConversation($action->conversation_id, $userId, $tenantId) : null;
        if (!$action || !$conversation) {
            return $this->notFound();
        }

        return $this->locked($conversation, function () use ($request, $action, $conversation, $tenantId, $userId, $handle, $label) {
            $session = $this->session($request, $conversation, $tenantId, $userId);
            $session->language = $action->language ?: $session->language;

            $userMessage = AiAgentMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => "[{$label} action {$action->id}]",
            ]);

            return $this->respond($session, $handle($session, $action->refresh()), $userMessage, $action->refresh());
        });
    }

    private function session(Request $request, AiAgentConversation $conversation, string $tenantId, int $userId): AgentSession
    {
        return new AgentSession(
            $userId,
            $tenantId,
            $conversation,
            ApiContext::fromRequest($request, $tenantId, $conversation->language ?: 'ar'),
            $conversation->language ?: 'ar',
        );
    }

    private function respond(AgentSession $session, AgentReply $reply, AiAgentMessage $userMessage, ?AiAgentAction $action = null, ?string $question = null): JsonResponse
    {
        $session->conversation->forceFill([
            'state' => $session->state,
            'language' => $session->language,
            'last_activity_at' => now(),
        ])->save();

        $payload = $reply->toArray();
        $userMessage->forceFill(['reply' => $payload])->save();
        $assistant = AiAgentMessage::create([
            'conversation_id' => $session->conversation->id,
            'role' => 'assistant',
            'content' => mb_substr($reply->text, 0, 5000),
            'reply' => $payload,
        ]);

        return response()->json(['success' => true, 'data' => array_filter([
            'conversation_id' => $session->conversation->id,
            'message_id' => $assistant->id,
            'duplicate' => false,
            'reply' => $payload,
            'action' => $action ? $this->actionData($action->refresh()) : null,
        ] + $this->legacyFields($payload, $question), fn ($v) => $v !== null)]);
    }

    /** What clients written for the question-only chatbot read. */
    private function legacyFields(array $payload, ?string $question): array
    {
        return [
            'question' => $question,
            'answer' => $payload['plain_text'] ?? $payload['text'] ?? '',
            'sources' => $payload['sources'] ?? [],
            'answered_at' => now()->toDateTimeString(),
        ];
    }

    private function earlierDelivery(AiAgentConversation $conversation, string $clientId): ?JsonResponse
    {
        $earlier = AiAgentMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('client_message_id', $clientId)
            ->first();

        if (!$earlier) {
            return null;
        }
        if (!$earlier->reply) {
            return $this->stillProcessing();
        }

        return response()->json(['success' => true, 'data' => [
            'conversation_id' => $conversation->id,
            'duplicate' => true,
            'reply' => $earlier->reply,
        ] + $this->legacyFields($earlier->reply, $earlier->content)]);
    }

    /**
     * One turn at a time per conversation, claimed with a conditional UPDATE on
     * the conversation row. (Not Cache::lock: the tenancy cache bootstrapper
     * tags every cache call, which the database store cannot do.) The action
     * claim itself is separately atomic.
     */
    private function locked(AiAgentConversation $conversation, callable $callback): JsonResponse
    {
        $deadline = microtime(true) + 20;

        while (!$this->claimTurn($conversation)) {
            if (microtime(true) >= $deadline) {
                return $this->stillProcessing();
            }
            usleep(200_000);
        }

        try {
            return $callback();
        } finally {
            AiAgentConversation::query()->whereKey($conversation->id)->update(['locked_until' => null]);
        }
    }

    private function claimTurn(AiAgentConversation $conversation): bool
    {
        return AiAgentConversation::query()
            ->whereKey($conversation->id)
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<', now()))
            ->update(['locked_until' => now()->addSeconds(90)]) === 1;
    }

    private function ownedConversation(string $id, int $userId, string $tenantId): ?AiAgentConversation
    {
        return AiAgentConversation::query()
            ->whereKey($id)
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    private function actionData(AiAgentAction $action): array
    {
        return [
            'id' => $action->id,
            'tool' => $action->tool,
            'status' => $action->status,
            'summary' => $action->summary,
            'expires_at' => $action->expires_at?->toIso8601String(),
            'executed_at' => $action->executed_at?->toIso8601String(),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Not found', 'message_ar' => 'غير موجود'], 404);
    }

    private function noTenant(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Clinic context not found.', 'message_ar' => 'لم يتم تحديد العيادة.'], 400);
    }

    /** A normal chat reply (so every chat UI shows it) saying the chatbot can't act yet. */
    private function notReadyReply(string $text): JsonResponse
    {
        $language = (new LanguageDetector())->detect($text);
        $reply = AgentReply::make(AgentReply::ERROR, Messages::t('not_ready', $language), $language)->toArray();

        return response()->json(['success' => true, 'data' => [
            'reply' => $reply,
            'question' => $text,
            'answer' => $reply['plain_text'],
            'sources' => [],
            'answered_at' => now()->toDateTimeString(),
        ]]);
    }

    private function notReady(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'The chatbot is not set up for this clinic yet. Please contact support.',
            'message_ar' => 'المساعد الذكي غير مهيأ لهذه العيادة بعد. يرجى التواصل مع الدعم الفني.',
        ], 503);
    }

    private function stillProcessing(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'This message is still being processed.',
            'message_ar' => 'الرسالة قيد المعالجة.',
        ], 409);
    }
}
