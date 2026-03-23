<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Devis extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'entreprise_id', 'exercice_id', 'created_by', 'numero',
        'date_devis', 'date_validite', 'montant_ht', 'montant_tva',
        'montant_timbre', 'montant_ttc', 'statut', 'notes',
    ];

    protected $casts = [
        'date_devis' => 'date',
        'date_validite' => 'date',
        'montant_ht' => 'decimal:2',
        'montant_tva' => 'decimal:2',
        'montant_timbre' => 'decimal:2',
        'montant_ttc' => 'decimal:2',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(DevisLigne::class);
    }
}
