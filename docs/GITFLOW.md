# Gitflow — Ledge (RNCP 39583)

## Modele de branches

```
main          <- production stable — merge via PR uniquement, JAMAIS de push direct
develop       <- integration continue — base de toutes les feature branches
feature/xxx   <- une branche par fonctionnalite (ex: feature/facturation)
fix/xxx       <- hotfix (depuis main, merge double main+develop)
fix/deps-xxx  <- mise a jour dependances
```

## Branches — Organisation

**Une branche = une fonctionnalite du backlog** : chaque US est developpee sur
sa branche `feature/{slug}` creee depuis `develop`, puis fusionnee par PR
(template RNCP, revue + CI verte). L'historique complet des merges est
consultable dans les Pull Requests GitHub ; le detail fonctionnel de chaque
livraison est trace dans [`CHANGELOG.md`](../CHANGELOG.md).

### Bogues traces par issue GitHub

Chaque anomalie qualifiee suit le processus decrit dans
[PLAN-CORRECTION-BOGUES.md](PLAN-CORRECTION-BOGUES.md) : issue GitHub,
branche `fix/*` dediee, test de non-regression, PR :

| Issue | Anomalie | Correctif |
|---|---|---|
| #20 | Double modale de confirmation a la suppression d'une mission | `fix/bugs-ui-missions-devis-avoir` — PR #23 |
| #21 | Bouton Modifier absent pour les missions et les devis | `fix/bugs-ui-missions-devis-avoir` — PR #23 |
| #22 | Montant HT non pre-rempli dans le dialog « Emettre un avoir » | `fix/bugs-ui-missions-devis-avoir` — PR #23 |
| #52 | Arrondi des tranches 30/30/40 non reconcilie avec le total facture | `fix/arrondi-tranches-facturation` — PR #53 |

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
- GitHub Actions : lint -> tests (back + front) -> audits dependances -> E2E

**Competences :** C2.1.2 (CI), C4.2.2 (CD)

### Phase 3 — Release vers main
1. Mettre a jour `CHANGELOG.md` -> section `[x.y.z]`
2. PR `develop -> main` (revue finale + merge)
3. Tag Git : `git tag vx.y.z`
4. GitHub Release avec notes
5. Deploiement selon la procedure du [MANUEL-DEPLOIEMENT.md](MANUEL-DEPLOIEMENT.md)

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

## Versioning et tags (SemVer)

Le projet suit le **versionnage semantique** `MAJEUR.MINEUR.CORRECTIF` :

| Increment | Quand |
|---|---|
| **MAJEUR** | Rupture de compatibilite (API, schema de donnees) |
| **MINEUR** | Nouvelle fonctionnalite retro-compatible |
| **CORRECTIF** | Correction de bogue retro-compatible |

Le **registre des versions** est [`CHANGELOG.md`](../CHANGELOG.md) (format
*Keep a Changelog*). L'historique va de `0.1.0` (mise en place initiale) a la
section `[Unreleased]` en cours, close a la prochaine release.

**Un tag est pose a chaque release vers `main`** (jamais sur une branche
d'integration non fusionnee) :

```bash
git checkout main && git pull
git tag -a vX.Y.Z -m "Version X.Y.Z"
git push origin vX.Y.Z
```

Le tag pointe le commit de merge sur `main` ; les notes de la GitHub Release
reprennent la section correspondante du CHANGELOG.

## Regle d'or

> **JAMAIS de push direct sur main.** Tout passe par une PR.
> Le jury verifie dans l'historique GitHub que chaque merge passe par une PR.
