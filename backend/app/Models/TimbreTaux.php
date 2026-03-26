<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class TimbreTaux extends Model
{
    protected $table = 'timbre_taux';

    protected $fillable = ['taux', 'plafond', 'designation', 'date_debut', 'date_fin', 'actif'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'actif' => 'boolean',
        'taux' => 'decimal:2',
        'plafond' => 'decimal:2',
    ];

    public static function enVigueurLe(Carbon|string $date): ?self
    {
        $date = is_string($date) ? Carbon::parse($date) : $date;

        return self::where('date_debut', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('date_fin')->orWhere('date_fin', '>=', $date);
            })
            ->orderByDesc('date_debut')
            ->first();
    }

    public function calculer(float $montantHt): float
    {
        $timbre = $montantHt * ($this->taux / 100);

        return min($timbre, (float) $this->plafond);
    }
}
