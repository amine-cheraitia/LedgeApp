import api from '@/api/client'
import type { Avoir } from '@/types'

export interface StoreAvoirPayload {
  montant_ht: number
  date_avoir: string
  motif: string
}

export const avoirsApi = {
  getAll: (): Promise<{ data: Avoir[] }> =>
    api.get('/avoirs').then(r => r.data),

  index: (factureId: number) =>
    api.get<{ data: Avoir[] }>(`/factures/${factureId}/avoirs`),

  store: (factureId: number, payload: StoreAvoirPayload) =>
    api.post<{ data: Avoir }>(`/factures/${factureId}/avoirs`, payload),

  delete: (avoirId: number): Promise<void> =>
    api.delete(`/avoirs/${avoirId}`),

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
