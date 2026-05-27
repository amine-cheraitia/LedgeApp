# Workflow feature — Ledge

> Checklist à suivre pour chaque nouvelle fonctionnalité ou correctif.

---

## 1. Branche (avant de coder)

- [ ] Une branche = **une seule** fonctionnalité
- [ ] Créée depuis `develop` : `feature/{slug-fonctionnalite}`
- [ ] Aucun intitulé RNCP dans le nom de branche

```bash
git checkout develop
git pull
git checkout -b feature/mon-slug
```

---

## 2. Développement

### Architecture backend
- [ ] Controllers minces — logique métier dans `app/Services/`
- [ ] FormRequest sur tout `store()` et `update()`
- [ ] Policy sur chaque ressource + `$this->authorize()`
- [ ] Eloquent uniquement — jamais `DB::raw()` avec input utilisateur
- [ ] Pas de Service → Service — passer par un Event Laravel si besoin

### Architecture frontend
- [ ] Composition API + `<script setup lang="ts">`
- [ ] Pattern Page → Composable → API (pas d'axios direct dans les pages)
- [ ] RGAA : skip link, `aria-label`, `role="alert"`, focus-visible
- [ ] OWASP : pas de `v-html` avec données utilisateur

### Commits
- [ ] Conventional Commits : `feat(module):`, `fix(module):`, `chore(module):`
- [ ] Jamais de `Co-Authored-By` dans les messages

---

## 3. Avant la PR (obligatoire)

- [ ] `CHANGELOG.md` mis à jour (section `[Unreleased]`)
- [ ] `docs/BACKLOG.md` mis à jour (US concernées)
- [ ] `php artisan test` — tous les tests passent
- [ ] `npm run build` si changements frontend

---

## 4. Pull Request

- [ ] `feature/*` → `develop` (jamais directement vers `main`)
- [ ] Template RNCP rempli (OWASP / RGAA / tests / documentation)
- [ ] Pas de mention « Generated with Cursor » dans le corps
- [ ] Captures d'écran si changement visuel

---

## 5. Release

- [ ] `develop` → `main` uniquement pour une release formelle (tag `vX.Y.Z`)
- [ ] Jamais de push direct sur `main`
