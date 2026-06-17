<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Avoir extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'facture_origine_id',
        'exercice_id',
        'created_by',
        'numero',
        'date_avoir',
        'montant_ht',
        'taux_tva_snapshot',
        'montant_tva',
        'montant_ttc',
        'motif',
    ];

    protected $casts = [
        'date_avoir' => 'date',
        'montant_ht' => 'decimal:2',
        'taux_tva_snapshot' => 'decimal:2',
        'montant_tva' => 'decimal:2',
        'montant_ttc' => 'decimal:2',
    ];

    public function factureOrigine(): BelongsTo
    {
        return $this->belongsTo(Facture::class, 'facture_origine_id');
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
