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

## Contrôles transversaux

Voir **US-36 (OWASP Top 10)** du `docs/BACKLOG.md` pour le détail des contrôles en place :
CSRF (Sanctum), Eloquent uniquement, FormRequests, throttling login, en-têtes HTTP de
sécurité (CSP…), Policies par ressource, `ApiExceptionRenderer` (A05 — aucune fuite
d'infos serveur), logging structuré + Sentry (A09), et **journal d'audit métier**
(`spatie/laravel-activitylog`, US-47) traçant les actions sur les entités sensibles.
