<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A chatbot agent conversation. Holds the agent's own state (the request being
 * filled in, the patient being discussed); never clinic business data.
 */
class AiAgentConversation extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'language', 'state', 'last_activity_at'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'state' => 'array',
            'last_activity_at' => 'datetime',
        ];
    }

    public function messages()
    {
        return $this->hasMany(AiAgentMessage::class, 'conversation_id');
    }
}
