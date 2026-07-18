<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class SettingService
{
    /**
     * Met a jour un lot de parametres de facon atomique :
     * si une ecriture echoue, aucune n'est appliquee.
     *
     * @param  array<int, array{key: string, value: ?string}>  $settings
     */
    public function mettreAJour(array $settings): void
    {
        DB::transaction(function () use ($settings): void {
            foreach ($settings as $setting) {
                Setting::set($setting['key'], $setting['value']);
            }
        });
    }
}
