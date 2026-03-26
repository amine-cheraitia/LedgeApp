<?php

namespace App\Providers;

use App\Events\InvoicePaid;
use App\Events\MissionCreated;
use App\Listeners\CancelRelancesOnPayment;
use App\Listeners\ConvertProspectToClient;
use App\Models\Mission;
use App\Observers\MissionObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Mission::observe(MissionObserver::class);

        Event::listen(MissionCreated::class, ConvertProspectToClient::class);
        Event::listen(InvoicePaid::class, CancelRelancesOnPayment::class);
    }
}
