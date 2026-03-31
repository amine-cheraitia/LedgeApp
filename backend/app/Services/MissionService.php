<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class MissionService
{
    public function __construct(
        private FacturationService $facturationService,
    ) {}

    /**
     * Cree une mission avec calcul automatique du prix HT.
     * Prix HT = prestation.tarif_initial × regime_fiscal.indice × categorie.indice
     */
    public function creerMission(array $data): Mission
    {
        return DB::transaction(function () use ($data) {
            $exercice = Exercice::current();
            $entreprise = Entreprise::findOrFail($data['entreprise_id']);
            $prestation = Prestation::findOrFail($data['prestation_id']);

            $prixHt = $prestation->calculerPrixHt(
                $entreprise->regime_fiscal,
                $entreprise->categorie,
            );

            $prefixe = Setting::get('mission_prefixe', 'M');
            $reference = $this->facturationService->genererNumero($prefixe, 'missions', $exercice, 'reference');

            $mission = Mission::create([
                'entreprise_id' => $entreprise->id,
                'exercice_id' => $exercice->id,
                'prestation_id' => $prestation->id,
                'devis_id' => $data['devis_id'] ?? null,
                'reference' => $reference,
                'prix_ht' => $prixHt,
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'],
                'statut' => 'en_cours',
                'notes' => $data['notes'] ?? null,
            ]);

            if (! empty($data['collaborateur_ids'])) {
                $mission->collaborateurs()->attach($data['collaborateur_ids']);
            }

            return $mission->load('entreprise', 'prestation', 'collaborateurs');
        });
    }

}

