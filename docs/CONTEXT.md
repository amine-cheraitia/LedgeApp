# Ledge — Contexte Projet

> Dernière mise à jour : 23 Mars 2026 — Architecture N-tier (Vue.js + Laravel API)
> RNCP 39583 · Expert en Développement Logiciel · YNOV

---

## Identité

| | |
|---|---|
| **Nom** | Ledge |
| **Type** | Système de gestion intégré pour cabinets de conseil / comptabilité |
| **Marché cible** | Algérie — cabinet pilote en premier, extensible nationalement |
| **Contexte** | Le cabinet ne dispose d'aucun outil numérique centralisé. Gestion sur Excel / papier → pertes d'information, erreurs de facturation, relances oubliées, aucune traçabilité. Ledge remplace tout ça. |
| **Deadline** | Début juin 2026 — MVP complet + tous les livrables RNCP |

---

## Stack Technique

| Couche | Choix | Version |
|---|---|---|
| **Architecture** | N-tier 3 couches (présentation / métier / données) | — |
| **Frontend** | Vue 3 + TypeScript + PrimeVue + Pinia + Vue Router | Vue 3.5 / PrimeVue 4 |
| **Backend** | Laravel (API REST) + Sanctum + PHP | Laravel 12 / PHP 8.3 |
| **BDD** | MySQL | 9.1.0 (WAMP local) |
| **Auth** | Laravel Sanctum (SPA cookie-based) | v4.3 |
| **Permissions** | Spatie Laravel Permission | v7.2 |
| **PDF** | DomPDF | v3.1 |
| **Queue / Cache** | Database driver (WAMP) | — |
| **Serveur cible** | Nginx + PHP-FPM | VPS Linux Ubuntu 22 LTS |
| **CI/CD** | GitHub Actions | Déploiement automatisé |
| **Dev tooling** | WAMP (Windows) + Claude Code | Environnement local |

> **Dev local :** WAMP sur Windows. Fix MySQL 9 requis : `ROW_FORMAT=DYNAMIC` dans `config/database.php`.

---

## Structure du projet

```
Ledge/
├── backend/              # Laravel 12 — API REST
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/   # Par domaine (Auth/, Entreprises/, Facturation/...)
│   │   │   ├── Requests/      # FormRequests par domaine
│   │   │   ├── Resources/     # API Resources JSON par domaine
│   │   │   └── Middleware/    # EnsureBackofficeAccess, EnsurePortailAccess
│   │   ├── Models/            # 19 modèles Eloquent
│   │   └── Providers/
│   ├── routes/api.php         # Toutes les routes API /api/v1/*
│   ├── database/              # Migrations + seeders
│   └── tests/
├── frontend/             # Vue 3 + TypeScript + PrimeVue
│   ├── src/
│   │   ├── api/           # Client Axios avec intercepteurs CSRF
│   │   ├── assets/        # CSS mobile-first + RGAA
│   │   ├── layouts/       # AdminLayout, PortailLayout
│   │   ├── pages/         # Pages par domaine
│   │   ├── router/        # Vue Router avec guards par rôle
│   │   ├── stores/        # Pinia stores (auth, etc.)
│   │   └── types/         # Interfaces TypeScript
│   └── package.json
├── .github/              # PR template RNCP, GitHub Actions
├── docs/                 # Documentation projet
├── CHANGELOG.md
├── CLAUDE.md
└── README.md
```

---

## Accès Local (Développement)

| Service | URL | Commande |
|---|---|---|
| **Backend API** | `http://localhost:8000/api/v1/*` | `cd backend && php artisan serve` |
| **Frontend** | `http://localhost:5173` | `cd frontend && npm run dev` |

**Compte admin par défaut :** `admin@ledge.dz` / `password`

---

## Modules Métier

### Core / Settings
Auth, rôles et permissions (Spatie Laravel Permission), **paramètres globaux configurables sans code** :
- Taux TVA avec **historique versionné** (date d'entrée en vigueur)
- Timbre fiscal (1%, plafonné 2 500 DA — LF 2024)
- Coordonnées cabinet (nom, adresse, NIF, NIS, RIB, logo)
- Numérotation factures (préfixe, format annuel ou séquentiel)
- Délais de relance (J+X par niveau)
- Modèles mails de relance (templates personnalisables)
- Grille tarifaire : tarifs de base prestations, indices régime fiscal, indices catégorie

**Rôles applicatifs** : `admin` · `collaborateur` · `secretaire` · `client`

**Colonne `users.entreprise_id`** : nullable — renseignée **uniquement** pour le rôle `client`. Les users `admin`, `collaborateur`, `secretaire` ont cette colonne à `NULL`.

| Fonctionnalité | Admin | Collaborateur | Secrétaire | Client |
|---|:---:|:---:|:---:|:---:|
| Paramétrage / TVA / tarifs / exercices | ✅ | ✗ | ✗ | ✗ |
| Gestion utilisateurs & affectation rôles | ✅ | ✗ | ✗ | ✗ |
| KPI global + performance collaborateurs | ✅ | ✗ | ✗ | ✗ |
| Clients / Prospects (CRUD) | ✅ | ✗ | ✗ | ✗ |
| Devis & Facturation (créer, émettre, PDF) | ✅ | ✗ | ✗ | ✗ |
| Missions & Planning global | ✅ | ✗ | ✗ | ✗ |
| Tâches — voir ses tâches assignées | ✅ | ✅ | ✗ | ✗ |
| Tâches — changer le statut | ✅ | ✅ | ✗ | ✗ |
| Tâches — ajouter un commentaire | ✅ | ✅ | ✗ | ✗ |
| Consulter les créances | ✅ | ✗ | ✅ | ✗ |
| Relance manuelle (bouton) | ✅ | ✗ | ✅ | ✗ |
| Relance automatique (config queue) | ✅ | ✗ | ✗ | ✗ |
| Portail client (ses factures / docs) | ✗ | ✗ | ✗ | ✅ |

---

### Clients / Dossiers
Fiche entreprise avec **deux statuts distincts** :
- **Prospect** : entreprise ayant demandé un devis mais sans mission en cours.
- **Client** : entreprise ayant au moins une mission active ou passée.

---

### Facturation
Devis, factures, avoirs. Séparation par **exercice fiscal**. Calcul automatique TVA + timbre fiscal. Génération PDF conforme DGI.

**Calcul du prix HT d'une mission :**
```
Prix HT = prestation.tarif_initial × regime_fiscal.indice × categorie.indice
```

**Calcul TVA sur facture :**
```
Montant TVA    = Prix HT × taux_tva_en_vigueur_à_la_date_de_facture
Timbre fiscal  = min(Prix HT × taux_timbre, plafond_timbre)
Prix TTC       = Prix HT + Montant TVA + Timbre fiscal
```

---

### Planning
FullCalendar (Vue.js), missions, tâches, assignation collaborateurs, commentaires internes.

---

### Relances / Mails
- **Automatique** : règles paramétrables (J+15, J+30…) via queue Laravel.
- **Manuelle** : bouton accessible à l'Admin et à la Secrétaire.

---

### Portail Client
Routes Vue.js séparées (`/portail`) — rôle `client` uniquement, lecture seule.

**Flux d'accès :**
1. Admin ouvre la fiche Entreprise (statut = `client`)
2. Admin clique **"Activer l'accès portail"**
3. Ledge crée un `User` avec `entreprise_id` + rôle `client`
4. Email envoyé avec lien de définition de mot de passe
5. Admin peut révoquer via `portail_actif = 0`

---

### KPI / Reporting
Performance personnel, taux de recouvrement, délais moyens, objectifs vs réalisé.

---

## Règles Métier Critiques

### TVA — Historisation obligatoire

```php
// Toujours utiliser cette méthode, jamais un taux en dur
$tva = TvaRate::enVigueurLe($facture->date_facture);
$timbre = TimbreRate::enVigueurLe($facture->date_facture);
```

### Exercices Fiscaux — Séparation stricte
Numérotation réinitialisée chaque année : `FF2026-001`, `FF2027-001`…

---

## Spécificités Réglementaires Algériennes

| Règle | Détail |
|---|---|
| TVA standard | 19% — LF 2023 (historisé) |
| TVA réduite | 9% (historisé) |
| Timbre fiscal | 1% plafonné à 2 500 DA — LF 2024 (historisé) |
| Mentions obligatoires facture | NIF + NIS + RC + Art. imposition, numéro chronologique, date |
| Exercice fiscal | Année civile (janvier → décembre) |

---

## Schéma BDD — Tables principales

```
users                  → auth + rôles Spatie (entreprise_id nullable, portail_actif)
entreprises            → clients & prospects
exercices              → exercices fiscaux par année
tva_rates              → historique taux TVA
timbre_rates           → historique taux timbre fiscal
settings               → paramètres clé/valeur
prestations            → catalogue avec tarif_initial
regimes_fiscaux        → Forfait (×1.0) / Réel (×1.5)
categories_entreprise  → TPE (×1.0) / PME (×1.75) / GE (×2.0)
missions               → missions par entreprise + exercice + prestation
mission_user           → affectation collaborateurs
taches                 → tâches par mission
tache_commentaires     → commentaires sur tâches
devis                  → devis par entreprise + exercice
devis_lignes           → lignes de devis
factures               → FF (facture) / FA (avoir)
facture_lignes         → lignes de facture
paiements              → paiements reçus par facture
relances               → journal des relances
documents              → fichiers PDF et documents
```

---

## Décisions Architecturales Clés

**Architecture N-tier retenue** — séparation claire entre :
- **Tier 1 (Présentation)** : Vue 3 + PrimeVue — SPA accessible RGAA, responsive mobile-first
- **Tier 2 (Métier)** : Laravel API REST + Sanctum — controllers organisés par domaine
- **Tier 3 (Données)** : MySQL via Eloquent ORM

**Organisation backend classique avec sous-dossiers par domaine** — pas de modules avec ServiceProviders séparés. Chaque domaine (Auth, Entreprises, Facturation, Planning...) a ses controllers, requests et resources dans des sous-dossiers dédiés.

**Sanctum SPA mode** — authentification cookie-based (pas de tokens Bearer) pour le même domaine. CORS configuré pour `localhost:5173`.

**`users.entreprise_id` nullable** — `NULL` pour admin/collaborateur/secrétaire, renseigné uniquement pour le rôle `client`.

**Table `tva_rates` versionnée** — taux TVA et timbre fiscal historisés avec date d'entrée en vigueur.

**Séparation par exercice fiscal** — toute la facturation est cloisonnée par année.

---

## Exigences Non-Fonctionnelles

### Accessibilité RGAA (C2.2.3 ★)

| Critère | Implémentation |
|---|---|
| Contraste couleurs (AA min 4.5:1) | Thème PrimeVue Aura validé |
| Navigation clavier | Tab/Enter/Esc sur tous les composants |
| Labels formulaires | `<label>` ou `aria-label` sur chaque input |
| Messages d'erreur | `aria-live` ou `role="alert"` |
| Focus visible | `outline` CSS sur `:focus-visible` |
| Skip link | "Aller au contenu principal" sur chaque page |
| Titres hiérarchiques | Structure `h1 > h2 > h3` cohérente |

### Sécurité OWASP

| Règle | Implémentation |
|---|---|
| A01 — Broken Access Control | Middlewares par rôle, Policies Laravel |
| A03 — Injection | FormRequests obligatoires, jamais de `DB::raw()` avec input |
| A07 — XSS | Sanitisation Vue.js (pas de `v-html` avec données utilisateur) |
| CSRF | Sanctum cookie + CSRF token automatique |

### Responsive Mobile-First
- Breakpoints : 768px (tablette), 1024px (desktop)
- Layout sidebar desktop, hamburger mobile
- DataTable responsive avec scroll horizontal

---

## Stratégie Git — Gitflow 5 Phases RNCP

### Modèle de branches

```
main          ← production stable — merge via PR uniquement
develop       ← intégration continue
feature/xxx   ← une branche par fonctionnalité (depuis develop)
fix/xxx       ← hotfix (depuis main, merge double main+develop)
```

### 5 Phases

**Phase 1 — Feature branch :**
- `git checkout develop && git checkout -b feature/xxx`
- Conventional Commits : `feat(module):`, `fix(module):`, `chore(module):`
- Tests avant PR → C2.2.2 ★
- PR vers develop avec template RNCP → C4.2.2

**Phase 2 — Merge develop :**
- GitHub Actions : lint → tests → build → staging → C2.1.2 ★

**Phase 3 — Release main :**
- CHANGELOG.md mis à jour → C4.3.2 ★ obligatoire
- PR develop → main + Tag Git + GitHub Release
- Deploy prod auto → C4.2.2

**Phase 4 — Hotfix :**
- Branche depuis main, merge double (main + develop)
- Fiche anomalie → C4.2.1 ★

**Phase 5 — Dépendances :**
- Branche `fix/deps-xxx`, PR → develop
- `chore(deps):` → C4.1.1 + C4.3.2

**Règle d'or : JAMAIS de push direct sur main.**

---

## Bloc 4 — MCO & Supervision

| Outil | Usage | Compétence |
|---|---|---|
| UptimeRobot | Ping HTTP toutes les 5 min | C4.1.2 ★ |
| Laravel Health (spatie) | Endpoint `/health` (BDD, stockage, queue) | C4.1.2 ★ |
| Sentry (free tier) | Remontée automatique des erreurs PHP | C4.2.1 ★ |
| GitHub Releases | Journal des versions + CHANGELOG | C4.3.2 ★ |

---

## RNCP 39583 — Suivi des Compétences

### Bloc 1 — Cadrer le projet ✅ Terminé

| Compétence | Obligatoire | Statut |
|---|---|---|
| C1.1.1 Cartographie parties prenantes | ★ | ✅ |
| C1.1.2 Analyse de la demande | | ✅ |
| C1.2.1 SWOT | | ✅ |
| C1.2.2 Faisabilité technique | ★ | ✅ |
| C1.2.3 Cartographie des risques | | ✅ |
| C1.3.1 Veille technologique & réglementaire | | ✅ |
| C1.3.2 Comparatif solutions techniques | ★ | ✅ |
| C1.4.1 Charge de travail | ★ | ✅ |
| C1.4.2 Budget prévisionnel | | ✅ |
| C1.5 Modélisation architecture | | ✅ |
| C1.6 Préconisation client | ★ | ✅ |

### Bloc 2 — Concevoir & Développer ⏳ En cours

| Compétence | Obligatoire | Statut |
|---|---|---|
| C2.1.1 Environnement déploiement / tests | | ⏳ |
| C2.1.2 Intégration continue | | ⏳ |
| C2.2.1 Prototype fonctionnel | ★ | ⏳ |
| C2.2.2 Tests unitaires | ★ | ⏳ |
| C2.2.3 Sécurité OWASP & accessibilité RGAA | ★ | ⏳ |
| C2.2.4 Déploiement progressif | | ⏳ |
| C2.3.1 Cahier de recettes | ★ | ⏳ |
| C2.3.2 Plan de correction des bugs | | ⏳ |
| C2.4.1 Documentation technique | | ⏳ |

### Bloc 3 — Piloter le projet ⏳ À faire

| Compétence | Obligatoire | Statut |
|---|---|---|
| C3.1 Planning / méthodologie | ★ | ⏳ |
| C3.2.1 Suivi avancement & indicateurs | ★ | ⏳ |
| C3.4.2 Démonstration live | ★ | ⏳ |

### Bloc 4 — MCO ⏳ À faire

| Compétence | Obligatoire | Statut |
|---|---|---|
| C4.1.1 Mises à jour dépendances | | ⏳ |
| C4.1.2 Supervision & alertes | ★ | ⏳ |
| C4.2.1 Consignation anomalies | ★ | ⏳ |
| C4.2.2 Correctif CI/CD | | ⏳ |
| C4.3.2 Journal des versions | ★ | ⏳ |

---

## Planning — 12 Semaines

| Semaines | Phase | Contenu | Statut |
|---|---|---|---|
| S1–S2 | Cadrage & Bloc 1 | Dossier de cadrage, SWOT, comparatif, architecture | ✅ |
| S3–S4 | Architecture & Setup | Schéma BDD, migrations, doc technique | ✅ |
| S5–S6 | Sprint 1 — Core | Auth, Entreprises, Facturation, Settings, Exercices | ⏳ |
| S7–S8 | Sprint 2 — Avancé | Planning FullCalendar, Relances, Portail client, KPI | ⏳ |
| S9 | Sprint 3 — Qualité | OWASP, RGAA, tests unitaires | ⏳ |
| S10–S11 | Recette & MCO | Cahier de recettes, CHANGELOG, supervision | ⏳ |
| S12 | Soutenance | Slides, démo live (C3.4.2 ★) | ⏳ |
