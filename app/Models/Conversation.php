<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'channel',
        'phone_number',
        'status',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    // ── Relations ──

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // ── Scopes ──

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    // ── Helpers ──

    public static function findOrCreateForPatient(Patient $patient, string $channel = 'whatsapp'): self
    {
        return self::firstOrCreate(
            ['patient_id' => $patient->id, 'channel' => $channel],
            ['phone_number' => $patient->phone, 'status' => 'open']
        );
    }
}
