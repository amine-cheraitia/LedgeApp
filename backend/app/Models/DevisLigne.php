<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevisLigne extends Model
{
    protected $table = 'devis_lignes';

    protected $fillable = [
        'devis_id', 'prestation_id', 'designation',
        'quantite', 'prix_unitaire_ht', 'total_ht', 'ordre',
    ];

    protected $casts = [
        'quantite' => 'decimal:2',
        'prix_unitaire_ht' => 'decimal:2',
        'total_ht' => 'decimal:2',
    ];

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }

    public function prestation(): BelongsTo
    {
        return $this->belongsTo(Prestation::class);
    }
}
