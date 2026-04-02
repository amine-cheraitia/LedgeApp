import type { Relance } from '@/types'
import api from '../client'

export const relancesApi = {
  indexParFacture: (factureId: number) =>
    api.get<{ data: Relance[] }>(`/factures/${factureId}/relances`),

  store: (factureId: number, payload: { niveau: 1 | 2 | 3 }) =>
    api.post<{ data: Relance }>(`/factures/${factureId}/relances`, payload),
}
