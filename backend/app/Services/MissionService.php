<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
    private const SORT_FIELDS = ['reference', 'date_debut', 'date_fin', 'statut', 'prix_ht'];

    public function listerMissions(array $filters, User $user): LengthAwarePaginator
    {
        $sortField = in_array($filters['sort_field'] ?? '', self::SORT_FIELDS)
            ? $filters['sort_field']
            : 'created_at';
        $sortDir = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Mission::with('entreprise', 'prestation', 'exercice')
            ->when(
                $user->hasRole('collaborateur'),
                fn ($q) => $q->whereHas('collaborateurs', fn ($c) => $c->where('user_id', $user->id))
            )
            ->when($filters['exercice_id'] ?? null, fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($filters['entreprise_id'] ?? null, fn ($q, $v) => $q->where('entreprise_id', $v))
            ->when($filters['statut'] ?? null, fn ($q, $v) => $q->where('statut', $v))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q
                ->where('reference', 'like', "%{$s}%")
                ->orWhereHas('entreprise', fn ($eq) => $eq->where('raison_sociale', 'like', "%{$s}%"))
            )
            ->orderBy($sortField, $sortDir)
            ->paginate($filters['per_page'] ?? 15);
    }

    public function mettreAJourMission(Mission $mission, array $data): Mission
    {
        $mission->update(collect($data)->except('collaborateur_ids')->all());

        if (array_key_exists('collaborateur_ids', $data)) {
            $mission->collaborateurs()->sync($data['collaborateur_ids'] ?? []);
        }

        return $mission->load('entreprise', 'prestation', 'collaborateurs');
    }

    /**
     * Retourne le numero de convention existant ou en genere un nouveau.
     * Le numero est genere une seule fois et stocke de facon immuable sur la mission.
     */
    public function obtenirNumeroConvention(Mission $mission): string
    {
        if ($mission->convention_numero) {
            return $mission->convention_numero;
        }

        $exercice = $mission->exercice ?? Exercice::find($mission->exercice_id);
        $prefixe = Setting::get('convention_prefixe', 'CV');
        $numero = $this->facturationService->genererNumero($prefixe, 'missions', $exercice, 'convention_numero');
        $mission->update(['convention_numero' => $numero]);

        return $numero;
    }

    /**
     * Retourne le numero de mandat existant ou en genere un nouveau.
     */
    public function obtenirNumeroMandat(Mission $mission): string
    {
        if ($mission->mandat_numero) {
            return $mission->mandat_numero;
        }

        $exercice = $mission->exercice ?? Exercice::find($mission->exercice_id);
        $prefixe = Setting::get('mandat_prefixe', 'MD');
        $numero = $this->facturationService->genererNumero($prefixe, 'missions', $exercice, 'mandat_numero');
        $mission->update(['mandat_numero' => $numero]);

        return $numero;
    }

    public function creerMission(array $data): Mission
    {
        return DB::transaction(function () use ($data) {
            $exercice = isset($data['exercice_id'])
                ? Exercice::findOrFail($data['exercice_id'])
                : Exercice::current();
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
