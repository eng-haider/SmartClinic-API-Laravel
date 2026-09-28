<?php

namespace App\Events;

use App\Models\CaseModel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CaseModel $case,
    ) {}
}
