<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercice extends Model
{
    use HasFactory;

    protected $fillable = ['annee', 'date_ouverture', 'date_cloture', 'statut'];

    protected $casts = [
        'date_ouverture' => 'date',
        'date_cloture' => 'date',
    ];

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function devis(): HasMany
    {
        return $this->hasMany(Devis::class);
    }

    public function isOuvert(): bool
    {
        return $this->statut === 'ouvert';
    }

    public static function current(): ?self
    {
        return self::where('annee', now()->year)->where('statut', 'ouvert')->first();
    }
}
