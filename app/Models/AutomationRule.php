<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    use HasFactory;

    public const TRIGGER_CASE_CREATED = 'case_created';
    public const TRIGGER_CASE_COMPLETED = 'case_completed';
    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_CUSTOM_DATE = 'custom_date';
    public const TRIGGER_PERIODIC = 'periodic';

    public const TRIGGERS = [
        self::TRIGGER_CASE_CREATED,
        self::TRIGGER_CASE_COMPLETED,
        self::TRIGGER_MANUAL,
        self::TRIGGER_CUSTOM_DATE,
        self::TRIGGER_PERIODIC,
    ];

    protected $fillable = [
        'name',
        'is_active',
        'trigger_type',
        'delay_minutes',
        'delay_days',
        'exact_datetime',
        'is_periodic',
        'periodic_interval_days',
        'template_key',
        'channel',
        'conditions_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_periodic' => 'boolean',
            'delay_minutes' => 'integer',
            'delay_days' => 'integer',
            'periodic_interval_days' => 'integer',
            'exact_datetime' => 'datetime',
            'conditions_json' => 'array',
            'created_by' => 'integer',
        ];
    }

    // ── Relations ──

    public function targets(): HasMany
    {
        return $this->hasMany(AutomationTarget::class);
    }

    public function template()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_key', 'key');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTrigger($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    // ── Helpers ──

    public function getDelayInMinutes(): int
    {
        return ($this->delay_days ?? 0) * 1440 + ($this->delay_minutes ?? 0);
    }

    public function matchesConditions(array $context): bool
    {
        if (empty($this->conditions_json)) {
            return true;
        }

        foreach ($this->conditions_json as $key => $value) {
            if (!isset($context[$key]) || $context[$key] != $value) {
                return false;
            }
        }

        return true;
    }
}
