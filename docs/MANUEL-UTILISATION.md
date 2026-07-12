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

### Portail client
Depuis la fiche entreprise (statut *client*) -> **« Activer l'acces portail »** :
- cree le compte client (role `client`, rattache a l'entreprise) ;
- envoie une **invitation** ; en repli, un **lien copiable** est affiche a l'admin ;
- `portail_actif = 1` pour activer, `0` pour revoquer.

---

## Parcours collaborateur

- Voit **ses** missions et **ses** taches.
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
