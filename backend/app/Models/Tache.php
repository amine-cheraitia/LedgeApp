<?php

declare(strict_types=1);

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

    /**
     * Limite la requête aux tâches qui chevauchent la période [debut, echeance].
     * date_debut et date_echeance sont nullables : on prend la borne effective via COALESCE.
     * Chevauchement = debut_effectif <= echeance_periode ET fin_effective >= debut_periode.
     * COALESCE est standard (compatible MySQL + SQLite) ; les bornes passent en bindings.
     */
    public function scopeChevauche(Builder $query, ?string $debut, ?string $echeance): Builder
    {
        $from = $debut ?? $echeance;
        $to = $echeance ?? $debut;

        return $query
            ->where(fn ($q) => $q->whereNotNull('date_debut')->orWhereNotNull('date_echeance'))
            ->whereRaw('COALESCE(date_debut, date_echeance) <= ?', [$to])
            ->whereRaw('COALESCE(date_echeance, date_debut) >= ?', [$from]);
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
