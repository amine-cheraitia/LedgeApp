import api from '@/api/client'
import type { TvaTaux } from '@/types'

export interface TvaTauxPayload {
  taux: number
  designation: string
  type: 'standard' | 'exonere'
  date_debut: string
  date_fin?: string | null
  actif?: boolean
}

export const referentielsApi = {
  getAllTvaTaux: (): Promise<{ data: TvaTaux[] }> =>
    api.get('/referentiels/tva-taux').then(r => r.data),

  createTvaTaux: (data: TvaTauxPayload): Promise<{ data: TvaTaux }> =>
    api.post('/referentiels/tva-taux', data).then(r => r.data),

  updateTvaTaux: (id: number, data: Partial<TvaTauxPayload>): Promise<{ data: TvaTaux }> =>
    api.put(`/referentiels/tva-taux/${id}`, data).then(r => r.data),

  deleteTvaTaux: (id: number): Promise<void> =>
    api.delete(`/referentiels/tva-taux/${id}`).then(() => undefined),
}
