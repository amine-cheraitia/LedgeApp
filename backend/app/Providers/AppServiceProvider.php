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
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;

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

        Health::checks([
            DatabaseCheck::new(),
            CacheCheck::new(),
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90),
            DebugModeCheck::new(),
        ]);
    }
}
