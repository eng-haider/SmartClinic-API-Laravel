<?php

namespace App\Services\AI\Agent\Flows;

use App\Services\AI\Agent\AgentReply;

/** Stops a flow early with a reply (a question, a refusal, an error). */
class Halt extends \RuntimeException
{
    public function __construct(public readonly AgentReply $reply)
    {
        parent::__construct($reply->text);
    }
}
