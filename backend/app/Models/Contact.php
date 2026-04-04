<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $fillable = [
        'entreprise_id',
        'nom',
        'prenom',
        'email',
        'telephone',
        'poste',
        'est_principal',
    ];

    protected $casts = [
        'est_principal' => 'boolean',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }
}
