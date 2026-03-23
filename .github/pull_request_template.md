# Pull Request — Ledge

## Description
<!-- Décris clairement ce que cette PR apporte ou corrige -->



## Type de changement
- [ ] ✨ Nouvelle fonctionnalité (`feat`)
- [ ] 🐛 Correctif (`fix`)
- [ ] ♻️ Refactoring sans impact fonctionnel (`refactor`)
- [ ] 📦 Mise à jour dépendances (`chore`)
- [ ] 🧪 Tests uniquement (`test`)
- [ ] 📝 Documentation (`docs`)

## Branche source → cible
<!-- ex : feature/relances → develop -->
`___` → `___`

## Compétences RNCP couvertes
<!-- Coche les compétences démontrées dans cette PR -->
- [ ] C2.1.2 — Intégration continue (CI/CD)
- [ ] C2.2.1 — Prototype fonctionnel (MVP)
- [ ] C2.2.2 — Tests unitaires (PHPUnit)
- [ ] C2.2.3 — Sécurité OWASP / Accessibilité RGAA
- [ ] C2.3.1 — Cahier de recettes
- [ ] C4.1.1 — Mise à jour dépendances
- [ ] C4.2.2 — Déploiement correctif CI/CD
- [ ] C4.3.2 — Journal des versions (CHANGELOG)

## Checklist avant merge

### Code
- [ ] Le code respecte les conventions SOLID du projet
- [ ] Aucun `DB::raw()` avec input utilisateur (règle OWASP A03)
- [ ] Les Form Requests valident tous les inputs
- [ ] Aucun secret / credential dans le code

### Tests
- [ ] Les tests unitaires existants passent (`php artisan test`)
- [ ] Les nouveaux cas de test sont couverts
- [ ] Test manuel du flow principal effectué

### Base de données
- [ ] Migration créée si modification du schéma
- [ ] La migration est réversible (`down()` implémenté)
- [ ] Les seeders sont mis à jour si nécessaire

### Documentation
- [ ] `CHANGELOG.md` mis à jour (section `[Unreleased]`)
- [ ] Les commentaires de code sont en français
- [ ] Le `README.md` est à jour si installation/config change

### Accessibilité (si PR front-end)
- [ ] Les `<input>` ont un `<label>` ou `aria-label`
- [ ] Les messages d'erreur utilisent `role="alert"` ou `aria-live`
- [ ] La navigation clavier fonctionne sur les éléments ajoutés

## Screenshots (si applicable)
<!-- Ajoute des captures d'écran pour les changements visuels -->

## Notes pour le reviewer
<!-- Contexte technique, points d'attention, décisions prises -->

---
> PR template Ledge — RNCP 39583 · Expert en Développement Logiciel · YNOV
