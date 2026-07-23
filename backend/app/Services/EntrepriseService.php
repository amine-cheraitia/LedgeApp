<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entreprise;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntrepriseService
{
    public function supprimer(Entreprise $entreprise): void
    {
        if ($entreprise->missions()->exists() || $entreprise->devis()->exists()) {
            throw new DomainException('Impossible de supprimer cette entreprise : des missions ou devis y sont associes.');
        }

        $entreprise->delete();
    }

    /**
     * Compteurs globaux affiches en tete de la liste (independants des filtres).
     *
     * @return array{total:int, clients:int, prospects:int}
     */
    public function compteursStatuts(): array
    {
        $parStatut = Entreprise::query()
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return [
            'total' => (int) $parStatut->sum(),
            'clients' => (int) ($parStatut['client'] ?? 0),
            'prospects' => (int) ($parStatut['prospect'] ?? 0),
        ];
    }

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

        $callback = function () use ($query) {
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
        };

        // StreamedResponse construit directement : `streamDownload()` regenere le
        // Content-Disposition depuis le nom de fichier (sans guillemets). Ici on
        // maitrise l'en-tete -> nom de fichier entre guillemets (RFC 6266).
        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="entreprises.csv"',
        ]);
    }
}
