<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Avoir;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class AuditService
{
    /**
     * Modeles auditables exposes au filtre (label court => classe Eloquent).
     *
     * @var array<string, class-string>
     */
    public const SUJETS = [
        'Facture' => Facture::class,
        'Avoir' => Avoir::class,
        'Paiement' => Paiement::class,
        'Devis' => Devis::class,
        'Entreprise' => Entreprise::class,
        'User' => User::class,
        'Setting' => Setting::class,
    ];

    /**
     * Liste paginee du journal d'audit, filtrable.
     *
     * @param  array<string, mixed>  $filters
     */
    public function lister(array $filters): LengthAwarePaginator
    {
        return Activity::query()
            ->with('causer')
            ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($filters['causer_id'] ?? null, fn ($q, $id) => $q->where('causer_id', $id))
            ->when(
                isset($filters['subject_type']) ? (self::SUJETS[$filters['subject_type']] ?? null) : null,
                fn ($q, $class) => $q->where('subject_type', $class)
            )
            ->when($filters['date_debut'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_fin'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(min((int) ($filters['per_page'] ?? 15), 100));
    }
}
