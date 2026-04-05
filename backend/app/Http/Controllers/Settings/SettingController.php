<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\Settings\SettingResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SettingResource::collection(Setting::all());
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable', 'string'],
        ]);

        foreach ($request->settings as $setting) {
            Setting::set($setting['key'], $setting['value']);
        }

        return response()->json(['message' => 'Paramètres mis à jour.']);
    }
}
