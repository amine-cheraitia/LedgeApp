<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingRequest;
use App\Http\Resources\Settings\SettingResource;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settingService) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Setting::class);

        return SettingResource::collection(Setting::all());
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        $this->settingService->mettreAJour($request->validated()['settings']);

        return response()->json(['message' => 'Paramètres mis à jour.']);
    }
}
