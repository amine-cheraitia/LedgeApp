# Gitflow — Ledge (RNCP 39583)

## Modèle de branches

```
main          ← production stable — merge via PR uniquement, JAMAIS de push direct
develop       ← intégration continue — base de toutes les feature branches
feature/xxx   ← une branche par fonctionnalité (ex: feature/facturation)
fix/xxx       ← hotfix (depuis main, merge double main+develop)
fix/deps-xxx  ← mise à jour dépendances
```

## Branches prévues

| Branche | Module | Statut |
|---|---|---|
| `main` | Production | stable |
| `develop` | Intégration | actif |
| `feature/scaffold` | Setup N-tier + auth API + frontend | ⏳ |
| `feature/facturation` | Devis, factures, avoirs, PDF | ⏳ |
| `feature/planning` | Missions, tâches, FullCalendar | ⏳ |
| `feature/portail-client` | Portail client lecture seule | ⏳ |
| `feature/relances` | Relances auto + manuelles | ⏳ |
| `feature/kpi` | Dashboard KPI | ⏳ |
| `cicd` | GitHub Actions pipeline | ⏳ |

## Convention de commits (Conventional Commits)

Format : `type(module): description courte`

| Type | Usage |
|---|---|
| `feat` | Nouvelle fonctionnalité |
| `fix` | Correctif bug |
| `chore` | Dépendances, config, tooling |
| `test` | Tests unitaires |
| `docs` | Documentation, CHANGELOG |
| `refactor` | Refactoring sans impact fonctionnel |

Exemples :
```
feat(facturation): calcul automatique TVA + timbre fiscal
feat(portail): activation accès client depuis fiche entreprise
fix(factures): snapshot tva_taux_id manquant à la création d'avoir
chore(deps): mise à jour Laravel 12.x
test(facturation): 12 cas couverts pour le calcul HT
docs(changelog): v1.1.0 — portail client + planning
```

## Workflow — 5 Phases

### Phase 1 — Feature branch
1. `git checkout develop`
2. `git checkout -b feature/xxx`
3. Développer avec Conventional Commits
4. `cd backend && php artisan test`
5. Ouvrir une PR `feature/xxx → develop` avec le template RNCP

**Compétences :** C4.3.2 ★ (journal), C2.2.2 ★ (tests), C4.2.2 (CI/CD)

### Phase 2 — Merge dans develop
- PR review + merge
- GitHub Actions : lint → tests → build → staging deploy

**Compétences :** C2.1.2 ★ (CI), C4.2.2 (CD)

### Phase 3 — Release vers main
1. Mettre à jour `CHANGELOG.md` → section `[x.y.z]`
2. PR `develop → main` (revue finale + merge)
3. Tag Git : `git tag vx.y.z`
4. GitHub Release avec notes
5. Deploy prod auto via GitHub Actions

**Compétences :** C4.3.2 ★ (obligatoire), C4.2.2

### Phase 4 — Hotfix
1. `git checkout main && git checkout -b fix/xxx`
2. Corriger + tester
3. PR → main (merge)
4. PR → develop (merge double obligatoire)
5. Fiche anomalie + patch CHANGELOG `vx.y.z+1`

**Compétences :** C4.2.1 ★ (anomalie), C4.3.2 ★

### Phase 5 — Dépendances
1. `git checkout develop && git checkout -b fix/deps-xxx`
2. `composer update` / `npm update`
3. `php artisan test`
4. PR → develop avec `chore(deps): description`
5. Documenter dans CHANGELOG

**Compétences :** C4.1.1, C4.3.2 ★

## Règle d'or

> **JAMAIS de push direct sur main.** Tout passe par une PR.
> Le jury vérifie dans l'historique GitHub que chaque merge passe par une PR.
