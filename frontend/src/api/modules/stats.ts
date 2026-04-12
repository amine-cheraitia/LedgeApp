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
    factures: any[]
    missions: any[]
  }
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
    date_fin: string | null
    mission_reference: string | null
    entreprise: string | null
    en_retard: boolean
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
