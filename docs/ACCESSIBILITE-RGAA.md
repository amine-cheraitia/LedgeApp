# Accessibilite — RGAA

Ce document decrit la demarche d'accessibilite de Ledge (referentiel RGAA,
competence RNCP C2.2.3) et les mesures appliquees dans l'interface Vue / PrimeVue.

## Demarche

L'accessibilite est traitee **au fil de l'eau** dans chaque composant, pas en
correction finale. Les regles ci-dessous font partie des conventions du projet
et sont verifiees a la revue de code (checklist du template de PR).

## Mesures appliquees

### Structure et navigation
- Structure semantique : `<main>`, `<nav>`, `<header>`, `<section aria-labelledby>`.
- **Skip link** « Aller au contenu principal » sur chaque page.
- Hierarchie de titres coherente (un seul `<h1>`, pas de saut de niveau).
- Navigation clavier complete sur tous les elements interactifs.

### Focus
- `:focus-visible { outline: 2px solid; outline-offset: 2px; }` global : indicateur
  de focus visible au clavier sur tous les composants.

### Formulaires
- `<label>` associe (ou `aria-label`) sur chaque champ de saisie.
- Messages d'erreur en `role="alert"` + `aria-live` (`assertive` pour les erreurs bloquantes, annonce lecteur d'ecran).

### Composants PrimeVue
- `aria-label` sur les boutons sans texte visible (icones : editer, supprimer, export).
- `aria-label` explicite sur les `DataTable` (intitule du tableau annonce).
- Intitules de liens explicites — jamais « cliquez ici ».

### Contenu
- Contraste minimum **4.5:1** sur tous les textes (palette navy/slate validee).
- Pas de `v-html` avec des donnees utilisateur (securite XSS + accessibilite).
- Information jamais portee par la seule couleur (statuts : texte + couleur).

## Verification

### Automatisee — Lighthouse

Le score d'accessibilite se mesure sur le build reel (auth Sanctum active) :

```bash
cd frontend
npm run build
npm run preview            # http://localhost:5173 (meme proxy que le dev)
# Dans Chrome : DevTools > Lighthouse > categorie Accessibilite
```

`vite preview` reprend le port 5173 et le proxy `/api` afin que les pages
authentifiees soient mesurees en conditions reelles.

### Manuelle
- Parcours complet au **clavier seul** (Tab / Shift+Tab / Entree / Echap).
- Verification du **contraste** (DevTools ou Contrast Checker).
- Test lecteur d'ecran (NVDA / VoiceOver) sur les parcours cles (connexion,
  creation de devis, consultation portail).

## Points de vigilance

- Les tableaux de donnees volumineux doivent conserver des en-tetes de colonnes
  associes (`scope`).
- Toute nouvelle modale doit pieger le focus et etre refermable au clavier (Echap).
- Les nouveaux composants icone-seule exigent un `aria-label`.

## Reference

Referentiel : [RGAA 4](https://accessibilite.numerique.gouv.fr/) — criteres appliques
via la checklist accessibilite du template de PR (`.github/pull_request_template.md`).
