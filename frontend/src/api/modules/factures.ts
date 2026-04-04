import api from '@/api/client'
import type { Facture, Paiement, PaginatedResponse } from '@/types'

export interface FactureFilters {
  page?: number
  per_page?: number
  search?: string
  exercice_id?: number
  entreprise_id?: number
  statut_paiement?: string
  sort_field?: string
  sort_direction?: 'asc' | 'desc'
}

export interface FacturePayload {
  mission_id: number
  date_facture: string
  notes?: string | null
}

export interface PaiementPayload {
  montant: number
  date_paiement: string
  mode_paiement: 'virement' | 'cheque' | 'autre'
  reference?: string | null
  notes?: string | null
}

export const facturesApi = {
  getAll(params?: FactureFilters): Promise<PaginatedResponse<Facture>> {
    return api.get('/factures', { params }).then(r => r.data)
  },

  getOne(id: number): Promise<{ data: Facture }> {
    return api.get(`/factures/${id}`).then(r => r.data)
  },

  create(data: FacturePayload): Promise<{ data: Facture }> {
    return api.post('/factures', data).then(r => r.data)
  },

  getPdf(id: number): Promise<Blob> {
    return api.get(`/factures/${id}/pdf`, { responseType: 'blob' }).then(r => r.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/factures/${id}`)
  },

  getPaiements(factureId: number): Promise<{ data: Paiement[] }> {
    return api.get(`/factures/${factureId}/paiements`).then(r => r.data)
  },

  createPaiement(factureId: number, data: PaiementPayload): Promise<{ data: Paiement }> {
    return api.post(`/factures/${factureId}/paiements`, data).then(r => r.data)
  },

  deletePaiement(factureId: number, paiementId: number): Promise<void> {
    return api.delete(`/factures/${factureId}/paiements/${paiementId}`)
  },
}
