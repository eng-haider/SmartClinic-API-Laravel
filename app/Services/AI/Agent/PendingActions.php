<?php

namespace App\Services\AI\Agent;

use App\Models\AiAgentAction;
use Illuminate\Support\Str;

/**
 * Actions waiting for confirmation, kept on the server. A confirmation only
 * counts for an action that belongs to the same user, clinic and
 * conversation, is still pending and has not expired. Moving it to
 * "executing" is a single conditional UPDATE, so two clicks (or a duplicated
 * delivery) can never both run it.
 */
class PendingActions
{
    public function create(AgentSession $session, string $tool, array $params, array $summary, array $snapshot = [], array $entities = []): AiAgentAction
    {
        // Only one action waits at a time in a conversation
        $this->cancelOpen($session);

        $action = AiAgentAction::create([
            'tenant_id' => $session->tenantId,
            'user_id' => $session->userId,
            'conversation_id' => $session->conversation->id,
            'tool' => $tool,
            'params' => $params,
            'entities' => $entities,
            'summary' => $summary,
            'snapshot' => $snapshot,
            'language' => $session->language,
            'idempotency_key' => (string) Str::uuid(),
            'status' => AiAgentAction::PENDING,
            'expires_at' => now()->addMinutes((int) config('ai_agent.confirmation_ttl_minutes', 10)),
        ]);

        $session->setPendingActionId($action->id);

        return $action;
    }

    /** The action, only if it belongs to this user and clinic (and conversation, when given). */
    public function find(string $id, int $userId, string $tenantId, ?string $conversationId = null): ?AiAgentAction
    {
        if (!Str::isUuid($id)) {
            return null;
        }

        return AiAgentAction::query()
            ->whereKey($id)
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->when($conversationId, fn ($q) => $q->where('conversation_id', $conversationId))
            ->first();
    }

    /** The conversation's pending action, marking it expired when its time ran out. */
    public function current(AgentSession $session): ?AiAgentAction
    {
        $id = $session->pendingActionId();
        if (!$id) {
            return null;
        }

        $action = $this->find($id, $session->userId, $session->tenantId, $session->conversation->id);
        if (!$action || $action->status !== AiAgentAction::PENDING) {
            $session->setPendingActionId(null);

            return null;
        }

        if ($action->isExpired()) {
            $this->expire($action);
            $session->setPendingActionId(null);

            return null;
        }

        return $action;
    }

    /**
     * pending -> executing, only once. False when another request got there
     * first, or the action expired or was cancelled.
     */
    public function claim(AiAgentAction $action): bool
    {
        $claimed = AiAgentAction::query()
            ->whereKey($action->id)
            ->where('status', AiAgentAction::PENDING)
            ->where('expires_at', '>', now())
            ->update([
                'status' => AiAgentAction::EXECUTING,
                'confirmed_at' => now(),
                'attempts' => $action->attempts + 1,
                'updated_at' => now(),
            ]) === 1;

        $action->refresh();

        return $claimed;
    }

    public function finish(AiAgentAction $action, string $status, ?int $httpStatus = null, ?array $result = null, ?string $error = null): void
    {
        $action->forceFill([
            'status' => $status,
            'response_status' => $httpStatus,
            'result' => $result,
            'error' => $error ? mb_substr($error, 0, 2000) : null,
            'executed_at' => $status === AiAgentAction::EXECUTED ? now() : $action->executed_at,
        ])->save();
    }

    public function cancel(AiAgentAction $action, string $reason = 'user'): bool
    {
        $cancelled = AiAgentAction::query()
            ->whereKey($action->id)
            ->where('status', AiAgentAction::PENDING)
            ->update([
                'status' => AiAgentAction::CANCELLED,
                'cancelled_at' => now(),
                'error' => $reason,
                'updated_at' => now(),
            ]) === 1;

        $action->refresh();

        return $cancelled;
    }

    public function expire(AiAgentAction $action): void
    {
        AiAgentAction::query()
            ->whereKey($action->id)
            ->where('status', AiAgentAction::PENDING)
            ->update(['status' => AiAgentAction::EXPIRED, 'updated_at' => now()]);

        $action->refresh();
    }

    /** Cancels whatever is still waiting in the conversation (a newer request replaces it). */
    public function cancelOpen(AgentSession $session, string $reason = 'superseded'): bool
    {
        $count = AiAgentAction::query()
            ->where('conversation_id', $session->conversation->id)
            ->where('user_id', $session->userId)
            ->where('tenant_id', $session->tenantId)
            ->where('status', AiAgentAction::PENDING)
            ->update([
                'status' => AiAgentAction::CANCELLED,
                'cancelled_at' => now(),
                'error' => $reason,
                'updated_at' => now(),
            ]);

        $session->setPendingActionId(null);

        return $count > 0;
    }
}
