import api from '@/api/client'
import type { Facture, Mission, PaginatedResponse, User } from '@/types'

export interface PortailDocumentFilters {
  page?: number
  per_page?: number
}

export interface PortailFactureFilters {
  page?: number
  per_page?: number
  exercice_id?: number
  statut_paiement?: string
}

export interface PortailMissionFilters {
  page?: number
  per_page?: number
  statut?: string
}

export const portailApi = {
  me(): Promise<{ data: User }> {
    return api.get('/portail/me').then(r => r.data)
  },

  // US-30 — Mes factures
  getFactures(params?: PortailFactureFilters): Promise<PaginatedResponse<Facture>> {
    return api.get('/portail/factures', { params }).then(r => r.data)
  },

  telechargerFacturePdf(factureId: number, numero: string): Promise<void> {
    return api
      .get(`/portail/factures/${factureId}/pdf`, { responseType: 'blob' })
      .then(response => {
        const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
        const a = document.createElement('a')
        a.href = url
        a.download = `facture-${numero}.pdf`
        a.click()
        URL.revokeObjectURL(url)
      })
  },

  // US-31 — Mes missions
  getMissions(params?: PortailMissionFilters): Promise<PaginatedResponse<Mission>> {
    return api.get('/portail/missions', { params }).then(r => r.data)
  },

  getMission(id: number): Promise<{ data: Mission }> {
    return api.get(`/portail/missions/${id}`).then(r => r.data)
  },

  rapportMissionPdfUrl(missionId: number): string {
    return `/api/v1/portail/missions/${missionId}/rapport/pdf`
  },

  // US-32 — Mes documents
  getDocuments(): Promise<{ data: Mission[] }> {
    return api.get('/portail/documents').then(r => r.data)
  },

  telechargerConventionPdf(missionId: number, numero: string): Promise<void> {
    return api
      .get(`/portail/documents/${missionId}/convention/pdf`, { responseType: 'blob' })
      .then(response => {
        const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
        const a = document.createElement('a')
        a.href = url
        a.download = `convention-${numero}.pdf`
        a.click()
        URL.revokeObjectURL(url)
      })
  },

  telechargerMandatPdf(missionId: number, numero: string): Promise<void> {
    return api
      .get(`/portail/documents/${missionId}/mandat/pdf`, { responseType: 'blob' })
      .then(response => {
        const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
        const a = document.createElement('a')
        a.href = url
        a.download = `mandat-${numero}.pdf`
        a.click()
        URL.revokeObjectURL(url)
      })
  },
}
