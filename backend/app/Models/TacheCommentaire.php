<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TacheCommentaire extends Model
{
    use SoftDeletes;

    protected $table = 'tache_commentaires';

    protected $fillable = ['tache_id', 'user_id', 'contenu'];

    public function tache(): BelongsTo
    {
        return $this->belongsTo(Tache::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
