<?php

namespace App\Http\Controllers\Prestations;

use App\Http\Controllers\Controller;
use App\Http\Resources\Prestations\PrestationResource;
use App\Models\Prestation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PrestationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PrestationResource::collection(Prestation::all());
    }

    public function show(Prestation $prestation): PrestationResource
    {
        return new PrestationResource($prestation);
    }

    public function calculerPrix(Request $request, Prestation $prestation): JsonResponse
    {
        $request->validate([
            'regime_fiscal' => ['required', 'string'],
            'categorie' => ['required', 'string'],
        ]);

        $prixHt = $prestation->calculerPrixHt(
            $request->regime_fiscal,
            $request->categorie
        );

        return response()->json([
            'prestation' => $prestation->code,
            'tarif_initial' => $prestation->tarif_initial,
            'regime_fiscal' => $request->regime_fiscal,
            'categorie' => $request->categorie,
            'prix_ht' => $prixHt,
        ]);
    }
}
