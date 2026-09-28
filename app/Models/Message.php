<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'conversation_id',
        'direction',
        'channel',
        'from_number',
        'to_number',
        'body',
        'template_key',
        'template_params',
        'external_id',
        'status',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'template_params' => 'array',
            'meta' => 'array',
        ];
    }

    // ── Relations ──

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // ── Scopes ──

    public function scopeOutbound($query)
    {
        return $query->where('direction', self::DIRECTION_OUTBOUND);
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', self::DIRECTION_INBOUND);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // ── Helpers ──

    public function isOutbound(): bool
    {
        return $this->direction === self::DIRECTION_OUTBOUND;
    }

    public function updateStatus(string $status, ?string $error = null): void
    {
        $data = ['status' => $status];
        if ($error) {
            $data['error_message'] = $error;
        }
        $this->update($data);
    }
}
