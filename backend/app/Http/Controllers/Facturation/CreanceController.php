<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Resources\Facturation\FactureResource;
use App\Services\FacturationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CreanceController extends Controller
{
    public function __construct(private readonly FacturationService $facturationService) {}

    public function index(): AnonymousResourceCollection
    {
        return FactureResource::collection(
            $this->facturationService->listerCreances()
        );
    }
}
