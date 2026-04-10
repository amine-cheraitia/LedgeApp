# Plan — Rapport de fin de mission (PDF)

## Contexte

Génération d'un rapport PDF à la fin de chaque mission :
- Accessible aux **admin** et **secrétaire** depuis le back-office (MissionDetailPage)
- Accessible au **client** depuis le portail si son entreprise a le portail actif
- Contient les tâches de la mission avec leurs commentaires (auteur + date + contenu)
- Portail client : uniquement les commentaires avec `visible_portail = true`
- Style cohérent avec les PDF existants (convention, mandat) : DomPDF, DejaVu Sans, #1e3a5f

---

## Fichiers à modifier / créer

### Backend
| Fichier | Action |
|---|---|
| `backend/app/Services/PdfService.php` | Ajouter `genererRapportMission()` |
| `backend/resources/views/pdf/rapport-mission.blade.php` | **Nouveau** |
| `backend/app/Http/Controllers/Planning/MissionController.php` | Ajouter `rapportPdf()` |
| `backend/app/Http/Controllers/Portail/PortailMissionController.php` | Ajouter `rapportPdf()` |
| `backend/routes/api.php` | 2 nouvelles routes GET |

### Frontend
| Fichier | Action |
|---|---|
| `frontend/src/api/modules/missions.ts` | Ajouter `rapportPdfUrl()` |
| `frontend/src/pages/missions/MissionDetailPage.vue` | Bouton téléchargement (admin + secrétaire) |
| `frontend/src/pages/portail/PortailMissionsPage.vue` | Bouton téléchargement client |

---

## Détail de l'implémentation

### Étape 1 — PdfService::genererRapportMission()

```php
public function genererRapportMission(Mission $mission, bool $portailMode = false): \Barryvdh\DomPDF\PDF
{
    $mission->load([
        'entreprise',
        'prestation',
        'exercice',
        'collaborateurs',
        'taches.assignee',
        'taches.commentaires.user',
        'factures',
    ]);

    if ($portailMode) {
        $mission->taches->each(function ($tache) {
            $tache->setRelation('commentaires',
                $tache->commentaires->where('visible_portail', true)
            );
        });
    }

    $cabinet = $this->getCabinetInfo();

    return Pdf::loadView('pdf.rapport-mission', compact('mission', 'cabinet', 'portailMode'))
        ->setPaper('a4', 'portrait');
}
```

### Étape 2 — Blade rapport-mission.blade.php

Structure du document :

```
Header         : nom cabinet + "RAPPORT DE FIN DE MISSION" + référence mission
Section 1      : Informations mission
                 → Client, Prestation, Exercice
                 → Date début / date fin
                 → Prix HT (masqué si portailMode)
                 → Collaborateurs assignés
                 → Notes de la mission
Section 2      : Tâches et commentaires
                 Pour chaque tâche :
                   → Titre, statut (badge coloré), assigné à, priorité, échéance
                   → Commentaires : [Auteur] — [Date] : contenu
                   → Si aucun commentaire : "Aucun commentaire"
Section 3      : Récapitulatif facturation (masqué si portailMode)
                 → Tableau factures : référence, date, HT/TTC, statut paiement
Footer fixe    : NIF/NIS cabinet + "Page X / Y"
```

CSS : `font-family: DejaVu Sans`, couleur `#1e3a5f`, badges statut colorés.

### Étape 3 — MissionController::rapportPdf()

```php
public function rapportPdf(Mission $mission): \Symfony\Component\HttpFoundation\StreamedResponse
{
    $this->authorize('view', $mission);

    return $this->pdfService
        ->genererRapportMission($mission, false)
        ->stream('rapport-mission-' . $mission->reference . '.pdf');
}
```

### Étape 4 — PortailMissionController::rapportPdf()

```php
public function rapportPdf(Request $request, Mission $mission): \Symfony\Component\HttpFoundation\StreamedResponse
{
    abort_if($mission->entreprise_id !== $request->user()->entreprise_id, 403);

    return $this->pdfService
        ->genererRapportMission($mission, true)
        ->stream('rapport-mission-' . $mission->reference . '.pdf');
}
```

### Étape 5 — Routes (api.php)

```php
// Backoffice
Route::get('missions/{mission}/rapport/pdf', [MissionController::class, 'rapportPdf']);

// Portail
Route::get('portail/missions/{mission}/rapport/pdf', [PortailMissionController::class, 'rapportPdf']);
```

### Étape 6 — missions.ts

```ts
rapportPdfUrl(missionId: number): string {
  return `${import.meta.env.VITE_API_URL}/api/v1/missions/${missionId}/rapport/pdf`
}
```

### Étape 7 — MissionDetailPage.vue

```html
<Button
  v-if="isAdmin || isSecretaire"
  label="Rapport de mission (PDF)"
  icon="pi pi-file-pdf"
  severity="secondary"
  outlined
  aria-label="Télécharger le rapport de fin de mission en PDF"
  @click="window.open(missionsApi.rapportPdfUrl(mission.id), '_blank')"
/>
```

### Étape 8 — PortailMissionsPage.vue

```html
<Button
  label="Rapport PDF"
  icon="pi pi-file-pdf"
  size="small"
  severity="secondary"
  outlined
  :aria-label="`Télécharger le rapport de la mission ${mission.reference}`"
  @click="window.open(`${apiUrl}/api/v1/portail/missions/${mission.id}/rapport/pdf`, '_blank')"
/>
```

---

## Ordre d'exécution

1. `PdfService::genererRapportMission()`
2. `rapport-mission.blade.php`
3. `MissionController::rapportPdf()`
4. `PortailMissionController::rapportPdf()`
5. Routes `api.php`
6. `missions.ts` — URL helper
7. `MissionDetailPage.vue` — bouton back-office
8. `PortailMissionsPage.vue` — bouton portail

---

## Vérification

```bash
cd backend && php artisan test   # aucune régression
```

Tests manuels :
- Admin → `GET /api/v1/missions/1/rapport/pdf` → PDF téléchargé ✅
- Collaborateur non assigné → 403 ✅
- Client portail → `GET /api/v1/portail/missions/1/rapport/pdf` → PDF (commentaires visible_portail=true uniquement) ✅
- Client portail autre entreprise → 403 ✅
- Vérifier section facturation absente côté portail ✅
- Vérifier aria-labels présents (RGAA) ✅
