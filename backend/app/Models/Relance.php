<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relance extends Model
{
    protected $fillable = [
        'facture_id', 'sent_by', 'niveau', 'type',
        'email_destinataire', 'envoyee_le', 'statut', 'message',
    ];

    protected $casts = [
        'envoyee_le' => 'datetime',
    ];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
