<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facture extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'entreprise_id', 'exercice_id', 'mission_id', 'devis_id', 'created_by',
        'tva_taux_id', 'timbre_taux_id', 'numero', 'type', 'facture_origine_id',
        'date_facture', 'date_echeance', 'montant_ht', 'taux_tva', 'montant_tva',
        'montant_timbre', 'montant_ttc', 'montant_paye', 'statut_paiement',
        'pdf_path', 'notes',
    ];

    protected $casts = [
        'date_facture' => 'date',
        'date_echeance' => 'date',
        'montant_ht' => 'decimal:2',
        'taux_tva' => 'decimal:2',
        'montant_tva' => 'decimal:2',
        'montant_timbre' => 'decimal:2',
        'montant_ttc' => 'decimal:2',
        'montant_paye' => 'decimal:2',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function tvaTaux(): BelongsTo
    {
        return $this->belongsTo(TvaTaux::class);
    }

    public function timbreTaux(): BelongsTo
    {
        return $this->belongsTo(TimbreTaux::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(FactureLigne::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function relances(): HasMany
    {
        return $this->hasMany(Relance::class);
    }

    public function montantRestant(): float
    {
        return (float) $this->montant_ttc - (float) $this->montant_paye;
    }

    public function estSolde(): bool
    {
        return $this->statut_paiement === 'solde';
    }
}
