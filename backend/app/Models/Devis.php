<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Devis extends Model
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
        'entreprise_id', 'prestation_id', 'exercice_id', 'created_by', 'numero',
        'date_devis', 'date_validite', 'prix_ht', 'montant_ht', 'taux_tva',
        'tva_taux_id', 'montant_tva', 'montant_ttc', 'statut',
    ];

    protected $casts = [
        'date_devis' => 'date',
        'date_validite' => 'date',
        'prix_ht' => 'decimal:2',
        'montant_ht' => 'decimal:2',
        'taux_tva' => 'decimal:2',
        'montant_tva' => 'decimal:2',
        'montant_ttc' => 'decimal:2',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function prestation(): BelongsTo
    {
        return $this->belongsTo(Prestation::class);
    }

    public function tvaTaux(): BelongsTo
    {
        return $this->belongsTo(TvaTaux::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function mission(): HasOne
    {
        return $this->hasOne(Mission::class);
    }
}
