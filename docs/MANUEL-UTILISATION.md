# Manuel d'utilisation — Ledge

Guide fonctionnel par role. Pour installer / lancer l'application, voir
[MANUEL-DEPLOIEMENT.md](MANUEL-DEPLOIEMENT.md).

## Les roles

| Role | Espace | Perimetre |
|---|---|---|
| **Admin** | `/admin` | Acces complet : parametres, utilisateurs, facturation, planning |
| **Collaborateur** | `/admin` | Ses missions et ses taches uniquement |
| **Secretaire** | `/admin` | Entreprises (sans suppression), recouvrement, envoi de documents. **Pas** de Missions ni Planning |
| **Client** | `/portail` | Lecture seule de ses propres donnees |

Le **client ne s'inscrit jamais lui-meme** : l'admin active son acces depuis la
fiche entreprise, et le client definit son mot de passe via un lien d'invitation.

---

## Premiere connexion

1. Ouvrir http://localhost:5173.
2. Se connecter avec un compte existant (admin en demo : voir manuel de deploiement).
3. Le menu s'adapte automatiquement au role.

Mot de passe oublie : lien **« Mot de passe oublie »** -> saisie de l'email ->
reception d'un lien de reinitialisation (en demo, lien visible dans les logs).

---

## Parcours administrateur

### Configurer le cabinet
- **Parametres** : coordonnees, prefixes de numerotation, TVA.
- **Exercices** : ouvrir l'exercice courant. Un exercice cloture peut etre
  **rouvert** par l'admin pour rattraper une facturation oubliee.
- **Prestations** : grille tarifaire (tarif de base par prestation).
- **Utilisateurs** : creer collaborateurs / secretaires -> ils recoivent une
  **invitation** pour definir leur mot de passe (aucun mot de passe n'est saisi
  ni transmis par l'admin).

### Cycle commercial
1. **Entreprise** : creer une fiche (statut *prospect* par defaut).
2. **Devis** : creer un devis avec ses lignes -> l'envoyer.
3. **Mission** : convertir le devis en mission. L'entreprise bascule
   automatiquement *prospect -> client*. Le prix HT est calcule une fois et fige.
4. **Taches** : decouper la mission, assigner des collaborateurs, suivre l'avancement.
5. **Facturation** : emettre les factures (par tranches 30 / 30 / 40 %). La TVA
   appliquee est celle **en vigueur a la date de facture** et reste figee.
6. **Paiements** : enregistrer les reglements -> le statut passe
   `en_attente -> partiel -> solde` automatiquement.
7. **Avoirs** : emettre un avoir (FA) rattache a une facture si necessaire.

### Planning
Calendrier des missions et des taches (4 vues : Annee, Mois, Semaine, Liste),
accessible a l'admin et au collaborateur -> **pas d'acces pour la secretaire**
(cf. tableau des roles).

- **Cote admin**, deux onglets :
  - **Missions** : calendrier de toutes les missions, filtrable par statut
    (En cours / Suspendue / Terminee / Annulee), couleur par prestation et
    bordure par statut. Une mission peut etre deplacee ou redimensionnee
    directement dans le calendrier (glisser-deposer) pour changer ses dates.
    Un clic ouvre le detail (entreprise, prestation, periode, prix HT) avec
    possibilite de changer le statut.
  - **Equipe** : grille hebdomadaire de disponibilite par collaborateur
    (navigation semaine par semaine), avec un indicateur de charge
    (Disponible / Modere / Charge selon le nombre de taches du jour) et
    acces au detail d'une tache depuis sa vignette.
- **Cote collaborateur** : calendrier limite a **ses** taches uniquement,
  en lecture seule (pas de glisser-deposer), colore par priorite.
- A la creation ou la modification d'une tache, le systeme signale -- a
  titre indicatif, non bloquant -- si le collaborateur assigne a deja une
  autre tache sur la meme periode.

### Portail client
Depuis la fiche entreprise (statut *client*) -> **« Activer l'acces portail »** :
- cree le compte client (role `client`, rattache a l'entreprise) ;
- envoie une **invitation** ; en repli, un **lien copiable** est affiche a l'admin ;
- `portail_actif = 1` pour activer, `0` pour revoquer.

### Statistiques
Page reservee a l'admin, avec un filtre d'exercice global (tous les
exercices ou un exercice precis) partage par les deux onglets.

- Onglet **Cabinet** : agregats globaux sur l'exercice filtre --
  - top entreprises contributrices (CA HT net, avoirs deduits, les 8
    premieres) ;
  - repartition des missions par prestation ;
  - missions par etat (En cours / Terminee / Suspendue / Annulee) ;
  - creances : total impaye, anciennete (15-30 / 30-60 / 60 jours et plus),
    top 5 debiteurs avec lien direct vers la fiche entreprise.
- Onglet **Collaborateurs** : selection d'un collaborateur (admin ou
  collaborateur -- jamais secretaire) puis --
  - cartes KPI : CA HT realise (missions cloturees uniquement), missions
    cloturees, taches terminees, taches en retard, delai moyen de
    traitement d'une tache ;
  - graphiques : CA HT realise par mois, taches par statut, missions par
    prestation ;
  - jauges realise vs cible pour CA HT, missions cloturees et taches
    terminees ;
  - editeur des objectifs annuels du collaborateur, avec confirmation avant
    tout ecrasement ou toute suppression d'un objectif existant.

### Journal d'audit
Page reservee a l'admin : tracabilite des actions sur les entites sensibles
(factures, avoirs, paiements, devis, entreprises, utilisateurs, parametres)
-- qui a fait quoi, et quand.

- Liste paginee (15 lignes/page), filtrable par entite, par action
  (Creation / Modification / Suppression) et par plage de dates.
- Chaque ligne affiche : date/heure, utilisateur (ou « Systeme » si aucun
  acteur identifie), action, entite concernee.
- Bouton « Voir le detail » -> fenetre modale avec le detail champ par
  champ (valeur avant / valeur apres) lorsqu'il est disponible.

---

## Parcours collaborateur

- Voit **ses** missions et **ses** taches.
- Consulte le **Planning** (calendrier) : uniquement ses propres taches, en lecture seule.
- Met a jour le statut des taches (en cours, terminee...) et ajoute des commentaires.
- Pas d'acces a la facturation ni aux parametres.

---

## Parcours secretaire

- **Entreprises** : creation et modification (pas de suppression).
- **Recouvrement** : enregistrement des paiements, suivi des creances, relances.
- **Transmission** : envoi des devis et des factures deja emis.
- Ne cree ni ne supprime devis / factures / avoirs. **Aucun acces** Missions/Planning.

---

## Parcours client (portail)

- Espace `/portail` en **lecture seule**, strictement limite a ses donnees.
- Consulte : ses factures, ses documents (PDF), ses missions.
- Telecharge les PDF (devis, factures, rapports de mission).

---

## Regles metier a connaitre

| Regle | Comportement |
|---|---|
| Prix HT mission | `tarif x indice regime x indice categorie`, calcule une fois, immuable |
| TVA | Historisee : celle en vigueur a la date du document, jamais recalculee |
| Numerotation | Par exercice, remise a zero chaque annee (`FF2026-001`, `DV2026-001`...) |
| Statut facture | Automatique selon les paiements |
| Prospect -> Client | Automatique a la creation d'une mission |
| Suppressions | Bloquees si des documents lies existent (message explicite) |

---

## Documents PDF

Devis, factures et rapports de mission sont exportables en PDF depuis leurs
ecrans respectifs (bouton d'export / icone PDF). Le portail client permet au
client de telecharger ses propres documents.
