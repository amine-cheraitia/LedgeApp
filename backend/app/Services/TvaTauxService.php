<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Facture;
use App\Models\TvaTaux;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class TvaTauxService
{
    /**
     * Cree un taux et cloture (versionne) le precedent taux ouvert du meme type.
     *
     * @param  array<string, mixed>  $data
     */
    public function creer(array $data): TvaTaux
    {
        return DB::transaction(function () use ($data) {
            $dateDebut = Carbon::parse($data['date_debut']);
            $actif = array_key_exists('actif', $data) ? (bool) $data['actif'] : true;

            // Interdit deux taux actifs du meme type qui commencent le meme jour (sinon conflit a la resolution).
            if ($actif && $this->existeTauxMemeJour($data['type'], $dateDebut)) {
                throw new DomainException($this->messageMemeJour($data['type'], $dateDebut));
            }

            // Versionnement : cloturer le precedent taux ouvert du meme type (la veille du nouveau)
            $this->cloturerPrecedent($data['type'], $dateDebut);

            return TvaTaux::create(array_merge($data, ['actif' => $actif]));
        });
    }

    /**
     * Met a jour un taux. Empeche de retirer (desactiver / changer de type) le dernier taux
     * actif en vigueur d'un type — sinon la facturation se retrouverait sans taux applicable.
     *
     * @param  array<string, mixed>  $data
     */
    public function mettreAJour(TvaTaux $taux, array $data): TvaTaux
    {
        return DB::transaction(function () use ($taux, $data) {
            $devientInactif = array_key_exists('actif', $data) && ! $data['actif'];
            $changeDeType = array_key_exists('type', $data) && $data['type'] !== $taux->type;

            if (($devientInactif || $changeDeType) && $this->estLeSeulUtilisable($taux)) {
                throw new DomainException($this->messageDernierActif($taux->type));
            }

            $type = $data['type'] ?? $taux->type;
            $dateDebut = isset($data['date_debut']) ? Carbon::parse($data['date_debut']) : $taux->date_debut;
            $resteActif = array_key_exists('actif', $data) ? (bool) $data['actif'] : (bool) $taux->actif;

            // Meme garde qu'a la creation : pas deux taux actifs du meme type le meme jour.
            if ($resteActif && $this->existeTauxMemeJour($type, $dateDebut, $taux->id)) {
                throw new DomainException($this->messageMemeJour($type, $dateDebut));
            }

            $taux->update($data);

            // L'edition re-versionne aussi : cloturer le precedent taux ouvert si la date de debut ou le type change.
            if ($resteActif && (isset($data['date_debut']) || $changeDeType)) {
                $this->cloturerPrecedent($type, $dateDebut, $taux->id);
            }

            return $taux;
        });
    }

    public function supprimer(TvaTaux $taux): void
    {
        if (Facture::where('tva_taux_id', $taux->id)->exists()) {
            throw new DomainException('Impossible de supprimer un taux de TVA utilise par des factures.');
        }

        if ($this->estLeSeulUtilisable($taux)) {
            throw new DomainException($this->messageDernierActif($taux->type));
        }

        $taux->delete();
    }

    /**
     * Ce taux est-il l'unique taux actif en vigueur (a ce jour) de son type ?
     */
    private function estLeSeulUtilisable(TvaTaux $taux): bool
    {
        return $taux->estActifEnVigueur()
            && ! TvaTaux::existeAutreActifEnVigueur($taux->type, $taux->id);
    }

    /**
     * Un autre taux ACTIF du meme type commence-t-il deja ce jour-la ?
     */
    private function existeTauxMemeJour(string $type, Carbon $dateDebut, ?int $exceptId = null): bool
    {
        return TvaTaux::where('type', $type)
            ->where('actif', true)
            ->whereDate('date_debut', $dateDebut->toDateString())
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * Cloture le precedent taux ouvert du meme type a la veille du nouveau debut (versionnement).
     */
    private function cloturerPrecedent(string $type, Carbon $dateDebut, ?int $exceptId = null): void
    {
        TvaTaux::where('type', $type)
            ->whereNull('date_fin')
            ->where('date_debut', '<', $dateDebut)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['date_fin' => $dateDebut->copy()->subDay()->toDateString()]);
    }

    private function messageMemeJour(string $type, Carbon $dateDebut): string
    {
        $libelle = $type === 'exonere' ? 'exonere' : 'standard';

        return "Un taux {$libelle} actif commence deja le {$dateDebut->format('d/m/Y')}. "
            .'Choisissez une autre date de debut ou modifiez le taux existant.';
    }

    private function messageDernierActif(string $type): string
    {
        $libelle = $type === 'exonere' ? 'exonere' : 'standard';

        return "Il doit rester au moins un taux {$libelle} actif et en vigueur.";
    }
}
