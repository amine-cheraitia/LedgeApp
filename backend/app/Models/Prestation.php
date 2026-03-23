<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prestation extends Model
{
    protected $fillable = ['code', 'designation', 'tarif_initial', 'duree_mois', 'description', 'actif'];

    protected $casts = [
        'actif' => 'boolean',
        'tarif_initial' => 'decimal:2',
    ];

    /**
     * Calcule le prix HT selon la grille tarifaire Ledge.
     * Prix HT = tarif_initial × indice_regime × indice_categorie
     */
    public function calculerPrixHt(string $regime, string $categorie): float
    {
        $indiceRegime = RegimeFiscal::where('code', $regime)->value('indice') ?? 1.0;
        $indiceCategorie = CategorieEntreprise::where('code', $categorie)->value('indice') ?? 1.0;

        return (float) $this->tarif_initial * (float) $indiceRegime * (float) $indiceCategorie;
    }
}
