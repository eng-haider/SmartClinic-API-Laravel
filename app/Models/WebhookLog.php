<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'event_type',
        'payload',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function markProcessed(): void
    {
        $this->update(['status' => 'processed']);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error_message' => $error]);
    }
}
