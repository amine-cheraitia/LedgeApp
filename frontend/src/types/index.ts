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
