<?php

namespace App\Models;

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
        'statut', 'date_echeance', 'priorite',
    ];

    protected $casts = [
        'date_echeance' => 'date',
    ];

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
