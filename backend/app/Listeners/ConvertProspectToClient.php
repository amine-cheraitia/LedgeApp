<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MissionCreated;

class ConvertProspectToClient
{
    public function handle(MissionCreated $event): void
    {
        $entreprise = $event->mission->entreprise;

        if ($entreprise->isProspect()) {
            $entreprise->update(['statut' => 'client']);
        }
    }
}
