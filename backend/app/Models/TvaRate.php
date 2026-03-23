<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TvaRate extends Model
{
    protected $table = 'tva_rates';

    protected $fillable = ['taux', 'designation', 'date_debut', 'date_fin', 'type', 'actif'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'actif' => 'boolean',
        'taux' => 'decimal:2',
    ];

    /**
     * Retourne le taux TVA en vigueur à une date donnée.
     * Règle critique : toujours retrouver le bon taux historique.
     */
    public static function enVigueurLe(\Carbon\Carbon|string $date, string $type = 'standard'): ?self
    {
        $date = is_string($date) ? \Carbon\Carbon::parse($date) : $date;

        return self::where('type', $type)
            ->where('date_debut', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('date_fin')->orWhere('date_fin', '>=', $date);
            })
            ->orderByDesc('date_debut')
            ->first();
    }
}
