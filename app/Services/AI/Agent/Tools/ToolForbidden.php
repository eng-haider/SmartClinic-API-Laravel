<?php

namespace App\Services\AI\Agent\Tools;

/** The signed-in user lacks every permission the tool accepts. */
class ToolForbidden extends \RuntimeException
{
    public function __construct(public readonly ToolDefinition $tool)
    {
        parent::__construct("Not allowed to use [{$tool->name}]");
    }
}
