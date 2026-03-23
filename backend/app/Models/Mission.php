<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'entreprise_id', 'exercice_id', 'prestation_id', 'reference',
        'prix_ht', 'date_debut', 'date_fin', 'statut', 'notes',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'prix_ht' => 'decimal:2',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function prestation(): BelongsTo
    {
        return $this->belongsTo(Prestation::class);
    }

    public function collaborateurs(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mission_user')
            ->withPivot('role_mission')
            ->withTimestamps();
    }

    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
