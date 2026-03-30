import api from '@/api/client'

export interface DashboardStats {
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
  getDashboard(): Promise<{ data: DashboardStats }> {
    return api.get('/stats').then((r) => r.data)
  },
}
