<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\MissionCreated;
use App\Models\Mission;

class MissionObserver
{
    public function created(Mission $mission): void
    {
        MissionCreated::dispatch($mission);
    }
}
