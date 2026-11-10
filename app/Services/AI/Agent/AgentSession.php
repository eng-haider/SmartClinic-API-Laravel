<?php

namespace App\Services\AI\Agent;

use App\Models\AiAgentConversation;
use App\Services\AI\Agent\Api\ApiContext;
use Carbon\CarbonImmutable;

/**
 * One request's view of the conversation: who is asking, in which clinic, in
 * which language, and the conversation state (the request being filled in and
 * the patient being discussed).
 */
class AgentSession
{
    /** @var list<string>|null null until loaded from GET /auth/me */
    public ?array $permissions = null;

    /** @var list<string> */
    public array $roles = [];

    public ?string $userName = null;

    public array $state;

    /** Per-request cache of read-only API answers */
    public array $cache = [];

    public function __construct(
        public readonly int $userId,
        public readonly string $tenantId,
        public readonly AiAgentConversation $conversation,
        public ApiContext $apiContext,
        public string $language = 'ar',
    ) {
        $this->state = $conversation->state ?? [];
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($permissions === []) {
            return true;
        }

        return $this->permissions !== null && array_intersect($permissions, $this->permissions) !== [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /** A plain doctor (not the clinic's super doctor) */
    public function isPlainDoctor(): bool
    {
        return $this->hasRole('doctor') && !$this->hasRole('clinic_super_doctor') && !$this->hasRole('super_admin');
    }

    // ---- Draft: the request being filled in -------------------------------

    public function draft(): ?array
    {
        return $this->state['draft'] ?? null;
    }

    public function startDraft(string $intent, array $slots = []): void
    {
        $this->state['draft'] = ['intent' => $intent, 'slots' => $slots, 'awaiting' => null, 'options' => []];
    }

    public function clearDraft(): void
    {
        unset($this->state['draft']);
    }

    public function slots(): array
    {
        return $this->state['draft']['slots'] ?? [];
    }

    public function slot(string $key, mixed $default = null): mixed
    {
        return $this->state['draft']['slots'][$key] ?? $default;
    }

    public function setSlots(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                unset($this->state['draft']['slots'][$key]);
            } else {
                $this->state['draft']['slots'][$key] = $value;
            }
        }
    }

    public function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($this->state['draft']['slots'][$key]);
        }
    }

    public function awaiting(): ?string
    {
        return $this->state['draft']['awaiting'] ?? null;
    }

    /**
     * @param list<array{id: string, label: string, set?: array, intent?: string}> $options
     */
    public function await(?string $slot, array $options = []): void
    {
        if (!isset($this->state['draft'])) {
            return;
        }
        $this->state['draft']['awaiting'] = $slot;
        $this->state['draft']['options'] = $options;
    }

    /** Options on screen: the draft's question, or buttons offered with an answer. */
    public function options(): array
    {
        return array_merge($this->state['draft']['options'] ?? [], $this->state['offers'] ?? []);
    }

    /** Buttons that come with a plain answer (no request being filled in). */
    public function offer(array $options): void
    {
        $this->state['offers'] = $options;
    }

    public function clearOffers(): void
    {
        unset($this->state['offers']);
    }

    // ---- The patient being discussed ---------------------------------------

    public function focusPatient(): ?array
    {
        $focus = $this->state['focus_patient'] ?? null;
        if (!$focus || empty($focus['at'])) {
            return null;
        }

        $ttl = (int) config('ai_agent.context_ttl_minutes', 30);
        if (CarbonImmutable::parse($focus['at'])->addMinutes($ttl)->isPast()) {
            return null;
        }

        return $focus;
    }

    public function setFocusPatient(int $id, string $name, ?string $phone = null): void
    {
        $this->state['focus_patient'] = ['id' => $id, 'name' => $name, 'phone' => $phone, 'at' => now()->toIso8601String()];
    }

    // ---- Pending action ----------------------------------------------------

    public function pendingActionId(): ?string
    {
        return $this->state['pending_action_id'] ?? null;
    }

    public function setPendingActionId(?string $id): void
    {
        if ($id === null) {
            unset($this->state['pending_action_id']);
        } else {
            $this->state['pending_action_id'] = $id;
        }
    }
}
