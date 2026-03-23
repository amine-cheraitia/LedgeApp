# Ledge — Guide pour Claude Code

## Projet
Système de gestion intégré pour cabinet de conseil/comptabilité algérien.
Stack : **Laravel 12** (API backend) + **Vue 3 + PrimeVue** (frontend) + **MySQL** + **Spatie Permission** + **Sanctum**.
Architecture : **N-tier 3 couches** — présentation (Vue.js) / métier (Laravel API) / données (MySQL).

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
│   │   │   │   ├── Prestations/   # PrestationController
│   │   │   │   ├── Settings/      # SettingController
│   │   │   │   ├── Facturation/   # (à venir)
│   │   │   │   └── Planning/      # (à venir)
│   │   │   ├── Requests/         # FormRequests par domaine
│   │   │   ├── Resources/        # API Resources JSON par domaine
│   │   │   └── Middleware/       # EnsureBackofficeAccess, EnsurePortailAccess
│   │   ├── Models/               # 19 modèles Eloquent
│   │   └── Providers/
│   ├── routes/api.php            # Toutes les routes API /api/v1/*
│   ├── database/                 # Migrations + seeders
│   └── tests/
├── frontend/             # Vue 3 + TypeScript + PrimeVue
│   ├── src/
│   │   ├── api/          # Client Axios
│   │   ├── assets/       # CSS (mobile-first, RGAA)
│   │   ├── layouts/      # AdminLayout, PortailLayout
│   │   ├── pages/        # Pages par domaine
│   │   ├── router/       # Vue Router avec guards
│   │   ├── stores/       # Pinia stores
│   │   └── types/        # TypeScript interfaces
│   └── package.json
├── .github/              # PR template, GitHub Actions
├── CHANGELOG.md
└── README.md
```

## Commandes essentielles

```bash
# Backend (depuis backend/)
cd backend
php artisan migrate
php artisan db:seed
php artisan test
composer dump-autoload
php artisan serve          # http://localhost:8000

# Frontend (depuis frontend/)
cd frontend
npm install
npm run dev                # http://localhost:5173
npm run build
```

## Gitflow (5 phases RNCP)

```
main      ← production stable (jamais de push direct)
develop   ← intégration
feature/* ← une branche par module (depuis develop)
fix/*     ← hotfix (depuis main, merge double main+develop)
```

- Conventional Commits : `feat(module):`, `fix(module):`, `chore(module):`
- Toute modification passe par une PR avec le template RNCP
- CHANGELOG.md mis à jour avant chaque merge vers main

## Rôles (Spatie Permission)
- `admin` — accès total back-office
- `collaborateur` — voit ses tâches assignées, peut commenter
- `secretaire` — consulte créances, envoie relances manuelles
- `client` — portail uniquement, lecture seule de ses factures/docs

## Règle critique : users.entreprise_id
- `NULL` pour admin/collaborateur/secretaire
- Renseignée **uniquement** pour le rôle `client`
- Le scope API du portail filtre toujours par `entreprise_id`

## Règle critique : TVA historisée
```php
$tva = TvaRate::enVigueurLe($facture->date_facture);
$timbre = TimbreRate::enVigueurLe($facture->date_facture);
```

## Règle critique : Calcul prix HT mission
```php
$prixHt = $prestation->calculerPrixHt($entreprise->regime_fiscal, $entreprise->categorie);
```

## Migrations : ordre important
1. `000001_create_entreprises_table` — avant la FK dans users
2. `000099_add_entreprise_id_to_users_table` — après entreprises

## Numérotation factures
Format : `FF2026-001`, `FA2026-001` (avoir), `DV2026-001` (devis)

## Conventions de code
- Tables en **français** (snake_case) : `tva_rates`, `tache_commentaires`
- Modèles en **PascalCase français** : `TacheCommentaire`, `RegimeFiscal`
- Controllers API dans `backend/app/Http/Controllers/{Domaine}/`
- Pages Vue dans `frontend/src/pages/{domaine}/`
- API Resources dans `backend/app/Http/Resources/{Domaine}/`
- Accessibilité RGAA : aria-labels, skip-link, focus-visible, rôles ARIA
- Sécurité OWASP : FormRequests obligatoires, pas de DB::raw() avec input
- Responsive mobile-first

## Exigences non-fonctionnelles
- **Accessibilité** : RGAA (labels, aria, navigation clavier, contrastes)
- **Sécurité** : OWASP Top 10 (validation serveur, CSRF Sanctum, pas d'injection)
- **Responsive** : Mobile-first, breakpoints 768px / 1024px
- **Tests** : PHPUnit (backend) + tests composants (frontend)

## Note PowerShell (Windows)
Les heredocs bash ne fonctionnent pas sous PowerShell.
Pour les commits multi-lignes, utiliser un fichier temporaire ou un message court.
