import api from '@/api/client'
import type { Prestation } from '@/types'

export interface CalculPrixResult {
  prestation: string
  tarif_initial: number
  regime_fiscal: string
  categorie: string
  prix_ht: number
}

export const prestationsApi = {
  getAll(): Promise<{ data: Prestation[] }> {
    return api.get('/prestations').then(r => r.data)
  },

  getOne(id: number): Promise<{ data: Prestation }> {
    return api.get(`/prestations/${id}`).then(r => r.data)
  },

  calculerPrix(id: number, regime_fiscal: string, categorie: string): Promise<CalculPrixResult> {
    return api.post(`/prestations/${id}/calculer-prix`, { regime_fiscal, categorie }).then(r => r.data)
  },
}
