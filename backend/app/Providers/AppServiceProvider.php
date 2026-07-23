<?php

declare(strict_types=1);

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
use Laravel\Telescope\Telescope;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Telescope : dev local uniquement. Le class_exists protege le boot en prod
        // deployee en --no-dev (le package et sa classe parente sont alors absents).
        if ($this->app->environment('local') && class_exists(Telescope::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
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
