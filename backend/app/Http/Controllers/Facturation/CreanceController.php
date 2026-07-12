<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Resources\Facturation\FactureResource;
use App\Services\FacturationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CreanceController extends Controller
{
    public function __construct(private readonly FacturationService $facturationService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        // Recouvrement : reserve a l'admin et a la secretaire (defense en profondeur).
        abort_unless($request->user()->hasAnyRole(['admin', 'secretaire']), 403);

        return FactureResource::collection(
            $this->facturationService->listerCreances()
        );
    }
}
