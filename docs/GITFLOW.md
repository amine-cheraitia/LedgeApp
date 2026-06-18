# Gitflow — Ledge (RNCP 39583)

## Modele de branches

```
main          <- production stable — merge via PR uniquement, JAMAIS de push direct
develop       <- integration continue — base de toutes les feature branches
feature/xxx   <- une branche par fonctionnalite (ex: feature/facturation)
fix/xxx       <- hotfix (depuis main, merge double main+develop)
fix/deps-xxx  <- mise a jour dependances
```

## Branches — Historique et planning

### Branches mergees dans develop

| Branche | Module | Statut |
|---|---|---|
| `feature/backend-setup` | Laravel 12 API + Sanctum + modeles + migrations + seeders | merge |
| `feature/auth-api` | AuthController + UserController + middlewares | merge |
| `feature/core-api` | Controllers Entreprises, Exercices, Prestations, Settings + FormRequests + Resources | merge |
| `feature/frontend-setup` | Vue 3 + PrimeVue Aura + Pinia + Router + Layouts Admin/Portail + Login | merge |
| `feature/core-pages` | Pages CRUD Users, Entreprises, Exercices, Prestations, Settings | merge |
| `fix/dark-mode-colors` | Fix variables CSS dark mode + CSRF proxy | merge |

### Branches a creer

| Branche | Module | Priorite |
|---|---|---|
| `feature/facturation` | Devis, factures, avoirs, PDF, TVA historisee | Sprint 1 |
| `feature/planning` | Missions, taches, FullCalendar, assignation collaborateurs | Sprint 2 |
| `feature/portail-client` | Portail client lecture seule | Sprint 2 |
| `feature/relances` | Relances auto (queue) + manuelles | Sprint 2 |
| `feature/kpi` | Dashboard KPI + graphiques | Sprint 2 |
| `feature/tests` | PHPUnit + tests composants Vue | Sprint 3 |
| `feature/audit-rgaa-owasp` | Audit final accessibilite + securite | Sprint 3 |
| `cicd` | GitHub Actions pipeline | Sprint 3 |

## Convention de commits (Conventional Commits)

Format : `type(module): description courte`

| Type | Usage |
|---|---|
| `feat` | Nouvelle fonctionnalite |
| `fix` | Correctif bug |
| `chore` | Dependances, config, tooling |
| `test` | Tests unitaires |
| `docs` | Documentation, CHANGELOG |
| `refactor` | Refactoring sans impact fonctionnel |

Exemples :
```
feat(facturation): calcul automatique TVA
feat(portail): activation acces client depuis fiche entreprise
fix(factures): snapshot tva_taux_id manquant a la creation d'avoir
chore(deps): mise a jour Laravel 12.x
test(facturation): 12 cas couverts pour le calcul HT
docs(changelog): v1.1.0 — portail client + planning
```

## Workflow — 5 Phases

### Phase 1 — Feature branch
1. `git checkout develop`
2. `git checkout -b feature/xxx`
3. Developper avec Conventional Commits
4. `cd backend && php artisan test`
5. Ouvrir une PR `feature/xxx -> develop` avec le template RNCP

**Competences :** C4.3.2 (journal), C2.2.2 (tests), C4.2.2 (CI/CD)

### Phase 2 — Merge dans develop
- PR review + merge
- GitHub Actions : lint -> tests -> build -> staging deploy

**Competences :** C2.1.2 (CI), C4.2.2 (CD)

### Phase 3 — Release vers main
1. Mettre a jour `CHANGELOG.md` -> section `[x.y.z]`
2. PR `develop -> main` (revue finale + merge)
3. Tag Git : `git tag vx.y.z`
4. GitHub Release avec notes
5. Deploy prod auto via GitHub Actions

**Competences :** C4.3.2 (obligatoire), C4.2.2

### Phase 4 — Hotfix
1. `git checkout main && git checkout -b fix/xxx`
2. Corriger + tester
3. PR -> main (merge)
4. PR -> develop (merge double obligatoire)
5. Fiche anomalie + patch CHANGELOG `vx.y.z+1`

**Competences :** C4.2.1 (anomalie), C4.3.2

### Phase 5 — Dependances
1. `git checkout develop && git checkout -b fix/deps-xxx`
2. `composer update` / `npm update`
3. `php artisan test`
4. PR -> develop avec `chore(deps): description`
5. Documenter dans CHANGELOG

**Competences :** C4.1.1, C4.3.2

## Regle d'or

> **JAMAIS de push direct sur main.** Tout passe par une PR.
> Le jury verifie dans l'historique GitHub que chaque merge passe par une PR.
