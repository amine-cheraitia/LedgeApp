export interface User {
  id: number
  name: string
  email: string
  entreprise_id: number | null
  portail_actif: boolean
  email_verified_at: string | null
  roles: string[]
  entreprise?: Entreprise
  created_at: string
  updated_at: string
}

export interface Entreprise {
  id: number
  raison_sociale: string
  nif: string | null
  nis: string | null
  num_rc: string | null
  article_imposition: string | null
  regime_fiscal: string
  categorie: string
  secteur_activite: string | null
  adresse: string | null
  ville: string | null
  wilaya: string | null
  telephone: string | null
  email: string | null
  contact_principal: string | null
  statut: 'prospect' | 'client' | 'ancien_client'
  notes: string | null
  missions_count?: number
  factures_count?: number
  portail_user?: {
    id: number
    name: string
    email: string
    portail_actif: boolean
  } | null
  created_at: string
  updated_at: string
}

export interface Contact {
  id: number
  entreprise_id: number
  nom: string
  prenom: string | null
  email: string | null
  telephone: string | null
  poste: string | null
  est_principal: boolean
  created_at: string
  updated_at: string
}

export interface Exercice {
  id: number
  annee: number
  date_ouverture: string
  date_cloture: string
  statut: 'ouvert' | 'cloture'
  created_at: string
  updated_at: string
}

export interface Prestation {
  id: number
  code: string
  designation: string
  tarif_initial: number
  duree_mois: number
  description: string | null
  actif: boolean
  created_at: string
  updated_at: string
}

export interface Setting {
  id: number
  key: string
  value: string
  group: string | null
  label: string | null
}

export interface Mission {
  id: number
  entreprise_id: number
  exercice_id: number
  prestation_id: number
  reference: string
  prix_ht: number
  date_debut: string
  date_fin: string | null
  statut: 'en_cours' | 'terminee' | 'suspendue' | 'annulee'
  notes: string | null
  entreprise?: Entreprise
  exercice?: Exercice
  prestation?: Prestation
  collaborateurs?: User[]
  taches?: Tache[]
  factures?: Facture[]
  created_at: string
  updated_at: string
}

export interface Tache {
  id: number
  mission_id: number
  assigned_to: number | null
  titre: string
  description: string | null
  statut: 'a_faire' | 'en_cours' | 'terminee' | 'bloquee'
  date_echeance: string | null
  priorite: number
  assignee?: User
  created_at: string
  updated_at: string
}

export interface Devis {
  id: number
  entreprise_id: number
  prestation_id: number
  exercice_id: number
  created_by: number
  numero: string
  date_devis: string
  date_validite: string
  prix_ht: number
  montant_ht: number
  montant_tva: number
  montant_timbre: number
  montant_ttc: number
  statut: 'brouillon' | 'envoye' | 'accepte' | 'refuse' | 'expire'
  notes: string | null
  entreprise?: Entreprise
  prestation?: Prestation
  created_at: string
  updated_at: string
}

export interface FactureLigne {
  id?: number
  facture_id?: number
  prestation_id: number | null
  designation: string
  quantite: number
  prix_unitaire_ht: number
  total_ht: number
  ordre: number
  prestation?: Prestation
}

export interface Facture {
  id: number
  entreprise_id: number
  exercice_id: number
  mission_id: number | null
  devis_id: number | null
  created_by: number
  tva_rate_id: number | null
  timbre_rate_id: number | null
  numero: string
  type: 'FF' | 'FA'
  facture_origine_id: number | null
  date_facture: string
  date_echeance: string
  montant_ht: number
  taux_tva: number
  montant_tva: number
  montant_timbre: number
  montant_ttc: number
  montant_paye: number
  statut_paiement: 'en_attente' | 'partiel' | 'solde'
  mode_paiement: 'virement' | 'cheque' | 'autre' | 'non_defini'
  montant_restant: number
  pdf_path: string | null
  notes: string | null
  entreprise?: Entreprise
  exercice?: Exercice
  mission?: Mission
  lignes?: FactureLigne[]
  paiements?: Paiement[]
  relances?: Relance[]
  avoirs?: Avoir[]
  created_at: string
  updated_at: string
}

export interface Avoir {
  id: number
  facture_origine_id: number
  exercice_id: number
  created_by: number
  numero: string
  date_avoir: string
  montant_ht: number
  taux_tva_snapshot: number
  montant_tva: number
  montant_ttc: number
  motif: string
  facture_origine?: {
    id: number
    numero: string
    entreprise?: { raison_sociale: string } | null
  }
  created_at: string
}

export interface Relance {
  id: number
  facture_id: number
  sent_by: number | null
  niveau: 1 | 2 | 3
  type: 'automatique' | 'manuelle'
  email_destinataire: string
  envoyee_le: string | null
  statut: 'en_attente' | 'envoyee' | 'echec' | 'annulee'
  message: string | null
  sentBy?: User
  created_at: string
}

export interface Paiement {
  id: number
  facture_id: number
  recorded_by: number
  montant: number
  date_paiement: string
  mode_paiement: 'virement' | 'cheque' | 'espece' | 'autre'
  reference: string | null
  notes: string | null
  facture?: Facture
  created_at: string
  updated_at: string
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}
