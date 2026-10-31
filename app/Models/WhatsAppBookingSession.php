<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A customer part-way through booking with the WhatsApp bot (see WhatsAppBookingBot).
 */
class WhatsAppBookingSession extends Model
{
    public const STEP_DAY = 'day';
    public const STEP_TIME = 'time';
    public const STEP_NAME = 'name';

    protected $table = 'whatsapp_booking_sessions';

    protected $fillable = [
        'phone',
        'step',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
