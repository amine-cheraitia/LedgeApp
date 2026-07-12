<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Facture extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'entreprise_id', 'exercice_id', 'mission_id', 'devis_id', 'created_by',
        'tva_taux_id', 'numero', 'type', 'facture_origine_id',
        'date_facture', 'date_echeance', 'montant_ht', 'taux_tva', 'montant_tva',
        'montant_ttc', 'montant_paye', 'statut_paiement',
        'mode_paiement', 'pdf_path',
    ];

    protected $casts = [
        'date_facture' => 'date',
        'date_echeance' => 'date',
        'montant_ht' => 'decimal:2',
        'taux_tva' => 'decimal:2',
        'montant_tva' => 'decimal:2',
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

    public function avoirs(): HasMany
    {
        return $this->hasMany(Avoir::class, 'facture_origine_id');
    }

    public function montantRestant(): float
    {
        $totalAvoirs = (float) $this->avoirs()->sum('montant_ttc');

        return max(0.0, (float) $this->montant_ttc - (float) $this->montant_paye - $totalAvoirs);
    }

    public function estSolde(): bool
    {
        return $this->statut_paiement === 'solde';
    }
}
