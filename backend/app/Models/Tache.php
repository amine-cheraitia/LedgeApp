<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tache extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mission_id', 'assigned_to', 'titre', 'description',
        'statut', 'date_debut', 'date_echeance', 'priorite',
    ];

    protected $attributes = [
        'statut' => 'a_faire',
        'priorite' => 1,
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_echeance' => 'date',
    ];

    /**
     * Limite la requête aux tâches visibles par l'utilisateur :
     * l'admin voit tout, le collaborateur uniquement celles qui lui sont affectées.
     */
    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        return $user->hasRole('admin')
            ? $query
            : $query->where('assigned_to', $user->id);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function commentaires(): HasMany
    {
        return $this->hasMany(TacheCommentaire::class);
    }
}
