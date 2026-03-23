# CHANGELOG — Ledge

> Format : [Semantic Versioning](https://semver.org) — `MAJOR.MINOR.PATCH`
> - **MAJOR** : rupture de compatibilité (migration BDD obligatoire)
> - **MINOR** : nouvelle fonctionnalité rétro-compatible
> - **PATCH** : correctif sans impact sur le schéma

---

## [Unreleased]

### En cours
- Module KPI / Reporting (objectifs vs réalisé par collaborateur)
- Portail client — panel Filament `/portail` (resources + pages)
- Relances automatiques via queue Laravel
- Resources Filament back-office : Entreprises, Facturation, Settings

---

## [0.1.0] — 2026-03-16

### Ajouts — Setup initial

#### Infrastructure & Configuration
- Laravel 12 (PHP 8.3) + Filament v3.3 + Spatie Permission v7.2 + DomPDF + Spatie Health
- Base de données MySQL 9.1 — fix `ROW_FORMAT=DYNAMIC` pour utf8mb4 (WAMP)
- `Schema::defaultStringLength(191)` dans `AppServiceProvider`
- Deux panels Filament : `AdminPanelProvider` (`/admin`) + `PortailPanelProvider` (`/portail`)
- Middleware `EnsureBackofficeAccess` — bloque les clients sur le back-office
- Middleware `EnsurePortailAccess` — bloque les non-clients sur le portail, vérifie `portail_actif`

#### Base de données — 20 tables migrées
- `users` + `users.entreprise_id` (nullable FK) + `users.portail_actif`
- `entreprises` — clients & prospects avec régime fiscal et catégorie
- `exercices` — exercices fiscaux par année avec statut `ouvert/cloturé`
- `tva_rates` + `timbre_rates` — historique taux avec `date_debut`/`date_fin`
- `settings` — paramètres clé/valeur par groupe
- `prestations` + `regimes_fiscaux` + `categories_entreprise` — grille tarifaire
- `missions` + `mission_user` — missions et affectation collaborateurs
- `taches` + `tache_commentaires` — planning et commentaires
- `devis` + `devis_lignes` — gestion des devis
- `factures` + `facture_lignes` — facturation FF/FA avec snapshot TVA
- `paiements` — suivi des encaissements
- `relances` — journal des relances
- `documents` — stockage et partage portail

#### Données initiales seedées
- 4 rôles : `admin`, `collaborateur`, `secretaire`, `client`
- Compte admin : `admin@ledge.dz` / `password`
- TVA 19% (standard) + 9% (réduit) — LF 2023
- Timbre fiscal 1% plafonné 2 500 DA — LF 2024
- 5 prestations réelles du cabinet (CAC, ACMPT, AENT, ASSC, A&C)
- Indices régimes fiscaux (Forfait ×1.0, Réel ×1.5) et catégories (TPE ×1.0, PME ×1.75, GE ×2.0)
- Exercice fiscal 2026 ouvert
- 12 paramètres `settings` initiaux (cabinet, facturation, délais relances)

#### Modèles Eloquent
- 18 modèles créés avec relations, casts et scopes
- `TvaRate::enVigueurLe($date)` + `TimbreRate::enVigueurLe($date)` — résolution historique
- `Prestation::calculerPrixHt($regime, $categorie)` — formule grille tarifaire
- `User::canAccessPanel()` — contrôle d'accès Filament par panel

---

## Branches actives

| Branche | Objectif |
|---|---|
| `main` | Production stable — merge via PR uniquement |
| `develop` | Intégration continue — base de toutes les features |
| `feature/auth-roles` | Module Auth + Spatie |
| `feature/facturation` | Devis, factures, avoirs, PDF |
| `feature/portail-client` | Panel portail + activation accès |
| `feature/planning-calendar` | FullCalendar + missions + tâches |
| `feature/relances` | Relances auto + manuelles |
| `feature/kpi` | Objectifs, résultats, dashboard |
| `cicd` | Configuration GitHub Actions |

---

## Convention de commits

```
feat(module): description courte
fix(module): description du correctif
chore(deps): mise à jour dépendance X
test(module): ajout tests unitaires
docs(changelog): mise à jour journal
refactor(module): refactoring sans changement fonctionnel
```

### Exemples Ledge
```
feat(facturation): calcul automatique TVA + timbre fiscal avec snapshot
feat(portail): activation accès client depuis fiche entreprise
feat(relances): relance automatique J+15 via queue Laravel
fix(factures): snapshot tva_taux_id manquant à la création d'avoir
fix(exercices): numérotation annuelle ne se réinitialisait pas
chore(deps): mise à jour Laravel 12.x.0
test(facturation): FormuleHTPrixServiceTest — 12 cas couverts
docs(changelog): v0.1.0 — setup initial + migrations + seeders
```
