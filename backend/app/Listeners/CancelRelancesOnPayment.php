<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InvoicePaid;

class CancelRelancesOnPayment
{
    public function handle(InvoicePaid $event): void
    {
        $event->facture->relances()
            ->where('statut', 'en_attente')
            ->update(['statut' => 'annulee']);
    }
}
