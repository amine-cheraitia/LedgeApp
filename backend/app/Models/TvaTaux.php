<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class TvaTaux extends Model
{
    protected $table = 'tva_taux';

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
    public static function enVigueurLe(Carbon|string $date, string $type = 'standard'): ?self
    {
        $date = is_string($date) ? Carbon::parse($date) : $date;

        return self::where('type', $type)
            ->where('actif', true)
            ->where('date_debut', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('date_fin')->orWhere('date_fin', '>=', $date);
            })
            // Priorite au taux dont la date de debut est la plus proche (<=) de la date du document ;
            // departage stable par id (le plus recemment cree) si deux taux partagent la meme date_debut.
            ->orderByDesc('date_debut')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Ce taux est-il actif ET en vigueur a la date donnee (par defaut aujourd'hui) ?
     */
    public function estActifEnVigueur(Carbon|string|null $date = null): bool
    {
        if (! $this->actif) {
            return false;
        }

        $date = $date === null ? Carbon::now() : (is_string($date) ? Carbon::parse($date) : $date);

        return $this->date_debut <= $date
            && ($this->date_fin === null || $this->date_fin >= $date);
    }

    /**
     * Existe-t-il un AUTRE taux actif et en vigueur (a la date donnee) pour ce type ?
     * Sert a garantir qu'un type garde toujours au moins un taux utilisable.
     */
    public static function existeAutreActifEnVigueur(string $type, int $exceptId, Carbon|string|null $date = null): bool
    {
        $date = $date === null ? Carbon::now() : (is_string($date) ? Carbon::parse($date) : $date);

        return self::where('type', $type)
            ->where('id', '!=', $exceptId)
            ->where('actif', true)
            ->where('date_debut', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('date_fin')->orWhere('date_fin', '>=', $date);
            })
            ->exists();
    }
}
