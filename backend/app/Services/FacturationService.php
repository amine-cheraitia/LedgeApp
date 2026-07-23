<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\InvoicePaid;
use App\Mail\DevisMail;
use App\Mail\FactureMail;
use App\Mail\MailMessages;
use App\Models\Avoir;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Mission;
use App\Models\Paiement;
use App\Models\Prestation;
use App\Models\Setting;
use App\Models\TvaTaux;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class FacturationService
{
    public function __construct(private readonly NumerotationService $numerotation) {}

    private const DEVIS_SORT_FIELDS = ['numero', 'date_devis', 'date_validite', 'statut', 'prix_ht'];

    private const FACTURE_SORT_FIELDS = ['numero', 'date_facture', 'date_echeance', 'montant_ttc', 'statut_paiement'];

    public function listerDevis(array $filters): LengthAwarePaginator
    {
        $sortField = in_array($filters['sort_field'] ?? '', self::DEVIS_SORT_FIELDS)
            ? $filters['sort_field']
            : 'created_at';
        $sortDir = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Devis::with('entreprise', 'prestation')
            ->when($filters['exercice_id'] ?? null, fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($filters['entreprise_id'] ?? null, fn ($q, $v) => $q->where('entreprise_id', $v))
            ->when($filters['statut'] ?? null, fn ($q, $v) => $q->where('statut', $v))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q
                ->where('numero', 'like', "%{$s}%")
                ->orWhereHas('entreprise', fn ($eq) => $eq->where('raison_sociale', 'like', "%{$s}%"))
            )
            ->orderBy($sortField, $sortDir)
            ->paginate($filters['per_page'] ?? 15);
    }

    public function listerCreances(): Collection
    {
        return Facture::with(['entreprise', 'mission.prestation'])
            ->withSum('avoirs as total_avoirs', 'montant_ttc')
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->orderBy('date_echeance', 'asc')
            ->get()
            // Defense en profondeur : le statut est un champ derive qui peut etre
            // desynchronise (donnees anterieures au recalcul incluant les avoirs).
            // Une facture sans reste a payer ne doit JAMAIS apparaitre en creance
            // — on filtre sur le restant reel (TTC - paiements - avoirs).
            ->filter(fn (Facture $f) => (float) $f->montant_ttc - (float) $f->montant_paye - (float) ($f->total_avoirs ?? 0) > 0.009)
            ->values();
    }

    public function listerFactures(array $filters): LengthAwarePaginator
    {
        $sortField = in_array($filters['sort_field'] ?? '', self::FACTURE_SORT_FIELDS)
            ? $filters['sort_field']
            : 'created_at';
        $sortDir = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Facture::with('entreprise', 'mission', 'lignes')
            ->when($filters['exercice_id'] ?? null, fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($filters['entreprise_id'] ?? null, fn ($q, $v) => $q->where('entreprise_id', $v))
            ->when($filters['statut_paiement'] ?? null, fn ($q, $v) => $q->where('statut_paiement', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q
                ->where('numero', 'like', "%{$s}%")
                ->orWhereHas('entreprise', fn ($eq) => $eq->where('raison_sociale', 'like', "%{$s}%"))
            )
            ->orderBy($sortField, $sortDir)
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Resout le taux de TVA a appliquer pour un type a une date donnee.
     * - exonere : aucun taux requis (0%) ;
     * - standard : un taux actif doit exister a cette date, sinon erreur metier
     *   (evite d'appliquer silencieusement 0% sur un document soumis a la TVA).
     */
    private function resoudreTvaTaux(string $type, string $date): ?TvaTaux
    {
        $tvaTaux = TvaTaux::enVigueurLe($date, $type);

        if ($tvaTaux === null && $type !== 'exonere') {
            throw new DomainException(
                "Aucun taux TVA {$type} en vigueur au {$date}. Verifiez les taux de TVA."
            );
        }

        return $tvaTaux;
    }

    /**
     * Liste paginee des avoirs (tous exercices), avec filtres exercice + recherche.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listerAvoirs(array $filters): LengthAwarePaginator
    {
        return Avoir::with('factureOrigine.entreprise')
            ->when($filters['exercice_id'] ?? null, fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q
                ->where('numero', 'like', "%{$s}%")
                ->orWhereHas('factureOrigine.entreprise', fn ($eq) => $eq->where('raison_sociale', 'like', "%{$s}%"))
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function creerDevis(array $data, int $userId): Devis
    {
        return DB::transaction(function () use ($data, $userId) {
            $exercice = isset($data['exercice_id'])
                ? Exercice::findOrFail($data['exercice_id'])
                : Exercice::current();

            if ($exercice === null) {
                throw new DomainException('Aucun exercice ouvert : ouvrez l\'exercice de l\'année avant de créer un devis.');
            }

            $prefixe = Setting::get('devis_prefixe', 'DV');

            $entreprise = Entreprise::findOrFail($data['entreprise_id']);
            $prestation = Prestation::findOrFail($data['prestation_id']);

            // Prix HT calcule une seule fois via la grille tarifaire — immuable
            $prixHt = $prestation->calculerPrixHt($entreprise->regime_fiscal, $entreprise->categorie);

            $typeTva = $data['type_tva'] ?? 'standard';
            $tvaTaux = $this->resoudreTvaTaux($typeTva, $data['date_devis']);
            $tauxTva = $tvaTaux ? (float) $tvaTaux->taux : 0.0;

            $montantTva = round($prixHt * $tauxTva / 100, 2);
            $montantTtc = round($prixHt + $montantTva, 2);

            $devis = Devis::create([
                'entreprise_id' => $entreprise->id,
                'prestation_id' => $prestation->id,
                'exercice_id' => $exercice->id,
                'created_by' => $userId,
                'tva_taux_id' => $tvaTaux?->id,
                'numero' => $this->numerotation->genererNumero($prefixe, 'devis', $exercice),
                'date_devis' => $data['date_devis'],
                'date_validite' => $data['date_validite'],
                'prix_ht' => $prixHt,
                'taux_tva' => $tauxTva,
                'montant_tva' => $montantTva,
                'montant_ttc' => $montantTtc,
                'statut' => 'brouillon',
            ]);

            return $devis->load('prestation', 'entreprise');
        });
    }

    public function mettreAJourDevis(Devis $devis, array $data): Devis
    {
        if ($devis->statut !== 'brouillon') {
            throw new DomainException('Seuls les devis en brouillon peuvent etre modifies.');
        }

        // Recalculer le prix HT si entreprise ou prestation change, puis propager aux totaux
        // (le taux TVA fige du devis est conserve — principe d'immuabilite du taux).
        if (isset($data['entreprise_id']) || isset($data['prestation_id'])) {
            $entreprise = Entreprise::findOrFail($data['entreprise_id'] ?? $devis->entreprise_id);
            $prestation = Prestation::findOrFail($data['prestation_id'] ?? $devis->prestation_id);
            $prixHt = $prestation->calculerPrixHt($entreprise->regime_fiscal, $entreprise->categorie);

            $tauxTva = (float) $devis->taux_tva;
            $montantTva = round($prixHt * $tauxTva / 100, 2);

            $data['prix_ht'] = $prixHt;
            $data['montant_tva'] = $montantTva;
            $data['montant_ttc'] = round($prixHt + $montantTva, 2);
        }

        $devis->update($data);

        return $devis->load('prestation', 'entreprise');
    }

    /**
     * Envoie le devis au client par mail (PDF en piece jointe) puis bascule le statut en envoye.
     * Le statut ne change que si le mail a pu partir (adresse email requise).
     */
    public function envoyerDevis(Devis $devis): Devis
    {
        if ($devis->statut !== 'brouillon') {
            throw new DomainException('Seuls les devis en brouillon peuvent etre envoyes.');
        }

        $email = $devis->entreprise?->emailDestinataire();

        if (empty($email)) {
            throw new DomainException(MailMessages::PAS_D_ADRESSE);
        }

        try {
            Mail::to($email)->send(new DevisMail($devis));
        } catch (\Throwable $e) {
            report($e);
            throw new DomainException(MailMessages::ENVOI_ECHOUE);
        }

        $devis->update(['statut' => 'envoye']);

        return $devis->load('prestation', 'entreprise');
    }

    /**
     * Transmet la facture au client par mail (PDF en piece jointe). Ne modifie pas le statut.
     */
    public function transmettreFacture(Facture $facture): Facture
    {
        $email = $facture->entreprise?->emailDestinataire();

        if (empty($email)) {
            throw new DomainException(MailMessages::PAS_D_ADRESSE);
        }

        try {
            Mail::to($email)->send(new FactureMail($facture));
        } catch (\Throwable $e) {
            report($e);
            throw new DomainException(MailMessages::ENVOI_ECHOUE);
        }

        return $facture;
    }

    public function accepterDevis(Devis $devis): Devis
    {
        if ($devis->statut !== 'envoye') {
            throw new DomainException('Seuls les devis envoyes peuvent etre acceptes.');
        }

        // Regle metier : un devis n'est acceptable que dans son delai de
        // validite (jour d'echeance inclus). Passe ce delai il devient
        // 'expire' (seul producteur de ce statut) : son prix n'engage plus
        // le cabinet et la conversion en mission devient impossible.
        if ($devis->date_validite !== null && $devis->date_validite->endOfDay()->isPast()) {
            $devis->update(['statut' => 'expire']);

            throw new DomainException(
                'Ce devis a expiré le '.$devis->date_validite->format('d/m/Y').' : il ne peut plus être accepté.'
            );
        }

        $devis->update(['statut' => 'accepte']);

        return $devis->load('prestation', 'entreprise');
    }

    public function refuserDevis(Devis $devis): Devis
    {
        if ($devis->statut !== 'envoye') {
            throw new DomainException('Seuls les devis envoyes peuvent etre refuses.');
        }

        $devis->update(['statut' => 'refuse']);

        return $devis->load('prestation', 'entreprise');
    }

    public function supprimerDevis(Devis $devis): void
    {
        // Un devis rattache a une mission ou a des factures est la trace commerciale
        // d'origine : on interdit sa suppression pour ne pas rompre le lien. Un
        // eventuel trou dans la numerotation des devis est sans consequence.
        if ($devis->mission()->exists()) {
            throw new DomainException('Ce devis a servi à générer une mission : il ne peut pas être supprimé.');
        }

        if ($devis->factures()->exists()) {
            throw new DomainException('Ce devis est rattaché à des factures : il ne peut pas être supprimé.');
        }

        if ($devis->statut !== 'brouillon') {
            throw new DomainException('Seuls les devis en brouillon peuvent être supprimés.');
        }

        $devis->delete();
    }

    /**
     * Cree une facture de mission avec tranche automatique.
     * T1=30%, T2=30%, T3=40% — determinee par le nb de factures existantes sur la mission.
     * Date echeance = date_facture + 45 jours.
     * Snapshots TVA figes a la date de facturation — regle immuable.
     */
    public function creerFacture(array $data, int $userId): Facture
    {
        return DB::transaction(function () use ($data, $userId) {
            $mission = Mission::with('entreprise', 'prestation')
                ->lockForUpdate()
                ->findOrFail($data['mission_id']);

            $nbFactures = Facture::where('mission_id', $mission->id)
                ->where('type', 'FF')
                ->count();

            if ($nbFactures >= 3) {
                throw new DomainException('Les 3 tranches ont deja ete facturees pour cette mission.');
            }

            $tranches = [
                0 => ['taux' => 0.30, 'label' => 'Tranche 1 (30%)'],
                1 => ['taux' => 0.30, 'label' => 'Tranche 2 (30%)'],
                2 => ['taux' => 0.40, 'label' => 'Tranche 3 — solde (40%)'],
            ];

            $tranche = $tranches[$nbFactures];

            // La 3e tranche est le solde exact (prix_ht - T1 - T2) et non un round() independant :
            // garantit T1 + T2 + T3 == prix_ht meme lorsque le prix porte des centimes (pas de perte d'arrondi).
            $prixHt = (float) $mission->prix_ht;
            $montantT1 = round($prixHt * 0.30, 2);
            $montantT2 = round($prixHt * 0.30, 2);
            $montantsTranches = [
                0 => $montantT1,
                1 => $montantT2,
                2 => round($prixHt - $montantT1 - $montantT2, 2),
            ];
            $montantHt = $montantsTranches[$nbFactures];

            $dateFacture = $data['date_facture'];
            $dateEcheance = Carbon::parse($dateFacture)->addDays(45)->toDateString();

            $typeTva = $data['type_tva'] ?? 'standard';
            $tvaTaux = $this->resoudreTvaTaux($typeTva, $dateFacture);
            $tauxTva = $tvaTaux ? (float) $tvaTaux->taux : 0.0;
            $montantTva = round($montantHt * $tauxTva / 100, 2);
            $montantTtc = round($montantHt + $montantTva, 2);

            $exercice = isset($data['exercice_id'])
                ? Exercice::findOrFail($data['exercice_id'])
                : Exercice::current();

            if ($exercice === null) {
                throw new DomainException('Aucun exercice ouvert : ouvrez l\'exercice de l\'année avant de créer une facture.');
            }

            $prefixe = Setting::get('facture_prefixe', 'FF');
            $designation = $mission->prestation->designation.' — '.($tranche['taux'] * 100).'%';

            $facture = Facture::create([
                'entreprise_id' => $mission->entreprise_id,
                'exercice_id' => $exercice->id,
                'mission_id' => $mission->id,
                'devis_id' => $mission->devis_id ?? null,
                'created_by' => $userId,
                'tva_taux_id' => $tvaTaux?->id,
                'numero' => $this->numerotation->genererNumero($prefixe, 'factures', $exercice),
                'type' => 'FF',
                'date_facture' => $dateFacture,
                'date_echeance' => $dateEcheance,
                'montant_ht' => $montantHt,
                'taux_tva' => $tauxTva,
                'montant_tva' => $montantTva,
                'montant_ttc' => $montantTtc,
                'montant_paye' => 0,
                'statut_paiement' => 'en_attente',
                'mode_paiement' => 'non_defini',
            ]);

            FactureLigne::create([
                'facture_id' => $facture->id,
                'prestation_id' => $mission->prestation_id,
                'designation' => $designation,
                'quantite' => 1,
                'prix_unitaire_ht' => $montantHt,
                'total_ht' => $montantHt,
                'ordre' => 1,
            ]);

            return $facture->load('lignes', 'entreprise', 'mission');
        });
    }

    /**
     * Emet un avoir sur une facture existante.
     * Le taux TVA est repris depuis la facture d'origine (snapshot immuable).
     * Le montant HT saisi ne peut pas depasser le montant restant du de la facture.
     */
    public function creerAvoir(Facture $facture, array $data, int $userId): Avoir
    {
        $avoir = DB::transaction(function () use (&$facture, $data, $userId) {
            // Verrou sur la facture d'origine : le controle du restant du et l'emission
            // de l'avoir sont atomiques (evite un sur-credit concurrent, TOCTOU).
            $facture = Facture::whereKey($facture->getKey())->lockForUpdate()->first();

            $montantHt = (float) $data['montant_ht'];
            $tauxTva = (float) $facture->taux_tva;
            $montantTva = round($montantHt * $tauxTva / 100, 2);
            $montantTtc = round($montantHt + $montantTva, 2);

            if ($montantTtc > $facture->montantRestant() + 0.001) {
                throw new DomainException('Le montant de l\'avoir ne peut pas dépasser le montant restant dû ('
                    .number_format($facture->montantRestant(), 2, ',', ' ').' DA).');
            }

            $exercice = Exercice::current();

            if ($exercice === null) {
                throw new DomainException('Aucun exercice ouvert : ouvrez l\'exercice de l\'année avant d\'émettre un avoir.');
            }

            $prefixe = Setting::get('avoir_prefixe', 'FA');

            $avoir = Avoir::create([
                'facture_origine_id' => $facture->id,
                'exercice_id' => $exercice->id,
                'created_by' => $userId,
                'numero' => $this->numerotation->genererNumero($prefixe, 'avoirs', $exercice),
                'date_avoir' => $data['date_avoir'],
                'montant_ht' => $montantHt,
                'taux_tva_snapshot' => $tauxTva,
                'montant_tva' => $montantTva,
                'montant_ttc' => $montantTtc,
                'motif' => $data['motif'],
            ]);

            // L'avoir reduit le du : on reevalue le statut de paiement de la facture.
            $this->recalculerStatutPaiement($facture);

            return $avoir;
        });

        // Facture soldee par avoir : on annule les relances en cours (meme effet qu'un paiement solde).
        if ($facture->estSolde()) {
            InvoicePaid::dispatch($facture);
        }

        return $avoir;
    }

    /**
     * Supprime un avoir et reevalue le statut de paiement de la facture d'origine
     * (le du remonte : une facture soldee par cet avoir redevient impayee).
     */
    public function supprimerAvoir(Avoir $avoir): void
    {
        DB::transaction(function () use ($avoir) {
            $facture = $avoir->factureOrigine;
            $avoir->delete();

            if ($facture !== null) {
                $this->recalculerStatutPaiement($facture);
            }
        });
    }

    /**
     * Supprime un paiement et recalcule le statut de SA facture — invariant
     * metier : le statut de paiement est toujours recalcule apres mutation
     * (miroir de supprimerAvoir, suppression + recalcul indissociables).
     */
    public function supprimerPaiement(Paiement $paiement): void
    {
        DB::transaction(function () use ($paiement) {
            $facture = $paiement->facture;
            $paiement->delete();

            if ($facture !== null) {
                $this->recalculerStatutPaiement($facture);
            }
        });
    }

    public function supprimerFacture(Facture $facture): void
    {
        if ($facture->paiements()->exists()) {
            throw new DomainException('Impossible de supprimer une facture avec des paiements. Pour l\'annuler, creez un avoir (FA).');
        }

        if ($facture->avoirs()->exists()) {
            throw new DomainException('Impossible de supprimer une facture deja annulee par un avoir.');
        }

        if (! $this->estDerniereDeLaSequence($facture)) {
            throw new DomainException("La facture {$facture->numero} n'est pas la dernière de la séquence : la supprimer créerait un trou dans la numérotation. Pour l'annuler tout en restant conforme, créez un avoir (FA).");
        }

        DB::transaction(function () use ($facture) {
            $facture->lignes()->delete();
            // Hard delete : libere physiquement le numero pour qu'il soit reutilise
            // par genererNumero (MAX+1). Un soft delete laisserait la ligne en base,
            // donc un trou dans la numerotation.
            $facture->forceDelete();
        });
    }

    /**
     * Une facture est la derniere de sa sequence si aucune autre facture non
     * supprimee du meme prefixe+annee n'a un numero superieur.
     */
    private function estDerniereDeLaSequence(Facture $facture): bool
    {
        $prefixeAnnee = substr($facture->numero, 0, strrpos($facture->numero, '-') + 1);

        $max = Facture::where('exercice_id', $facture->exercice_id)
            ->where('numero', 'like', $prefixeAnnee.'%')
            ->max('numero');

        return $facture->numero === $max;
    }

    /**
     * Enregistre un paiement sur une facture (verrou pour eviter le sur-credit en
     * concurrence), met a jour le mode de paiement et recalcule le statut.
     * Leve DomainException si la facture est deja soldee (409) et ValidationException
     * si le montant depasse le restant du (422).
     *
     * @param  array<string, mixed>  $data
     */
    public function enregistrerPaiement(Facture $facture, array $data, int $userId): Paiement
    {
        return DB::transaction(function () use ($facture, $data, $userId) {
            // Controle du restant du et insertion atomiques (TOCTOU).
            $facture = Facture::whereKey($facture->getKey())->lockForUpdate()->first();

            if ($facture->estSolde()) {
                throw new DomainException('Cette facture est déjà soldée.');
            }

            $montantRestant = $facture->montantRestant();
            if ((float) $data['montant'] > $montantRestant) {
                throw ValidationException::withMessages([
                    'montant' => ["Le montant ne peut pas dépasser {$montantRestant} DA."],
                ]);
            }

            $paiement = Paiement::create([
                'facture_id' => $facture->id,
                'recorded_by' => $userId,
                'montant' => $data['montant'],
                'date_paiement' => $data['date_paiement'],
                'mode_paiement' => $data['mode_paiement'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $facture->update(['mode_paiement' => $data['mode_paiement']]);
            $this->recalculerStatutPaiement($facture);

            if ($facture->estSolde()) {
                InvoicePaid::dispatch($facture);
            }

            return $paiement;
        });
    }

    /**
     * Recalcule le statut de paiement d'une facture apres un paiement ou un avoir.
     *
     * Le statut "solde" tient compte des avoirs : une facture annulee (totalement
     * ou partiellement) par un avoir est reglee d'autant. Sans cela, une facture
     * soldee par avoir resterait "impayee" et declencherait des relances a tort.
     * Le statut "partiel" reste attache a un paiement reel (un avoir n'est pas un paiement).
     */
    public function recalculerStatutPaiement(Facture $facture): void
    {
        $totalPaye = (float) $facture->paiements()->sum('montant');
        $facture->montant_paye = $totalPaye;

        $totalRegle = $totalPaye + (float) $facture->avoirs()->sum('montant_ttc');

        if ($totalRegle >= (float) $facture->montant_ttc) {
            $facture->statut_paiement = 'solde';
        } elseif ($totalPaye > 0) {
            $facture->statut_paiement = 'partiel';
        } else {
            $facture->statut_paiement = 'en_attente';
        }

        $facture->save();
    }
}
