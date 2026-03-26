import api from '@/api/client'
import type { Facture, Paiement, PaginatedResponse } from '@/types'

export interface FactureFilters {
  page?: number
  per_page?: number
  search?: string
  entreprise_id?: number
  type?: 'FF' | 'FA'
  statut_paiement?: string
}

export interface FactureLignePayload {
  prestation_id?: number | null
  designation: string
  quantite: number
  prix_unitaire_ht: number
}

export interface FacturePayload {
  entreprise_id: number
  mission_id?: number | null
  devis_id?: number | null
  type: 'FF' | 'FA'
  facture_origine_id?: number | null
  date_facture: string
  date_echeance: string
  notes?: string | null
  lignes: FactureLignePayload[]
}

export interface PaiementPayload {
  montant: number
  date_paiement: string
  mode_paiement: 'virement' | 'cheque' | 'espece' | 'autre'
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

  delete(id: number): Promise<void> {
    return api.delete(`/factures/${id}`)
  },

  // Paiements
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
