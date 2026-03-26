<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Mission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MissionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Mission $mission) {}
}
