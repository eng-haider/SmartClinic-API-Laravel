<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An operation the agent prepared and the user must confirm before it runs.
 *
 * Status flow: pending -> executing -> executed | failed | unknown,
 * or pending -> cancelled | expired.
 */
class AiAgentAction extends Model
{
    use HasUuids;

    public const PENDING = 'pending';
    public const EXECUTING = 'executing';
    public const EXECUTED = 'executed';
    public const FAILED = 'failed';
    public const UNKNOWN = 'unknown';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    protected $fillable = [
        'tenant_id', 'user_id', 'conversation_id', 'tool', 'params', 'entities', 'summary',
        'snapshot', 'language', 'idempotency_key', 'status', 'attempts', 'response_status',
        'result', 'error', 'expires_at', 'confirmed_at', 'executed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'params' => 'array',
            'entities' => 'array',
            'summary' => 'array',
            'snapshot' => 'array',
            'result' => 'array',
            'attempts' => 'integer',
            'response_status' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'executed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->status === self::PENDING && $this->expires_at->isPast();
    }
}
