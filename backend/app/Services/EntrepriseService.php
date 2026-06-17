<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entreprise;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntrepriseService
{
    public function lister(array $filters): LengthAwarePaginator
    {
        return Entreprise::query()
            ->when($filters['search'] ?? null, function ($q, $s) {
                $q->where(function ($inner) use ($s) {
                    $inner->where('raison_sociale', 'like', "%{$s}%")
                        ->orWhere('nif', 'like', "%{$s}%")
                        ->orWhere('nis', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('telephone', 'like', "%{$s}%")
                        ->orWhere('ville', 'like', "%{$s}%");
                });
            })
            ->when($filters['statut'] ?? null, fn ($q, $s) => $q->where('statut', $s))
            ->when($filters['wilaya'] ?? null, fn ($q, $w) => $q->where('wilaya', $w))
            ->with('users')
            ->withCount('missions', 'factures')
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function wilayas(): Collection
    {
        return Entreprise::query()
            ->whereNotNull('wilaya')
            ->distinct()
            ->orderBy('wilaya')
            ->pluck('wilaya');
    }

    public function creer(array $data): Entreprise
    {
        return Entreprise::create($data);
    }

    public function exportCsv(array $filters): StreamedResponse
    {
        $query = Entreprise::query()
            ->when($filters['search'] ?? null, function ($q, $s) {
                $q->where(function ($inner) use ($s) {
                    $inner->where('raison_sociale', 'like', "%{$s}%")
                        ->orWhere('nif', 'like', "%{$s}%")
                        ->orWhere('nis', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('telephone', 'like', "%{$s}%")
                        ->orWhere('ville', 'like', "%{$s}%");
                });
            })
            ->when($filters['statut'] ?? null, fn ($q, $s) => $q->where('statut', $s))
            ->when($filters['wilaya'] ?? null, fn ($q, $w) => $q->where('wilaya', $w))
            ->orderBy('raison_sociale');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="entreprises.csv"',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Raison sociale', 'NIF', 'NIS', 'Statut', 'Regime fiscal', 'Categorie', 'Wilaya', 'Ville', 'Email', 'Telephone'], ';');

            $query->chunk(500, function ($entreprises) use ($handle) {
                foreach ($entreprises as $e) {
                    fputcsv($handle, [
                        $e->raison_sociale,
                        $e->nif ?? '',
                        $e->nis ?? '',
                        $e->statut,
                        $e->regime_fiscal,
                        $e->categorie,
                        $e->wilaya ?? '',
                        $e->ville ?? '',
                        $e->email ?? '',
                        $e->telephone ?? '',
                    ], ';');
                }
            });

            fclose($handle);
        }, 'entreprises.csv', $headers);
    }
}
