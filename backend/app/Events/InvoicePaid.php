<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Facture;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public Facture $facture)
    {
    }
}
