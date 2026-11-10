<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAgentMessage extends Model
{
    protected $fillable = ['conversation_id', 'client_message_id', 'role', 'content', 'reply'];

    protected function casts(): array
    {
        return ['reply' => 'array'];
    }
}
