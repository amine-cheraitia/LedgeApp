# CHANGELOG — Ledge

> Format : [Semantic Versioning](https://semver.org) — `MAJOR.MINOR.PATCH`
> - **MAJOR** : rupture de compatibilite (migration BDD obligatoire)
> - **MINOR** : nouvelle fonctionnalite retro-compatible
> - **PATCH** : correctif sans impact sur le schema

---

## [Unreleased]

### Correctifs — Bugs UI missions / devis / avoir (issues #20, #21, #22)

#### Frontend
- **[#20] Double modale de confirmation** : suppression du `<ConfirmDialog />` en double dans `MissionListPage.vue`, `DevisListPage.vue` et `FactureListPage.vue` — le composant global dans `AppLayout.vue` est suffisant (PrimeVue ConfirmationService est global)
- **[#21] Bouton Modifier manquant** :
  - **Missions** : ajout bouton `pi pi-pencil` + dialog pré-rempli (date_debut, date_fin, collaborateurs, notes) — réutilise `updateMission()` de `useMissions.ts`
  - **Devis** : ajout bouton `pi pi-pencil` visible uniquement sur statut `brouillon` + dialog (date_validite, notes) — ajout `updateDevis()` dans `useDevis.ts` (appelle `devisApi.update()` existant)
- **[#22] Avoir non pré-rempli** : `openAvoir()` dans `FactureListPage.vue` calcule désormais `montant_ht = montant_restant × (montant_ht / montant_ttc)` pour restituer le HT restant proportionnel

---

### Ajouts — Dashboard KPI (US-33)

#### Backend
- **`DashboardController::stats()`** : enrichi avec filtre `exercice_id`, CA du mois, TVA collectée, taux de recouvrement, alertes dynamiques (factures en retard, taux faible)
- **`routes/api.php`** : route `/stats` déplacée dans le groupe `backoffice` (clients bloqués — 403)
- **`SettingsSeeder`** : ajout clé `seuil_alerte_recouvrement` (défaut 70%)
- **Tests** : 9 tests `DashboardKpiTest` — structure KPI, CA mois, TVA, taux recouvrement, filtre exercice, alertes retard/faible, no-alert si soldé, accès client interdit, 401 non authentifié

#### Frontend
- **`api/modules/stats.ts`** : `getDashboard(exerciceId?)` + type `DashboardStats` enrichi (`kpi`, `alertes`, `exercices`)
- **`pages/dashboard/DashboardPage.vue`** : filtre exercice, 3 widgets KPI (CA mois, TVA collectée, taux recouvrement avec barre de progression colorée), bannière d'alertes (`Message` PrimeVue)

---

### Ajouts — Portail : mes factures + mes missions (US-30, US-31)

#### Backend
- **`PortailFactureController`** : `GET /api/v1/portail/factures` (liste scoped `entreprise_id`, filtres exercice + statut), `GET /api/v1/portail/factures/{id}/pdf` (PDF sécurisé — 403 si hors scope)
- **`PortailMissionController`** : `GET /api/v1/portail/missions` (liste scoped, filtre statut), `GET /api/v1/portail/missions/{id}` (détail avec tâches, sans commentaires internes)
- **Routes** : 4 routes dans le groupe `middleware('portail')`
- **Tests** : 6 tests `PortailFactureTest` + 7 tests `PortailMissionTest` — scope isolation, filtres, PDF 403 hors scope, tâches sans commentaires, accès staff interdit

#### Frontend
- **`api/modules/portail.ts`** : `portailApi.getFactures()`, `telechargerFacturePdf()`, `getMissions()`, `getMission()`
- **`pages/portail/PortailFacturesPage.vue`** : tableau factures (numéro, date, échéance, TTC, restant dû en rouge, statut) + filtre statut + téléchargement PDF
- **`pages/portail/PortailMissionsPage.vue`** : tableau missions (référence, prestation, statut, barre d'avancement %) + dialog détail avec tâches (sans commentaires internes) — lecture seule stricte
- **`router/index.ts`** : routes `/portail/factures` et `/portail/missions`

---

### Ajouts — Portail client accès (US-29)

#### Backend
- **`PortailController::me()`** : endpoint `GET /api/v1/portail/me` — retourne le profil du client avec son entreprise (scope isolation garantie par le middleware)
- **Route** `GET /api/v1/portail/me` dans le groupe `middleware('portail')` — protégé par `EnsurePortailAccess`
- **Tests** : 11 tests `PortailAccessTest` — activation portail (création user client + mot de passe temporaire), 422 si prospect, 409 si portail déjà activé, toggle activate/désactivation, middleware bloque staff (403), middleware bloque client inactif (403), middleware autorise client actif, retour entreprise dans `/portail/me`, accès non authentifié (401)

#### Frontend
- **`layout/PortailLayout.vue`** : layout dédié portail — topbar avec logo, raison sociale entreprise, nav (Accueil / Mes factures / Mes missions), nom utilisateur, bouton déconnexion ; pas de sidebar backoffice ; footer cabinet ; responsive mobile-first ; RGAA (skip-link, aria-label, focus-visible)
- **`router/index.ts`** : route `/portail` branchée sur `PortailLayout` au lieu de `AppLayout`
- **`pages/portail/PortailDashboard.vue`** : page d'accueil portail — message de bienvenue avec nom client + raison sociale, cards accès rapide (Mes factures / Mes missions), bloc informations entreprise (régime fiscal, catégorie, NIF, ville)

---

### Ajouts — Avoir sur facture (US-16)

#### Backend
- **Migration** `create_avoirs_table` : table `avoirs` — `facture_origine_id`, `exercice_id`, `created_by`, `numero` (FA{ANNEE}-{NNN}), `date_avoir`, `montant_ht`, `taux_tva_snapshot`, `montant_tva`, `montant_ttc`, `motif`
- **`Avoir`** : modèle Eloquent avec relations `factureOrigine`, `exercice`, `createdBy`
- **`Facture::montantRestant()`** : mise à jour — soustrait désormais le total des avoirs TTC (`max(0, ttc - paye - avoirs)`)
- **`FacturationService::creerAvoir()`** : création d'avoir avec reprise du taux TVA snapshot de la facture d'origine, validation montant ≤ restant dû, numérotation séquentielle FA{ANNEE}-{NNN}
- **`PdfService::genererAvoir()`** : génération PDF DomPDF — design violet distinct de la facture, header "Avoir / Note de crédit", référence facture origine, récapitulatif financier, montant en lettres, motif
- **`AvoirController`** : `GET /factures/{id}/avoirs` (liste), `POST /factures/{id}/avoirs` (création), `GET /factures/{id}/avoirs/{avoir}/pdf` (PDF)
- **`StoreAvoirRequest`** : validation `montant_ht`, `date_avoir`, `motif`
- **`AvoirResource`** : resource JSON avec tous les champs
- **`FactureResource`** : ajout de la relation `avoirs` (chargée conditionnellement)
- **`routes/api.php`** : 3 routes avoirs nestées sous `/factures/{id}/avoirs`
- **`resources/views/pdf/avoir.blade.php`** : vue PDF avoir — couleur violette, récap financier, motif, signatures
- **Tests** : 9 tests `AvoirApiTest` — liste, création, TVA snapshot, numérotation séquentielle, montant > restant (409), réduction montant restant, validation montant/motif, accès non authentifié (62 tests en tout, 157 assertions)

#### Frontend
- **`types/index.ts`** : interface `Avoir` ajoutée, `Facture` enrichi avec `avoirs?`
- **`api/modules/avoirs.ts`** : `avoirsApi.index()`, `avoirsApi.store()`, `avoirsApi.telechargerPdf()`
- **`pages/factures/FactureListPage.vue`** : bouton "Émettre un avoir" dans les actions, dialog avec récap restant dû, saisie montant HT + date + motif, toast succès/erreur

---

### Ajouts — Relances et créances impayées (US-25, US-26, US-27, US-28)

#### Backend
- **Migration** `add_annulee_to_relances_statut` : ajout du statut `annulee` à l'enum `relances.statut` (nécessaire pour `CancelRelancesOnPayment`)
- **`RelanceService`** : logique métier centralisée — `envoyerManuelle()` (niveau choisi, mail immédiat), `envoyerAutomatique()` (vérification doublon + séquentialité des niveaux), résolution des templates depuis `settings` avec remplacement des variables `{{client}}`, `{{montant}}`, `{{numero_facture}}`, `{{echeance}}`
- **`RelanceController`** : `GET /api/v1/factures/{id}/relances` (historique), `POST /api/v1/factures/{id}/relances` (relance manuelle)
- **`CreanceController`** : `GET /api/v1/creances` — liste des factures `en_attente` ou `partiel` triées par ancienneté d'échéance
- **`FactureResource`** : ajout du champ `montant_restant` (calculé) et `relances` (relation conditionnelle)
- **`RelanceResource`** : resource JSON dédiée (niveau, type, statut, email, envoyee_le, sentBy)
- **`RelanceClientMail`** + `resources/views/mail/relance.blade.php` : email HTML avec badge niveau (rappel / relance ferme / mise en demeure), récapitulatif financier, style responsive
- **`EnvoyerRelancesJob`** : job quotidien (`ShouldQueue`) — sélectionne les factures échues, détermine le niveau attendu selon les délais settings, envoie uniquement si le niveau précédent a déjà été traité
- **`routes/console.php`** : scheduler — `EnvoyerRelancesJob` déclenché chaque jour à 8h00
- **`SettingsSeeder`** : ajout des 3 templates mail par défaut (`relance_template_n1/n2/n3`)
- **`resend/resend-laravel`** : package installé — driver `resend` pour envoi email en production
- **Tests** : 9 tests `RelanceApiTest` — liste créances, exclusion soldées, inclusion partielles, relance manuelle, blocage sur facture soldée, validation niveau, historique, anti-doublon automatique, blocage niveau2 sans niveau1

#### Frontend
- **`api/modules/relances.ts`** : `relancesApi.indexParFacture()` + `relancesApi.store()`
- **`api/modules/creances.ts`** : `creancesApi.index()`
- **`types/index.ts`** : interface `Relance` ajoutée, `Facture` enrichi avec `montant_restant` et `relances?`
- **`pages/relances/CreancesPage.vue`** : tableau créances impayées avec retard en jours, montant restant dû, badge statut, bouton relance manuelle avec dialog de sélection du niveau
- **`pages/relances/RelancesConfigPage.vue`** : configuration des délais (J+15/30/45) et templates des 3 niveaux avec variables disponibles, sauvegarde via `settingsApi`
- **`router/index.ts`** : routes `/creances` et `/relances/config`
- **`AppMenu.vue`** : section Facturation enrichie — liens Créances et Relances

---

### Corrections — PDF facture : en-tête, désignation, TypeError

- **`FactureController::pdf()`** : type de retour corrigé `StreamedResponse` → `Illuminate\Http\Response` — DomPDF `.stream()` / `.download()` retourne une `Response` standard
- **`pdf/facture.blade.php` + `pdf/devis.blade.php`** : logo `[L] Ledge` en haut à gauche + nom du cabinet en grand — adresse, téléphone et coordonnées du cabinet retirés de l'en-tête (conservés dans le footer)
- **`pdf/facture.blade.php`** : désignation de la ligne = nom de la prestation de la mission + pourcentage de tranche calculé depuis les montants réels (`ligne.total_ht / mission.prix_ht`) — indépendant du snapshot `designation` stocké en base

---

### Ajouts — Facturation : création facture avec tranches automatiques (US-13)

#### Backend
- **Migration** : `add_mode_paiement_to_factures_table` — colonne `mode_paiement` (default `non_defini`) + `make_timbre_taux_id_nullable_on_factures`
- **`FacturationService::creerFacture()`** : entièrement réécrit — détection automatique de la tranche (T1 30%, T2 30%, T3 40%) par comptage des factures `FF` de la mission, `lockForUpdate()` pour éviter les doublons en concurrence, snapshot TVA historisée via `TvaTaux::enVigueurLe($dateFacture)`, date échéance auto = date_facture + 45 jours, `montant_timbre = 0` (timbre ignoré), `mode_paiement = non_defini` à la création
- **`FacturationService::creerFacture()`** : lève `DomainException` si ≥ 3 factures existent déjà pour la mission
- **`PaiementController`** : met à jour `mode_paiement` sur la facture lors de l'enregistrement d'un paiement
- **`PdfService::genererFacture()`** : génère le PDF de facture (template A4 identique au devis, sans timbre, avec mention mode de règlement)
- **`FactureController::pdf()`** : route `GET /api/v1/factures/{id}/pdf`
- **`StoreFactureRequest`** : simplifié — `mission_id` + `date_facture` + `notes` uniquement
- **`StorePaiementRequest`** : `mode_paiement` accepte `virement`, `cheque`, `autre`
- **`FactureResource`** : expose `mode_paiement`
- **Vue Blade `pdf/facture.blade.php`** : template A4 — en-tête cabinet, bloc destinataire, tableau prestation (tranche label, HT, TVA, TTC), totaux, montant en lettres, mode de règlement (RIB si virement), signatures « Bon pour acquit »
- **Tests** : 8 tests `FactureApiTest` — tranche1, tranches T1/T2/T3, erreur >3 tranches, snapshot TVA, paiement partiel, solde, suppression bloquée avec paiements, paiement sur facture soldée bloqué

#### Frontend
- **`api/modules/factures.ts`** : `FacturePayload` simplifié (`mission_id`, `date_facture`, `notes`), ajout `getPdf()` avec `responseType: blob`, `PaiementPayload` avec `autre` comme 3e mode
- **`types/index.ts`** : `Facture` enrichi avec `mode_paiement`
- **`composables/useFactures.ts`** : ajout `telechargerPdf(id, numero)` — pattern blob download
- **`pages/factures/FactureListPage.vue`** : formulaire basé sur mission (plus entreprise), date facture par défaut aujourd'hui, aperçu date échéance (+45j, non modifiable), hint tranche suivante, dialog paiement avec 3 modes, bouton PDF sur chaque ligne

---

### Ajouts — Missions : tâches complètes + statuts mission (US-19)

#### Backend
- **`UpdateTacheRequest`** (nouveau) : FormRequest dédié à la mise à jour des tâches — `titre` en `sometimes` (optionnel) permettant la mise à jour partielle du statut seul sans re-valider tous les champs
- **`TacheController::update()`** : injecte désormais `UpdateTacheRequest` au lieu de `StoreTacheRequest` — SRP respecté (store et update ont des règles différentes)

#### Frontend
- **`MissionListPage.vue`** : ajout `MultiSelect` collaborateurs dans le dialog de création de mission
- **`MissionDetailPage.vue`** : refonte complète —
  - Boutons de changement de statut mission (Reprendre / Suspendre / Terminer / Annuler) selon le statut courant
  - Collaborateurs affichés en chips dans le bloc info
  - Sélecteur `assigné à` (Select user) dans le dialog nouvelle tâche
  - Bouton supprimer tâche avec `ConfirmDialog` + gestion erreur 409 (tâche avec commentaires)
  - Cohérence des tags de statut tâche

---

### Corrections — PDF devis : timbre retiré + montant en lettres

#### Backend
- **`FacturationService::creerDevis()`** : `montant_timbre` forcé à 0 — le timbre fiscal s'applique uniquement sur les factures, pas sur les devis
- **`PdfService::montantEnLettres()`** : nouvelle méthode — convertit un montant float en lettres françaises via `NumberFormatter` (PHP intl, locale `fr`), suffixe « Dinars Algériens »
- **Test** `test_tva_calcule_sur_prix_ht_sans_timbre` : assertions mises à jour (`montant_timbre = 0`, TTC = HT + TVA)

#### Frontend (PDF)
- **`pdf/devis.blade.php`** : colonne Timbre et ligne "Timbre fiscal" supprimées du tableau et des totaux
- Bandeau *Arrêté le présent devis à la somme de **[montant en lettres]*** ajouté sous le bloc TOTAL TTC

---

### Ajouts — PDF devis (US-12)

#### Backend
- **PdfService** : service dédié DomPDF — `genererDevis()` charge les données cabinet (`Setting`) et rend la vue Blade ; conçu pour accueillir `genererFacture()` (US-14)
- **Vue Blade `pdf/devis.blade.php`** : template A4 portrait — en-tête cabinet (nom/NIF/NIS/RIB/adresse/tél), bloc destinataire (raison sociale/NIF/NIS/RC), tableau prestation (code, désignation, prix HT, TVA, timbre), récapitulatif totaux, zone double signature avec mention « Bon pour accord »
- **Route** : `GET /api/v1/devis/{id}/pdf` — streame le PDF directement au client
- **DevisController::pdf()** : délègue à `PdfService`, retourne un stream `application/pdf`
- **Test** : `test_pdf_devis_retourne_un_pdf` — vérifie HTTP 200 + Content-Type `application/pdf`

#### Frontend
- **`api/modules/devis.ts`** : ajout `getPdf(id)` avec `responseType: 'blob'`
- **`composables/useDevis.ts`** : ajout `telechargerPdf(id, numero)` — crée un object URL, déclenche le téléchargement, révoque l'URL
- **`pages/devis/DevisListPage.vue`** : bouton PDF (icône `pi-file-pdf`) visible pour tous les statuts sauf `brouillon`

### Refactoring SOLID — Devis workflow

#### Backend
- **SRP** : logique de transition d'état déplacée du controller vers `FacturationService` — nouvelles méthodes `envoyerDevis()`, `accepterDevis()`, `refuserDevis()`, `supprimerDevis()`, `mettreAJourDevis()` ; chaque méthode lève `DomainException` si la transition est invalide
- **FormRequests** : ajout `UpdateDevisRequest` (notes, date_validite) et `ConvertirEnMissionRequest` (date_debut, date_fin, collaborateur_ids) — suppression des `$request->validate()` inline dans `update()` et `convertirEnMission()`
- **DRY** : `MissionService::genererReference()` supprimée — délègue désormais à `FacturationService::genererNumero()` via le nouveau paramètre optionnel `$colonne` (défaut `'numero'`)
- **DevisController** : réduit à validation + délégation + retour Resource ; gestion HTTP 409 centralisée par `catch (DomainException)`

### Corrections — Devis : une prestation unique (US-11)

#### Backend
- **Migration** : suppression `devis_lignes`, ajout `prestation_id` + `prix_ht` sur `devis`
- **FacturationService::creerDevis()** : calcul automatique `tarif × indice_regime × indice_categorie` — prix saisi manuellement supprimé
- **StoreDevisRequest** : `prestation_id` requis, suppression `lignes[]`
- **DevisResource** : expose `prestation` et `prix_ht` au lieu des lignes
- **Tests** : 7 tests DevisApiTest réécrits (grille tarifaire, TVA, timbre, validation)

#### Frontend
- **`api/modules/devis.ts`** : `DevisPayload` passe à `prestation_id`, suppression `DevisLignePayload`
- **`types/index.ts`** : suppression `DevisLigne`, `Devis` aligné sur le backend
- **`pages/devis/DevisListPage.vue`** : formulaire — section lignes remplacée par `Select` prestation

### Ajouts — Portail client + Dashboard KPI + Refonte design

#### Backend — Portail client (US-29)
- **PortailService** : activation accès portail depuis fiche entreprise — création user `client` avec mot de passe temporaire, toggle actif/inactif
- **EntrepriseController** : méthodes `activerPortail` et `togglePortail`
- **EntrepriseResource** : exposition `portail_user` (id, email, portail_actif) via `whenLoaded`
- Routes : `POST /api/v1/entreprises/{id}/activer-portail`, `POST /api/v1/entreprises/{id}/toggle-portail`

#### Backend — Dashboard KPI (US-33 partiel)
- **DashboardController** : endpoint `GET /api/v1/stats` — compteurs entreprises/missions/factures/devis, CA HT/TTC, impayés, en retard, 5 derniers éléments

#### Frontend — Refonte design
- Nouveau système de layout PrimeVue 4 : `AppLayout`, `AppTopbar`, `AppSidebar`, `AppMenu`, `AppMenuItem`, `AppFooter`, `AppConfigurator`
- `FloatingConfigurator` : switch thème Aura/Lara en live
- Styles globaux refaits : `layout.scss`, `tailwind.css`
- Module API `stats.ts` pour le dashboard
- Pages mises à jour : `LoginPage`, `DashboardPage`, `EntrepriseListPage`

### A faire
- Module Relances (automatiques via queue + manuelles)
- Portail client (lecture seule factures/documents)
- Module KPI / Reporting (CA, missions, performance collaborateurs)
- Module Documents / GED
- PDF facture conforme DGI (US-14) — `PdfService::genererFacture()` + montant en lettres
- Avoirs (FA) — creation depuis facture existante

---

## [0.5.0] — 2026-03-25

### Ajouts — Module Missions + Taches

#### Backend — MissionService + Controllers

- **MissionService** — couche metier centralisee :
  - `creerMission()` : calcul automatique du prix HT (`prestation.tarif_initial x regime_fiscal.indice x categorie.indice`),
    generation de la reference sequentielle (M2026-001), creation mission + rattachement collaborateurs (pivot `mission_user`)
  - `updateMission()` : modification statut/dates/notes/collaborateurs. Prix HT immuable apres creation
  - `deleteMission()` : suppression bloquee si factures associees (HTTP 409)
- **MissionController** — CRUD complet avec filtres (entreprise, statut), eager loading relations
- **TacheController** — CRUD nested sous `/missions/{id}/taches`, changement statut inline
- **Conversion Devis → Mission** — `POST /devis/{id}/convertir-en-mission` : cree une mission depuis un devis accepte/envoye
- **FormRequests** : `StoreMissionRequest`, `UpdateMissionRequest`, `StoreTacheRequest`
- **Resources** : `MissionResource` (avec entreprise, prestation, taches, factures), `TacheResource`

#### Frontend — Pages Missions

- **API modules** : `missions.ts`, `taches.ts` — CRUD complet
- **Composables** : `useMissions.ts`, `useTaches.ts` — logique reactive
- **MissionListPage** — DataTable (reference, entreprise, prestation, prix HT, statut) + Dialog creation (select entreprise/prestation)
- **MissionDetailPage** — detail mission, tranches suggerees (30/30/40), section taches avec statut inline, factures liees
- **Router** : routes `/missions` et `/missions/:id`
- **Sidebar** : ajout item Missions (pi-briefcase) dans la navigation

#### Tests

- **MissionApiTest** (7 tests) : prix HT calcule, bascule prospect→client, CRUD, reference sequentielle, protection suppression
- **TacheApiTest** (3 tests) : creation, liste, mise a jour statut
- **Frontend** (9 tests) : modules API missions + taches

### Corrections

- **fix(auth)** : roles Spatie retournes comme objets `[{name: 'admin', ...}]` — normalisation en strings `['admin']` pour que `isAdmin` fonctionne dans le sidebar
- **fix(routes)** : parametre route taches `{tach}` → `{tache}` (singularisation Laravel incorrecte pour le francais)
- **fix(model)** : ajout `$attributes` defaults sur Tache (statut, priorite) pour coherence modele/BDD

---

## [0.4.0] — 2026-03-25

### Ajouts — Sprint 1 Facturation

#### Backend — Service Layer Facturation

- **FacturationService** — couche metier centralisee :
  - `genererNumero()` : numerotation sequentielle par exercice avec `lockForUpdate()` pour eviter
    les doublons en concurrence (format `{prefixe}{annee}-{sequence}`, ex: FF2026-001)
  - `creerDevis()` : creation devis + lignes, calcul automatique TVA/timbre depuis la date du devis
  - `creerFacture()` : creation facture + lignes avec **snapshots immuables** (taux_tva, montant_tva,
    montant_timbre, montant_ttc copies a la creation et jamais recalcules)
  - `recalculerStatutPaiement()` : mise a jour automatique du statut facture
    (en_attente → partiel → solde) selon la somme des paiements enregistres

- **Controllers REST** (dans `Http/Controllers/Facturation/`) :
  - `DevisController` — CRUD complet. Update/delete limites aux devis en statut `brouillon`
  - `FactureController` — Create/Read/Delete. Suppression bloquee si paiements associes (HTTP 409)
  - `PaiementController` — Create/Read/Delete, nested sous `/factures/{id}/paiements`.
    Enregistrement bloque si facture deja soldee (HTTP 409)

- **FormRequests** — validation stricte des inputs :
  - `StoreDevisRequest` : entreprise_id (exists), dates, lignes[].designation/quantite/prix_unitaire_ht
  - `StoreFactureRequest` : entreprise_id, type (in:FF,FA), dates, lignes
  - `StorePaiementRequest` : montant (integer >0), date_paiement, mode_paiement (in:virement,cheque,espece,autre)

- **API Resources** — serialisation JSON coherente :
  - DevisResource + DevisLigneResource (avec relations lignes incluses)
  - FactureResource + FactureLigneResource (avec snapshots TVA/timbre)
  - PaiementResource

- **Events / Listeners** — decouplage metier via evenements Laravel :
  - `MissionCreated` → `ConvertProspectToClient` : bascule automatique entreprise prospect → client
    a la creation de la premiere mission (via MissionObserver)
  - `InvoicePaid` → `CancelRelancesOnPayment` : annulation automatique des relances en cours
    quand une facture passe au statut solde

- **Routes API** ajoutees dans `routes/api.php` :
  - `GET|POST /api/v1/devis`, `GET|PUT|DELETE /api/v1/devis/{id}`
  - `GET|POST /api/v1/factures`, `GET|DELETE /api/v1/factures/{id}`
  - `GET|POST /api/v1/factures/{facture}/paiements`, `DELETE /api/v1/factures/{facture}/paiements/{paiement}`

- **Protection suppression** renforcee :
  - EntrepriseController : retourne 409 si devis ou missions associes

#### Frontend — Composables + API Modules

- **API Modules** (`api/modules/`) — couche d'abstraction HTTP, jamais d'appel Axios direct
  dans les composants :
  - `entreprises.ts` : getAll (pagine + search), getOne, create, update, delete
  - `exercices.ts` : getAll, getCurrent, create, update, delete
  - `prestations.ts` : getAll, calculerPrix (appel formule backend)
  - `users.ts` : getAll (pagine), getOne, create, update, delete
  - `settings.ts` : getAll, update (batch cle/valeur)
  - `devis.ts` : getAll (pagine), getOne, create, update, delete + DevisPayload/DevisLignePayload types
  - `factures.ts` : getAll (pagine), getOne, create, delete + createPaiement, getPaiements, deletePaiement

- **Composables** (`composables/`) — logique reactive reutilisable, separee des composants Vue :
  - `useEntreprises` : liste reactive, pagination, search, CRUD avec refresh auto
  - `useUsers` : idem pour utilisateurs
  - `useExercices` : liste + CRUD exercices fiscaux
  - `usePrestations` : liste + calcul prix HT
  - `useSettings` : liste + sauvegarde batch
  - `useDevis` : liste paginee, CRUD, updateStatut (brouillon → envoye → accepte/refuse)
  - `useFactures` : liste paginee, CRUD, addPaiement avec refresh statut

- **Types TypeScript** enrichis (`types/index.ts`) :
  - `Mission` : id, entreprise_id, exercice_id, prestation_id, numero, prix_ht, statut, dates
  - `DevisLigne` / `Devis` : lignes avec designation/quantite/prix, statut (brouillon→envoye→accepte→refuse→expire)
  - `FactureLigne` / `Facture` : snapshots taux_tva/montant_tva/montant_timbre/montant_ttc, statut_paiement
  - `Paiement` : montant, date, mode_paiement (virement|cheque|espece|autre), reference

- **Pages refactorisees** avec composables + CRUD complet via PrimeVue Dialog :
  - `EntrepriseListPage` : DataTable + Dialog creation/edition + ConfirmDialog suppression + Select regime/categorie/statut
  - `UserListPage` : DataTable + Dialog avec Password component + Select role
  - `ExerciceListPage` : DataTable + Dialog avec DatePicker
  - `PrestationListPage` : refactorisee avec usePrestations (lecture seule)
  - `SettingsPage` : refactorisee avec useSettings

- **Nouvelles pages** :
  - `DevisListPage` : creation avec lignes dynamiques (ajout/suppression), Select entreprise avec filtre,
    actions statut (envoyer), suppression limitee aux brouillons
  - `FactureListPage` : creation avec lignes + dialog paiement separe (montant, date, mode, reference),
    affichage montant restant du

- **Router** : routes `/devis` et `/factures` ajoutees avec meta backoffice
- **AdminLayout** : menu items Devis (pi-file) et Factures (pi-receipt) ajoutes dans la sidebar

#### Tests

- **Backend PHPUnit** — 28 tests, 59 assertions, tous passent sur SQLite :memory: :
  - `EntrepriseApiTest` (6 tests) : list pagine, create, update, delete protege si missions/devis, delete OK, search
  - `DevisApiTest` (5 tests) : create avec lignes, calcul TVA+timbre, list, delete protege si non-brouillon, numerotation sequentielle
  - `FactureApiTest` (6 tests) : create, snapshot TVA immuable, paiement → statut partiel, paiement complet → solde,
    delete protege si paiements, impossible de payer une facture soldee
  - `TvaRateTest` (3 tests) : taux correct par date, null avant tout taux, accepte string date
  - `TimbreRateTest` (3 tests) : calcul avec plafond, taux correct, null avant tout taux
  - `PrestationTest` (1 test) : prix_ht = tarif × indice_regime × indice_categorie
  - `ConvertProspectToClientTest` (2 tests) : prospect→client a la mission, client reste client
  - Tests Filament V1 supprimes (obsoletes depuis migration N-tier)

- **Frontend Vitest** — 18 tests, tous passent :
  - `types.test.ts` (5 tests) : validation interfaces Entreprise, Facture, Paiement, Devis, PaginatedResponse
  - `api-modules.test.ts` (13 tests) : mock Axios via `vi.hoisted()`, verification des appels HTTP corrects
    pour entreprises, exercices, devis, factures, settings

#### CI/CD

- **GitHub Actions** (`.github/workflows/ci.yml`) — pipeline declenchee sur push/PR vers main et develop :
  - Job `backend` : PHP 8.2, Composer cache, Laravel Pint (lint), PHPUnit sur SQLite :memory:
  - Job `frontend` : Node 20, npm ci, Vitest, vue-tsc (type check), Vite build

---

## [0.3.0] — 2026-03-23

### Correctifs
- fix(frontend): dark mode Aura — correction variables couleurs layout + fix CSRF proxy

---

## [0.2.0] — 2026-03-23

### Ajouts — Frontend Vue 3 + Pages CRUD

#### Frontend Setup
- Vue 3 + TypeScript + Vite + PrimeVue 4 (theme Aura) + Pinia + Vue Router
- Client Axios avec intercepteurs CSRF Sanctum
- Layouts AdminLayout (sidebar desktop / hamburger mobile) + PortailLayout
- Router avec guards auth + role (backoffice vs portail)
- Pinia store auth (login/logout/me)
- CSS mobile-first + skip-link RGAA + focus-visible

#### Pages CRUD
- LoginPage — authentification Sanctum SPA
- DashboardPage — page d'accueil admin (contenu a venir)
- UserListPage — liste + creation/edition utilisateurs avec roles
- EntrepriseListPage — CRUD entreprises (raison sociale, NIF, regime fiscal, categorie)
- ExerciceListPage — CRUD exercices fiscaux
- PrestationListPage — CRUD prestations (catalogue tarifaire)
- SettingsPage — parametres globaux cle/valeur
- PortailDashboard — page portail client (contenu a venir)

---

## [0.1.0] — 2026-03-16

### Ajouts — Backend Laravel API

#### Infrastructure & Configuration
- Laravel 12 (PHP 8.3) + Sanctum v4 (SPA cookie-based) + Spatie Permission v7.2 + DomPDF + Spatie Health
- MySQL 9.1 — fix `ROW_FORMAT=DYNAMIC` pour utf8mb4 (WAMP)
- `Schema::defaultStringLength(191)` dans `AppServiceProvider`
- Middleware `EnsureBackofficeAccess` — bloque les clients sur le back-office
- Middleware `EnsurePortailAccess` — bloque les non-clients, verifie `portail_actif`
- CORS configure pour `localhost:5173`

#### API REST
- AuthController — login/logout/me (Sanctum SPA)
- UserController — CRUD utilisateurs + assignation roles Spatie
- EntrepriseController — CRUD entreprises
- ExerciceController — CRUD exercices fiscaux
- PrestationController — CRUD prestations
- SettingController — CRUD parametres cle/valeur
- FormRequests de validation sur tous les endpoints
- API Resources JSON par domaine

#### Base de donnees — 17 migrations
- `users` + `users.entreprise_id` (nullable FK) + `users.portail_actif`
- `entreprises` — clients & prospects avec regime fiscal et categorie
- `exercices` — exercices fiscaux par annee avec statut `ouvert/cloture`
- `tva_rates` + `timbre_rates` — historique taux avec `date_debut`/`date_fin`
- `settings` — parametres cle/valeur par groupe
- `prestations` + `regimes_fiscaux` + `categories_entreprise` — grille tarifaire
- `missions` + `mission_user` — missions et affectation collaborateurs
- `taches` + `tache_commentaires` — planning et commentaires
- `devis` + `devis_lignes` — gestion des devis
- `factures` + `facture_lignes` — facturation FF/FA avec snapshot TVA
- `paiements` — suivi des encaissements
- `relances` — journal des relances
- `documents` — stockage et partage portail

#### Donnees initiales seedees
- 4 roles : `admin`, `collaborateur`, `secretaire`, `client`
- Compte admin : `admin@ledge.dz` / `password`
- TVA 19% (standard) + 9% (reduit) — LF 2023
- Timbre fiscal 1% plafonne 2 500 DA — LF 2024
- 5 prestations reelles (CAC, ACMPT, AENT, ASSC, A&C)
- Indices regimes fiscaux (Forfait x1.0, Reel x1.5) et categories (TPE x1.0, PME x1.75, GE x2.0)
- Exercice fiscal 2026 ouvert
- 12 parametres `settings` initiaux

#### Modeles Eloquent (18)
- Relations, casts et scopes configures
- `TvaRate::enVigueurLe($date)` + `TimbreRate::enVigueurLe($date)` — resolution historique
- `Prestation::calculerPrixHt($regime, $categorie)` — formule grille tarifaire
- `Exercice::current()` — exercice ouvert de l'annee en cours
- `User::canAccessPanel()` — controle d'acces par panel (backoffice vs portail)

---

## Branches actives

| Branche | Objectif | Statut |
|---|---|---|
| `main` | Production stable | init seulement |
| `develop` | Integration continue | actif |
| `feature/backend-setup` | Laravel API scaffold | merge |
| `feature/auth-api` | Auth Sanctum + Users | merge |
| `feature/core-api` | Controllers CRUD API | merge |
| `feature/frontend-setup` | Vue 3 + PrimeVue + Layout | merge |
| `feature/core-pages` | Pages CRUD frontend | merge |
| `fix/dark-mode-colors` | Fix theme dark mode | merge |
| `feature/facturation` | Sprint 1 — devis, factures, paiements, CI | en cours |

---

## Convention de commits

```
feat(module): description courte
fix(module): description du correctif
chore(deps): mise a jour dependance X
test(module): ajout tests unitaires
docs(changelog): mise a jour journal
refactor(module): refactoring sans changement fonctionnel
```

### Exemples Ledge
```
feat(facturation): calcul automatique TVA + timbre fiscal avec snapshot
feat(portail): activation acces client depuis fiche entreprise
feat(relances): relance automatique J+15 via queue Laravel
fix(factures): snapshot tva_taux_id manquant a la creation d'avoir
fix(exercices): numerotation annuelle ne se reinitialisait pas
chore(deps): mise a jour Laravel 12.x.0
test(facturation): FormuleHTPrixServiceTest — 12 cas couverts
docs(changelog): v0.1.0 — setup initial + migrations + seeders
```
