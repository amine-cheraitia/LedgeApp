<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Entreprise extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'raison_sociale', 'nif', 'nis', 'num_rc', 'article_imposition',
        'regime_fiscal', 'categorie', 'secteur_activite', 'adresse',
        'ville', 'wilaya', 'telephone', 'email', 'contact_principal',
        'statut', 'notes',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Adresse email a utiliser pour joindre l'entreprise : email du contact principal,
     * a defaut l'email general de l'entreprise. Source unique pour devis/factures/relances.
     */
    public function emailDestinataire(): ?string
    {
        $principal = $this->contacts()->where('est_principal', true)->value('email');

        return $principal ?: $this->email;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    public function devis(): HasMany
    {
        return $this->hasMany(Devis::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function isClient(): bool
    {
        return $this->statut === 'client';
    }

    public function isProspect(): bool
    {
        return $this->statut === 'prospect';
    }
}
