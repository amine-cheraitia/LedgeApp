<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingRequest;
use App\Http\Resources\Settings\SettingResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SettingResource::collection(Setting::all());
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        Gate::authorize('update', Setting::class);

        foreach ($request->settings as $setting) {
            Setting::set($setting['key'], $setting['value']);
        }

        return response()->json(['message' => 'Paramètres mis à jour.']);
    }
}
