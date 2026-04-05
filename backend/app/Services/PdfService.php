<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Avoir;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;

class PdfService
{
    public function genererDevis(Devis $devis): PdfInstance
    {
        $devis->load('entreprise', 'prestation', 'exercice', 'createdBy');

        $cabinet = $this->getCabinetInfo();
        $montantEnLettres = $this->montantEnLettres((float) $devis->montant_ttc);

        return Pdf::loadView('pdf.devis', compact('devis', 'cabinet', 'montantEnLettres'))
            ->setPaper('a4', 'portrait');
    }

    public function genererFacture(Facture $facture): PdfInstance
    {
        $facture->load('entreprise', 'mission.prestation', 'lignes', 'exercice', 'createdBy');

        $cabinet = $this->getCabinetInfo();
        $montantEnLettres = $this->montantEnLettres((float) $facture->montant_ttc);

        return Pdf::loadView('pdf.facture', compact('facture', 'cabinet', 'montantEnLettres'))
            ->setPaper('a4', 'portrait');
    }

    public function genererAvoir(Avoir $avoir): PdfInstance
    {
        $avoir->load('factureOrigine.entreprise', 'factureOrigine.mission', 'exercice', 'createdBy');

        $cabinet = $this->getCabinetInfo();
        $montantEnLettres = $this->montantEnLettres((float) $avoir->montant_ttc);

        return Pdf::loadView('pdf.avoir', compact('avoir', 'cabinet', 'montantEnLettres'))
            ->setPaper('a4', 'portrait');
    }

    public function genererConvention(Mission $mission): PdfInstance
    {
        $mission->load('entreprise', 'prestation', 'exercice');

        $cabinet = $this->getCabinetInfo();
        $montantEnLettres = $this->montantEnLettres((float) $mission->prix_ht);
        $dateContrat = now()->format('d/m/Y');
        $ville = Setting::get('cabinet_ville', 'Alger');

        return Pdf::loadView('pdf.convention', compact('mission', 'cabinet', 'montantEnLettres', 'dateContrat', 'ville'))
            ->setPaper('a4', 'portrait');
    }

    public function genererMandat(Mission $mission): PdfInstance
    {
        $mission->load('entreprise', 'prestation', 'exercice');

        $cabinet = $this->getCabinetInfo();
        $dateContrat = now()->format('d/m/Y');
        $ville = Setting::get('cabinet_ville', 'Alger');

        return Pdf::loadView('pdf.mandat', compact('mission', 'cabinet', 'dateContrat', 'ville'))
            ->setPaper('a4', 'portrait');
    }

    public function montantEnLettres(float $montant): string
    {
        $entier = (int) round($montant);
        $formatter = new \NumberFormatter('fr', \NumberFormatter::SPELLOUT);
        $enLettres = $formatter->format($entier);

        // Capitalise la première lettre
        return ucfirst($enLettres).' Dinars Algériens';
    }

    /**
     * @return array<string, string|null>
     */
    /**
     * @return array<string, string|null>
     */
    /**
     * @return array<string, string|null>
     */
    private function getCabinetInfo(): array
    {
        return [
            'nom' => Setting::get('cabinet_nom', 'Cabinet'),
            'adresse' => Setting::get('cabinet_adresse'),
            'nif' => Setting::get('cabinet_nif'),
            'nis' => Setting::get('cabinet_nis'),
            'rib' => Setting::get('cabinet_rib'),
            'telephone' => Setting::get('cabinet_telephone'),
            'agrement' => Setting::get('cabinet_agrement'),
            'soustitre' => Setting::get('cabinet_soustitre'),
        ];
    }
}
