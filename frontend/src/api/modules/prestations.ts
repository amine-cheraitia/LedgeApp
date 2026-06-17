import api from '@/api/client'
import type { Prestation } from '@/types'

export interface CalculPrixResult {
  prestation: string
  tarif_initial: number
  regime_fiscal: string
  categorie: string
  prix_ht: number
}

export interface PrestationPayload {
  code: string
  designation: string
  tarif_initial: number
  duree_mois: number
  description?: string | null
  actif?: boolean
}

export const prestationsApi = {
  getAll(): Promise<{ data: Prestation[] }> {
    return api.get('/prestations').then(r => r.data)
  },

  getOne(id: number): Promise<{ data: Prestation }> {
    return api.get(`/prestations/${id}`).then(r => r.data)
  },

  create(data: PrestationPayload): Promise<{ data: Prestation }> {
    return api.post('/prestations', data).then(r => r.data)
  },

  update(id: number, data: Partial<PrestationPayload>): Promise<{ data: Prestation }> {
    return api.put(`/prestations/${id}`, data).then(r => r.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/prestations/${id}`).then(() => undefined)
  },

  calculerPrix(id: number, regime_fiscal: string, categorie: string): Promise<CalculPrixResult> {
    return api.post(`/prestations/${id}/calculer-prix`, { regime_fiscal, categorie }).then(r => r.data)
  },
}
