import api from '@/api/client'

export interface DashboardStats {
  exercices: { id: number; annee: number; statut: string }[]
  exercice_courant: number | null
  kpi: {
    ca_mois: number
    tva_collectee: number
    taux_recouvrement: number
    seuil_recouvrement: number
  }
  alertes: { type: 'danger' | 'warn' | 'info'; message: string }[]
  entreprises: {
    total: number
    clients: number
    prospects: number
  }
  missions: {
    total: number
    en_cours: number
    terminees: number
    ca_ht: number
  }
  factures: {
    total: number
    en_attente: number
    partielles: number
    soldees: number
    ca_ttc: number
    total_paye: number
    total_impaye: number
    en_retard: number
  }
  devis: {
    total: number
    en_attente: number
    acceptes: number
    ca_potentiel: number
  }
  recentes: {
    factures: {
      id: number
      numero: string
      montant_ttc: number
      statut_paiement: string
      date_facture: string
      entreprise?: { raison_sociale: string }
    }[]
    missions: {
      id: number
      reference: string
      statut: string
      prix_ht: number
      entreprise?: { raison_sociale: string }
    }[]
  }
}

export interface SecretaireAction {
  key: string
  label: string
  count: number
  severity: 'danger' | 'warn' | 'info'
  icon: string
  route: string
}

export interface SecretaireStats {
  alertes: { type: 'danger' | 'warn' | 'info'; message: string }[]
  actions: SecretaireAction[]
  creances: {
    total_impaye: number
    clients_debiteurs: number
    en_retard: number
  }
  aging: {
    retard_15_30: number
    retard_30_60: number
    retard_60_plus: number
  }
  relances_dues: {
    niveau_1: number
    niveau_2: number
    niveau_3: number
  }
  top_debiteurs: {
    entreprise_id: number
    raison_sociale: string
    montant_impaye: number
  }[]
  factures_emises: {
    mois_courant: { count: number; montant_ttc: number }
    mois_precedent: { count: number; montant_ttc: number }
  }
  facturation: {
    devis_en_attente: { count: number; montant: number }
    devis_a_convertir: number
    devis_expirant: number
    encaissements_mois: { count: number; montant: number }
  }
  recentes_creances: {
    id: number
    numero: string
    entreprise: string | null
    montant_restant: number
    date_echeance: string | null
    statut_paiement: string
    en_retard: boolean
  }[]
}

export interface CollaborateurStats {
  missions: { total: number; en_cours: number; terminees: number }
  taches: {
    total: number
    a_faire: number
    en_cours: number
    terminees: number
    bloquees: number
    taux_completion: number
    en_retard: number
  }
  mes_missions: {
    id: number
    reference: string
    statut: string
    entreprise: string | null
    prestation: string | null
    date_fin: string | null
    taches_total: number
    taches_terminees: number
    progression: number
  }[]
  mes_taches_urgentes: {
    id: number
    mission_id: number
    titre: string
    statut: string
    priorite: number
    date_fin: string | null
    mission_reference: string | null
    entreprise: string | null
    en_retard: boolean
  }[]
  echeances: {
    type: 'mission' | 'tache'
    id: number
    titre: string
    entreprise: string | null
    mission_id: number | null
    mission_reference: string | null
    date: string
    en_retard: boolean
  }[]
  activite_recente: {
    id: number
    titre: string
    mission_reference: string | null
    mission_id: number | null
    date: string
  }[]
}

export type KpiObjectifType = 'ca_ht' | 'missions_cloturees' | 'taches_terminees'

export interface KpiCollaborateur {
  user: { id: number; name: string; email: string }
  objectifs: Partial<Record<KpiObjectifType, number>>
  realise: Record<KpiObjectifType | 'taches_en_retard' | 'delai_moyen_tache', number>
}

export const statsApi = {
  getDashboard(exerciceId?: number | null): Promise<{ data: DashboardStats }> {
    const params = exerciceId ? { exercice_id: exerciceId } : {}
    return api.get('/stats', { params }).then((r) => r.data)
  },

  getCollaborateurDashboard(): Promise<{ data: CollaborateurStats }> {
    return api.get('/collaborateur/stats').then((r) => r.data)
  },

  getSecretaireDashboard(): Promise<{ data: SecretaireStats }> {
    return api.get('/stats/secretaire').then((r) => r.data)
  },

  getKpiObjectifs(exerciceId?: number | null): Promise<{ data: KpiCollaborateur[] }> {
    const params = exerciceId ? { exercice_id: exerciceId } : {}
    return api.get('/kpi/objectifs', { params }).then((r) => r.data)
  },

  upsertKpiObjectif(data: {
    user_id: number
    exercice_id: number
    type: string
    valeur: number
  }): Promise<{ data: { id: number; type: string; valeur: number } }> {
    return api.post('/kpi/objectifs', data).then((r) => r.data)
  },

  deleteKpiObjectif(id: number): Promise<void> {
    return api.delete(`/kpi/objectifs/${id}`)
  },
}
