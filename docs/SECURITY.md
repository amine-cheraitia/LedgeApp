# Sécurité — Ledge

> Document de suivi sécurité. Couvre la gestion des dépendances vulnérables
> (OWASP A06) et les contrôles transversaux (voir aussi US-36 du BACKLOG).

---

## Composants tiers — advisories (OWASP A06)

### État au 2026-07-23 : audits vierges après les remédiations de juillet

Deux vagues d'advisories ont été traitées depuis l'état du 05/07 (détail complet dans le
[CHANGELOG](../CHANGELOG.md)) :

- **20/07** — 6 advisories : 4 sur `guzzlehttp/guzzle` (medium) et 2 **high** sur des
  dépendances transitives frontend (`brace-expansion`, `immutable`). Remédiation :
  `composer update guzzlehttp/guzzle` → 7.15.1 + `npm audit fix` (PR #97).
- **22-23/07** — 3 advisories **low** sur `dompdf/dompdf` < 3.1.6 (contournement du chroot,
  oracle d'existence de fichier via `@font-face`, lecture de fichier local via SVG).
  Remédiation : `dompdf` → **3.1.6** (PR #101). Impact réel quasi nul : les PDF sont rendus
  depuis des templates Blade internes, sans HTML/CSS fourni par l'utilisateur.

Vérification post-remédiation (23/07) : `composer audit` et `npm audit --omit=dev` vierges ;
suites re-exécutées après chaque mise à jour — **497 tests backend** et **604 tests frontend**
verts ; rendu PDF réel contrôlé après la montée dompdf. Ces deux épisodes ont été **détectés
par l'étape d'audit bloquante de la CI** (cf. Surveillance continue) — le mécanisme fonctionne.

### État au 2026-07-05 : `composer audit` et `npm audit` vierges

À la suite de la remédiation ci-dessous, les deux audits ne remontent **plus aucune
advisory** :

```bash
cd backend  && composer audit          # No security vulnerability advisories found.
cd frontend && npm audit --omit=dev    # found 0 vulnerabilities
```

Aucune advisory n'est masquée : l'entrée historique `config.audit.ignore`
(`PKSA-21fb-n1x5-5nf7`) de `backend/composer.json` — orpheline (ne correspondait plus à
aucune advisory active) — a été **supprimée**. `composer audit` reste vierge sans elle.

### Remédiation appliquée

**Backend (`composer update` dans les contraintes existantes, sans montée de version majeure)** :
les advisories précédemment tracées sur Symfony 7.x, `guzzlehttp/guzzle`, `guzzlehttp/psr7`,
`symfony/http-foundation`, `symfony/http-kernel`, `symfony/mailer`, `symfony/mime`,
`symfony/routing`, `symfony/yaml`, `symfony/polyfill-intl-idn` et **`laravel/framework`**
(dont un advisory *high* CVE-2026-48019 sur la validation d'e-mail par défaut) sont corrigées
par la mise à jour vers les versions correctives publiées dans les plages `^7.x` / `^12.0`
(notamment Symfony 7.4.x, Laravel 12.55.x, Guzzle 7.10.x). Aucune contrainte de
`composer.json` n'a été modifiée.

**Frontend** :
- **`axios`** (dépendance de production) bumpé de `^1.13.6` vers `^1.18.1` — corrige les
  advisories applicatives réelles (SSRF via bypass `NO_PROXY`, prototype pollution → injection
  d'en-tête, altération JSON, etc.), les seules réellement exécutées dans le bundle navigateur.
- **Outillage de build/dev** (`vite`, `postcss`, `picomatch`, `brace-expansion`, `form-data`)
  patché via `npm audit fix` (mises à jour sémantiquement compatibles, sans `--force`). Ces
  paquets ne sont **pas expédiés dans le bundle de production** servi au navigateur : leur
  risque se limitait au poste de développement / à la CI. Ils sont néanmoins corrigés.

### Vérification post-remédiation

- Backend : `php artisan test` → **415 tests verts** après `composer update`.
- Frontend : `vitest` → **557 tests verts**, `vue-tsc --noEmit` OK, `vite build` OK après
  bump axios + `npm audit fix`.

### Surveillance continue

- La CI (`.github/workflows/ci.yml`) exécute `composer audit` (**bloquant** : toute advisory
  Composer fait échouer le pipeline) et `npm audit --omit=dev --audit-level=high` (**bloquant
  à partir de la sévérité high**) à chaque build : une nouvelle advisory bloque les merges
  jusqu'à remédiation — démontré les 20/07 (Guzzle/npm) et 22/07 (dompdf).
- **Scan des images de release (Trivy)** : le pipeline CD (`cd.yml`) scanne chaque image de
  production avant publication — une vulnérabilité **HIGH/CRITICAL corrigeable** (système
  d'exploitation ou dépendance embarquée) **bloque la publication**. Couche complémentaire aux
  audits Composer/npm : elle couvre aussi les paquets OS de l'image. Contrôle démontré lors de
  la validation du pipeline (2 CVE HIGH du paquet Debian `linux-libc-dev` bloquées, corrigées
  par mise à niveau des paquets au build).
- Dès qu'une nouvelle advisory apparaît : évaluer l'impact, appliquer `composer update` /
  `npm audit fix` dans les contraintes, et mettre à jour ce document.

---

## Secrets & fichiers d'environnement (OWASP A05)

> **Note sur l'archive de livraison (évaluation)** : l'archive remise pour
> évaluation embarque volontairement un **compte SMTP de démonstration** (envoi
> réel des devis, factures et invitations pendant les tests). Ce compte est
> dédié à l'évaluation, ne donne accès à aucune donnée, n'est **pas versionné
> dans le dépôt git** (fichier d'environnement injecté uniquement dans
> l'archive), et est **révoqué après l'évaluation**. Aucun secret de production
> n'est distribué.

### Constat — audit interne du 2026-07-17

Deux fichiers d'environnement versionnés contenaient des valeurs sensibles :

- `backend/.env.e2e` : une **`APP_KEY` fonctionnelle** (clé de chiffrement Laravel) commitée.
  Portée réelle limitée (base E2E jetable `ledge_e2e`, données factices), mais une clé
  cryptographique valide ne doit jamais être versionnée.
- `backend/.env.docker` : `DB_PASSWORD=secret` en clair (stack de démo Docker isolée).

### Remédiation (2026-07-18)

- `backend/.env.e2e` **retiré du suivi git** (`.gitignore`) et remplacé par un template
  `backend/.env.e2e.example` sans clé. Le fichier réel est généré automatiquement au
  chargement de la config Playwright (`frontend/e2e/ensure-env.ts` : copie du template +
  `php artisan key:generate --env=e2e`). Garde-fou ajouté dans le `global-setup` : si le
  fichier manque, la suite **refuse de démarrer** plutôt que de laisser `--env=e2e`
  retomber sur le `.env` de dev (où `migrate:fresh` aurait détruit la base `ledge`).
- La clé E2E exposée dans l'historique git est **considérée compromise** : régénérée
  localement, elle ne protège plus rien (l'historique n'est pas réécrit — coût/bénéfice
  défavorable pour une clé de test morte).
- `backend/.env.docker` ne contient **plus aucun mot de passe** : `DB_PASSWORD` est fourni
  par `docker-compose.yml` (variable `DB_PASSWORD`, défaut de démo surchargeable avant
  `up`), même patron que `ADMIN_PASSWORD`. L'environnement conteneur prime sur le `.env`
  copié.

### Risques résiduels acceptés (documentés)

- Le **défaut de démo** `secret` reste visible dans `docker-compose.yml` : c'est un
  paramètre par défaut d'une stack locale isolée (réseau Docker interne, port MySQL 3307),
  surchargeable, pas un secret d'exploitation. Toute mise en production suit
  `docs/MANUEL-DEPLOIEMENT.md` (valeurs dédiées, jamais les défauts).
- `frontend/.env` reste versionné : il ne contient que des variables `VITE_*` (URLs
  locales), **publiques par conception** — Vite les injecte en clair dans le bundle
  livré au navigateur.

## Cartographie OWASP Top 10 (2021)

Contrôles en place dans Ledge, catégorie par catégorie.

| # | Catégorie | Contrôles dans Ledge |
|---|---|---|
| **A01** | Broken Access Control | Rôles Spatie + **Policy par ressource** ; middlewares `EnsureBackofficeAccess` / `EnsurePortailAccess` ; **isolation portail** stricte (`where('entreprise_id', …)` + `abort_if(403)`) ; guards Vue Router (`meta.backoffice` / `meta.portail`) |
| **A02** | Cryptographic Failures | Mots de passe **bcrypt** (`BCRYPT_ROUNDS=12`) ; chiffrement applicatif via `APP_KEY` ; en prod `SESSION_SECURE_COOKIE=true` + `SESSION_SAME_SITE=strict` (HTTPS) ; jetons d'invitation/reset **à usage unique et hachés** ; aucun secret versionné |
| **A03** | Injection | **Eloquent / Query Builder avec bindings** uniquement, jamais `DB::raw()` sur entrée utilisateur ; **FormRequest** sur chaque `store`/`update` ; pas de `v-html` sur données utilisateur (XSS) ; en-têtes CSP |
| **A04** | Insecure Design | Invariants métier : **snapshots immuables** (TVA/TTC), **TVA historisée** par date, numérotation concurrente sûre (`lockForUpdate` + contrainte UNIQUE) ; **protection de suppression** (entités liées) ; découplage via Events/Observers |
| **A05** | Security Misconfiguration | `APP_DEBUG=false` en prod ; **`ApiExceptionRenderer`** (aucune fuite d'info serveur) ; **Telescope désactivé** en prod ; **CORS** limité à `FRONTEND_URL` ; en-têtes HTTP de sécurité |
| **A06** | Vulnerable Components | `composer audit` + `npm audit` **en CI** ; remédiation documentée ci-dessus ; `composer.lock` / `package-lock.json` épinglés |
| **A07** | Identification & Auth Failures | Auth **Sanctum SPA** (cookies) ; **throttling** login + reset (6/min) ; **réponses génériques** (pas d'énumération de comptes) ; **invitation** obligatoire (l'admin ne fixe jamais de mot de passe) ; règles de robustesse mot de passe |
| **A08** | Software & Data Integrity | Dépendances **verrouillées** (`*.lock`) et vérifiées en CI ; jetons signés / à usage unique ; aucune désérialisation de données non fiables |
| **A09** | Logging & Monitoring Failures | Logging structuré + **Sentry** ; **journal d'audit métier** (`spatie/laravel-activitylog`) sur les entités sensibles ; **Laravel Health** (`/health`, admin) ; sonde publique `/up` (UptimeRobot) |
| **A10** | SSRF | Aucune requête sortante pilotée par l'utilisateur ; advisory SSRF d'`axios` **corrigée** (bump `^1.18.1`) ; génération PDF/mail sur entrées de confiance |

> Détail complémentaire et suivi : **US-36 (OWASP Top 10)** et **US-47 (audit)** du
> `docs/BACKLOG.md`.
