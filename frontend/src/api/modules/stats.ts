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

export const statsApi = {
  getDashboard(exerciceId?: number | null): Promise<{ data: DashboardStats }> {
    const params = exerciceId ? { exercice_id: exerciceId } : {}
    return api.get('/stats', { params }).then((r) => r.data)
  },
}
