# Ledge — Guide pour Claude Code

> Ce fichier est charge au demarrage de chaque session Claude Code.
> Il prime sur toute autre instruction.

---

## Projet

Systeme de gestion integre pour cabinet de conseil/comptabilite algerien.
Certification RNCP 39583 — Expert en Developpement Logiciel — YNOV.
Architecture : **N-tier 3 couches** — presentation (Vue.js) / metier (Laravel API) / donnees (MySQL).
Historique : V0 monolithique (Laravel 8 + Blade) -> V1 Filament (abandonnee) -> V2 actuelle N-tier.

### Stack technique

| Couche | Technologie |
|---|---|
| Backend | Laravel 12 — API REST — PHP 8.2+ |
| Auth | Laravel Sanctum (cookie-based SPA) |
| Frontend | Vue.js 3 SPA — Composition API — TypeScript |
| UI Library | PrimeVue 4 |
| State | Pinia |
| Router | Vue Router 4 |
| BDD | MySQL 8 |
| Roles | Spatie Laravel Permission |
| PDF | barryvdh/laravel-dompdf |
| Queue/Cache | Redis (production) / sync (dev) |
| MCO | UptimeRobot + Laravel Health + Sentry |

---

## Structure du projet

```
Ledge/
├── backend/              # Laravel 12 — API REST
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Auth/          # AuthController, UserController
│   │   │   │   ├── Entreprises/   # EntrepriseController
│   │   │   │   ├── Exercices/     # ExerciceController
│   │   │   │   ├── Facturation/   # DevisController, FactureController, PaiementController
│   │   │   │   ├── Planning/      # MissionController, TacheController
│   │   │   │   ├── Prestations/   # PrestationController
│   │   │   │   └── Settings/      # SettingController
│   │   │   ├── Requests/         # FormRequests par domaine
│   │   │   ├── Resources/        # API Resources JSON par domaine
│   │   │   └── Middleware/       # EnsureBackofficeAccess, EnsurePortailAccess
│   │   ├── Models/               # 19 modeles Eloquent
│   │   ├── Services/             # Logique metier (FacturationService, MissionService...)
│   │   ├── Events/               # MissionCreated, InvoicePaid, etc.
│   │   ├── Observers/            # MissionObserver, etc.
│   │   └── Providers/
│   ├── routes/api.php            # Toutes les routes API /api/v1/*
│   ├── database/                 # Migrations + seeders
│   └── tests/
├── frontend/             # Vue 3 + TypeScript + PrimeVue
│   ├── src/
│   │   ├── api/          # Client Axios + modules API par domaine
│   │   ├── assets/       # CSS (mobile-first, RGAA)
│   │   ├── composables/  # Logique reactive (prefixe use)
│   │   ├── layouts/      # AdminLayout, PortailLayout
│   │   ├── pages/        # Pages par domaine
│   │   ├── router/       # Vue Router avec guards
│   │   ├── stores/       # Pinia stores
│   │   └── types/        # TypeScript interfaces
│   └── package.json
├── .github/              # PR template, GitHub Actions
├── docs/                 # CONTEXT.md, ARCHITECTURE.md, GITFLOW.md, manuels, cahier de recettes...
├── CHANGELOG.md
└── README.md
```

---

## Commandes essentielles

```bash
# Backend (depuis backend/)
cd backend
php artisan migrate
php artisan db:seed
php artisan test
composer dump-autoload
php artisan serve          # http://localhost:8000
php artisan queue:work     # traitement des jobs (Redis en prod)

# Frontend (depuis frontend/)
cd frontend
npm install
npm run dev                # http://localhost:5173
npm run build
```

---

## Variables d'environnement cles

```env
# Backend (.env)
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DOMAIN=localhost

# Frontend (.env)
VITE_API_URL=http://localhost:8000
```

---

## Gitflow (5 phases RNCP)

```
main      <- production stable (jamais de push direct)
develop   <- integration
feature/* <- UNE branche par US/fonctionnalite (depuis develop)
fix/*     <- hotfix (depuis main, merge double main+develop)
```

- **Regle absolue : une branche = une seule fonctionnalite**
  - `feature/creation-facture` — pas `feature/facturation` qui regroupe N features
  - Nommage : `feature/{slug-fonctionnalite}` ex: `feature/creation-facture`, `feature/pdf-devis`
  - Jamais regrouper plusieurs fonctionnalites dans une meme branche feature
- Conventional Commits : `feat(module):`, `fix(module):`, `chore(module):`
- Toute modification passe par une PR avec le template RNCP
- CHANGELOG.md mis a jour avant chaque merge vers main

---

## Roles et acces (Spatie Permission)

4 roles : `admin` - `collaborateur` - `secretaire` - `client`

| Role | Panel | Restrictions |
|---|---|---|
| admin | `/admin` | Tout |
| collaborateur | `/admin` | Ses taches et missions uniquement |
| secretaire | `/admin` | Entreprises (CRUD sans suppression) + creances/recouvrement (paiements, relances) + envoi devis / transmission factures. Ne cree ni ne supprime devis/factures/avoirs. **Pas d'acces Missions ni Planning** |
| client | `/portail` | Lecture seule — ses donnees uniquement |

**`users.entreprise_id`** : nullable — NULL pour le staff, renseigne uniquement pour le role `client`.

Le client ne s'inscrit jamais lui-meme. L'Admin active l'acces depuis la fiche Entreprise.

---

## Regles metier CRITIQUES — ne jamais devier

### 1. Calcul prix HT mission
```php
// Prix HT = prestation.tarif_initial x regime_fiscal.indice x categorie.indice
// Ex: ACMPT (120 000) x Reel (1.5) x PME (1.75) = 315 000 DA
$prixHt = $prestation->calculerPrixHt($entreprise->regime_fiscal, $entreprise->categorie);
```
Ce montant est calcule UNE SEULE FOIS a la creation et stocke de facon immuable. **Jamais recalcule dynamiquement.**

**Conversion devis -> mission : le prix HT du devis accepte est CONTRACTUEL** — repris tel quel sur la mission (`devis->prix_ht`), jamais recalcule depuis la grille (le tarif ou les indices ont pu changer entre l'acceptation et la conversion). Un devis n'est acceptable que **dans son delai de validite** (jour d'echeance inclus) ; passe ce delai, l'acceptation le bascule en statut `expire` (409).

### 2. TVA historisee — regle absolue
```php
// TOUJOURS passer la date de facturation — JAMAIS Carbon::now()
$tva = TvaTaux::enVigueurLe($facture->date_facture);
```
La table `tva_taux` a `date_debut` / `date_fin`. Une facture de 2026 doit retourner 19% meme si appelee en 2030.

### 3. Snapshots immuables sur facture
Ces valeurs sont copiees a la creation et **ne changent jamais** :
`taux_tva_snapshot` - `montant_tva` - `prix_ttc`

### 4. Numerotation par exercice
Format : `{prefixe}{annee}-{sequence}`. Reinitialisee chaque exercice. Configurable via `settings`.
Utiliser un mecanisme avec `lockForUpdate` pour eviter les doublons en concurrence.

| Document | Prefixe | Exemple |
|---|---|---|
| Facture | FF | FF2026-001 |
| Avoir | FA | FA2026-001 |
| Devis | DV | DV2026-001 |
| Mission | M | M2026-001 |
| Mandat | MD | MD2026-001 |
| Convention | CV | CV2026-001 |

### 5. Scope portail — isolation absolue
Toute requete dans les controllers portail doit filtrer par `entreprise_id` :
```php
->where('entreprise_id', $request->user()->entreprise_id)
```

### 6. Prospect -> Client
Bascule automatique via Observer `MissionObserver` sur `MissionCreated`. Ne jamais faire cette bascule manuellement.

### 7. Exercice fiscal
```php
$exercice = Exercice::current(); // exercice ouvert de l'annee en cours
```
Toujours rattacher devis/factures/missions a un exercice. Separation stricte par annee.

---

## Tranches de facturation
```
Tranche 1 = 30% du total mission
Tranche 2 = 30% du total mission
Tranche 3 = 40% du total mission (solde)
```

## Statut facture (auto)
`en_attente` -> `partiel` -> `solde` (recalcule automatiquement selon paiements)

---

## Events Laravel (decouplage)
- `MissionCreated` -> bascule auto entreprise prospect -> client
- `InvoicePaid` -> annulation auto des relances en cours
- `FiscalYearClosed` -> archive les documents de l'exercice

---

## Portail client — activation
Admin -> Fiche Entreprise (statut=client) -> "Activer acces portail"
-> Cree User avec `entreprise_id` + role `client` (mot de passe placeholder aleatoire inutilisable)
-> Envoie une **invitation par email** (lien a usage unique via `InvitationService`) : le client **definit lui-meme** son mot de passe sur `/definir-mot-de-passe`.
-> `portail_actif = 1` pour activer, `0` pour revoquer

**Regle absolue : l'admin ne genere, ne voit ni ne transmet jamais de mot de passe.** Idem pour la creation d'un staff (collaborateur/secretaire) : creation sans mot de passe -> invitation. Repli si l'email n'arrive pas : un **lien copiable (sans mot de passe)** est affiche a l'admin. Renvoi possible via `renvoyer-invitation`. Mot de passe oublie en libre-service : `/mot-de-passe-oublie` -> `POST /forgot-password` (reponse generique) -> meme page `/definir-mot-de-passe`.

---

## Couche Services — regle obligatoire

Les controllers sont **minces** : ils valident (FormRequest), delegent au Service, et retournent la Resource.
Toute logique metier va dans `app/Services/`. Les Services utilisent Eloquent directement (pas de Repository).

```php
// Controller — mince
public function store(StoreFactureRequest $request): FactureResource
{
    $facture = $this->facturationService->creer($request->validated(), $request->user());
    return new FactureResource($facture);
}

// Service — logique metier
class FacturationService
{
    public function creer(array $data, User $user): Facture { ... }
}
```

Injection de dependances dans le constructeur du controller :
```php
public function __construct(private readonly FacturationService $facturationService) {}
```

---

## Modules metier (inventaire)

| Domaine | Controllers | Services | Modeles |
|---|---|---|---|
| Auth | AuthController, UserController, PasswordController | InvitationService | User |
| Entreprises | EntrepriseController, ContactController | — | Entreprise, Contact, CategorieEntreprise, RegimeFiscal |
| Exercices | ExerciceController | — | Exercice |
| Prestations | PrestationController | — | Prestation |
| Facturation | DevisController, FactureController, PaiementController, AvoirController, CreanceController | FacturationService, PdfService | Devis, DevisLigne, Facture, FactureLigne, Paiement, Avoir |
| Relances | RelanceController | RelanceService | Relance |
| Planning | MissionController, TacheController, TacheCommentaireController, CalendarController | MissionService, CalendarService | Mission, Tache, TacheCommentaire |
| Dashboard / KPI | DashboardController, StatistiqueController, KpiController | DashboardService, StatistiqueService, KpiService | KpiObjectif |
| Portail | PortailController, PortailFactureController, PortailMissionController, PortailDocumentController | — | — |
| Audit | AuditController | AuditService | — |
| Settings | SettingController | — | Setting |
| Referentiel | ReferentielTvaController | — | TvaTaux |

---

## MCO — Maintien en Conditions Operationnelles

| Outil | Role | Statut |
|---|---|---|
| Laravel Health | Health checks internes (BDD, cache, queue) | Installe |
| UptimeRobot | Monitoring externe (uptime, SSL, latence) | A configurer |
| Sentry | Error tracking + alerting temps reel | A installer |

---

## Jobs & Queues

| Job | Declencheur | Description |
|---|---|---|
| `EnvoyerRelancesJob` | Cron quotidien | Envoi automatique des relances echues |

Les KPI sont calcules a la volee (`KpiService`, `StatistiqueService`) — pas de job de snapshot.
Driver : **Redis** en production, **sync** en dev.

---

## Protection suppression
| Entite | Blocage si |
|---|---|
| Entreprise | devis ou missions associes |
| Mission | factures associees |
| Facture | paiements ou avoirs associes |
| Tache | commentaires associes |

---

## Migrations : ordre important
1. `000001_create_entreprises_table` — avant la FK dans users
2. `000099_add_entreprise_id_to_users_table` — apres entreprises

---

## Auth — Sanctum SPA (cookie-based)

```
GET    /sanctum/csrf-cookie      # recup CSRF token
POST   /api/v1/login             # login (cookie session)
POST   /api/v1/logout            # logout
GET    /api/v1/me                # user connecte + role
POST   /api/v1/forgot-password   # public — envoie lien reinit (reponse generique, throttle 6/min)
POST   /api/v1/reset-password    # public — definit le mot de passe via jeton (throttle 6/min)
```

Sanctum en mode SPA : authentification cookie-based (pas de tokens Bearer). CORS configure pour `localhost:5173`.

**Guards Vue Router** : route `meta.zone = 'backoffice'` -> bloque les clients. Route `meta.zone = 'portail'` -> bloque le staff.

---

## Routes API principales

```
# Auth
POST   /api/v1/login
POST   /api/v1/logout
GET    /api/v1/me

# Back-office (auth:sanctum + middleware backoffice)
GET|POST        /api/v1/entreprises
GET|PUT|DELETE  /api/v1/entreprises/{id}
GET|POST        /api/v1/exercices
GET|POST        /api/v1/prestations
GET|POST        /api/v1/settings
GET|POST        /api/v1/users

# Facturation
GET|POST        /api/v1/devis
GET|POST        /api/v1/factures
GET             /api/v1/factures/{id}/pdf
GET|POST        /api/v1/avoirs
GET             /api/v1/creances
POST            /api/v1/factures/{id}/relances

# Admin uniquement
POST   /api/v1/entreprises/{id}/activer-portail

# Portail client (auth:sanctum + middleware portail)
GET    /api/v1/portail/factures
GET    /api/v1/portail/documents
GET    /api/v1/portail/missions
```

---

## Format des reponses API

```typescript
// Liste paginee
{ data: [...], meta: { current_page, last_page, per_page, total } }

// Ressource unique
{ data: { id, ... } }

// Erreur validation (422)
{ message: "...", errors: { champ: ["message"] } }
```

---

## Conventions de code

### PHP / Laravel
- `declare(strict_types=1);` en haut de chaque nouveau fichier
- Tables en **francais** (snake_case) : `tva_taux`, `tache_commentaires`
- Modeles en **PascalCase francais** : `TacheCommentaire`, `RegimeFiscal`
- Controllers API dans `backend/app/Http/Controllers/{Domaine}/`
- FormRequests : `StoreFactureRequest`, `UpdateMissionRequest`
- API Resources dans `backend/app/Http/Resources/{Domaine}/`
- Events en **passe compose** : `FacturePayee`, `MissionCreee`
- Retours types obligatoires sur toutes les methodes publiques
- **JAMAIS** de `DB::raw()` avec input utilisateur -> Eloquent ou Query Builder avec bindings
- Form Request sur **tout** `store()` et `update()`
- Policy Laravel sur chaque ressource

### Vue.js / TypeScript
- Composition API + `<script setup lang="ts">` obligatoires — pas d'Options API
- Composables : prefixe `use` -> `useFactures.ts`, `useAuth.ts`
- Stores Pinia : suffixe `Store` -> `factureStore.ts`, `authStore.ts`
- Appels API toujours via les modules `api/` — jamais axios direct dans les composants
- **Composable vs module api/ (regle actee 2026-07)** : logique d'etat REUTILISABLE entre pages (listes, CRUD, filtres, toasts standards) -> composable `use*` obligatoire ; appel ponctuel sans etat partage (telechargement de PDF/blob, action one-shot, page auth isolee) -> appel direct du module `api/` tolere. Pas de refonte retroactive des pages existantes : la regle s'applique au code NOUVEAU et en refactor opportuniste quand on touche deja une page.
- Pages Vue dans `frontend/src/pages/{domaine}/`

---

## Securite — regles absolues

- Jamais de `DB::raw()` avec input utilisateur -> Eloquent uniquement
- Form Request sur **tout** `store()` et `update()`
- Policy Laravel sur chaque ressource
- CORS : `FRONTEND_URL` uniquement
- `APP_DEBUG=false` en production
- Pas de `v-html` avec donnees utilisateur (XSS)

---

## RGAA accessibilite (C2.2.3 — obligatoire RNCP)

Sur tout composant Vue avec PrimeVue :
- `aria-label` sur les boutons sans texte visible
- `role="alert"` + `aria-live="polite"` sur les messages d'erreur
- `:focus-visible { outline: 2px solid; outline-offset: 2px; }` dans le CSS global
- Textes de liens explicites — jamais "cliquez ici"
- `<label>` ou `aria-label` sur chaque input
- Skip link "Aller au contenu principal" sur chaque page
- Contraste minimum 4.5:1 sur tous les textes
- Navigation clavier complete sur tous les composants interactifs
- Pas de `v-html` avec donnees utilisateur (XSS + accessibilite)
- Structure semantique : `<main>`, `<nav>`, `<header>`, `<section>` avec `aria-labelledby`

---

## Devise

Tous les montants sont en **Dinars Algeriens (DA)**. Jamais d'euros dans l'application.

---

## Ce que Claude Code ne doit PAS faire

- Mettre de la logique metier dans les controllers -> tout deleguer au Service
- Appeler un Service d'un autre domaine directement -> passer par un Event Laravel
- Utiliser `Carbon::now()` pour resoudre la TVA -> passer la date de facturation
- Creer des composants Vue avec Options API -> Composition API uniquement
- Mettre la logique d'appel API dans les composants -> utiliser les composables ou modules api/
- Pousser directement sur `main` -> toujours via PR depuis `develop`
- Nommer un commit sans convention -> `feat/fix/chore(module): description`
- Utiliser `DB::raw()` avec input utilisateur -> Eloquent avec bindings
- Creer des fichiers inutiles ou sur-ingenier -> rester simple et direct
- Ajouter Co-Authored-By Claude dans les commits

---

## Note PowerShell (Windows)
Les heredocs bash ne fonctionnent pas sous PowerShell.
Pour les commits multi-lignes, utiliser un fichier temporaire ou un message court.
