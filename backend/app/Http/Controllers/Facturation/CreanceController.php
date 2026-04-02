<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Resources\Facturation\FactureResource;
use App\Models\Facture;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CreanceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $creances = Facture::with(['entreprise', 'mission.prestation'])
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->orderBy('date_echeance', 'asc')
            ->get();

        return FactureResource::collection($creances);
    }
}
