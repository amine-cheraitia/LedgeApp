# Sécurité — Ledge

> Document de suivi sécurité. Couvre la gestion des dépendances vulnérables
> (OWASP A06) et les contrôles transversaux (voir aussi US-36 du BACKLOG).

---

## Composants tiers — advisories (OWASP A06)

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

- La CI (`.github/workflows/ci.yml`) exécute désormais `composer audit` et
  `npm audit --omit=dev` à chaque build (étapes **non bloquantes** : elles rendent toute
  nouvelle advisory visible dans les logs sans casser le pipeline).
- Dès qu'une nouvelle advisory apparaît : évaluer l'impact, appliquer `composer update` /
  `npm audit fix` dans les contraintes, et mettre à jour ce document.

---

## Cartographie OWASP Top 10 (2021)

Contrôles en place dans Ledge, catégorie par catégorie.

| # | Catégorie | Contrôles dans Ledge |
|---|---|---|
| **A01** | Broken Access Control | Rôles Spatie + **Policy par ressource** ; middlewares `EnsureBackofficeAccess` / `EnsurePortailAccess` ; **isolation portail** stricte (`where('entreprise_id', …)` + `abort_if(403)`) ; guards Vue Router (`meta.zone`) |
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
