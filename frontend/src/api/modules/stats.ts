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
