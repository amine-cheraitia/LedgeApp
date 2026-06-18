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

            // Versionnement : cloturer le precedent taux ouvert du meme type (la veille du nouveau)
            TvaTaux::where('type', $data['type'])
                ->whereNull('date_fin')
                ->where('date_debut', '<', $dateDebut)
                ->update(['date_fin' => $dateDebut->copy()->subDay()->toDateString()]);

            return TvaTaux::create(array_merge($data, [
                'actif' => array_key_exists('actif', $data) ? (bool) $data['actif'] : true,
            ]));
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
        $devientInactif = array_key_exists('actif', $data) && ! $data['actif'];
        $changeDeType = array_key_exists('type', $data) && $data['type'] !== $taux->type;

        if (($devientInactif || $changeDeType) && $this->estLeSeulUtilisable($taux)) {
            throw new DomainException($this->messageDernierActif($taux->type));
        }

        $taux->update($data);

        return $taux;
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

    private function messageDernierActif(string $type): string
    {
        $libelle = $type === 'exonere' ? 'exonere' : 'standard';

        return "Il doit rester au moins un taux {$libelle} actif et en vigueur.";
    }
}
