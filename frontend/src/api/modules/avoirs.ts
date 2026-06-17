import api from '@/api/client'
import type { Avoir, PaginatedResponse } from '@/types'

export interface StoreAvoirPayload {
  montant_ht: number
  date_avoir: string
  motif: string
}

export interface AvoirFilters {
  page?: number
  per_page?: number
  exercice_id?: number
  search?: string
}

export const avoirsApi = {
  index: (factureId: number) =>
    api.get<{ data: Avoir[] }>(`/factures/${factureId}/avoirs`),

  store: (factureId: number, payload: StoreAvoirPayload) =>
    api.post<{ data: Avoir }>(`/factures/${factureId}/avoirs`, payload),

  getAll: (params?: AvoirFilters): Promise<PaginatedResponse<Avoir>> =>
    api.get('/avoirs', { params }).then(r => r.data),

  delete: (avoirId: number): Promise<void> =>
    api.delete(`/avoirs/${avoirId}`).then(() => undefined),

  telechargerPdf: (factureId: number, avoirId: number, numero: string) =>
    api.get(`/factures/${factureId}/avoirs/${avoirId}/pdf`, { responseType: 'blob' }).then((res) => {
      const url = URL.createObjectURL(new Blob([res.data]))
      const a = document.createElement('a')
      a.href = url
      a.download = `avoir-${numero}.pdf`
      a.click()
      URL.revokeObjectURL(url)
    }),
}
