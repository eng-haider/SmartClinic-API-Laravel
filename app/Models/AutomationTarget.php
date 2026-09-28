<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationTarget extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'automation_rule_id',
        'patient_id',
        'case_id',
        'scheduled_for',
        'status',
        'message_id',
        'error_message',
        'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'automation_rule_id' => 'integer',
            'patient_id' => 'integer',
            'case_id' => 'integer',
            'message_id' => 'integer',
            'scheduled_for' => 'datetime',
            'attempt_count' => 'integer',
        ];
    }

    // ── Relations ──

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function caseModel(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    // ── Scopes ──

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDue($query)
    {
        return $query->where('scheduled_for', '<=', now());
    }

    public function scopeReady($query)
    {
        return $query->pending()->due();
    }

    // ── Helpers ──

    public function markSent(int $messageId): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'message_id' => $messageId,
            'attempt_count' => $this->attempt_count + 1,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
            'attempt_count' => $this->attempt_count + 1,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }
}
