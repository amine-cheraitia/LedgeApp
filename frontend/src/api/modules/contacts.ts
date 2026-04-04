import api from '@/api/client'
import type { Contact } from '@/types'

export interface ContactPayload {
  nom: string
  prenom?: string | null
  email?: string | null
  telephone?: string | null
  poste?: string | null
  est_principal?: boolean
}

export const contactsApi = {
  getAll(entrepriseId: number): Promise<{ data: Contact[] }> {
    return api.get(`/entreprises/${entrepriseId}/contacts`).then(r => r.data)
  },

  create(entrepriseId: number, data: ContactPayload): Promise<{ data: Contact }> {
    return api.post(`/entreprises/${entrepriseId}/contacts`, data).then(r => r.data)
  },

  update(entrepriseId: number, contactId: number, data: Partial<ContactPayload>): Promise<{ data: Contact }> {
    return api.put(`/entreprises/${entrepriseId}/contacts/${contactId}`, data).then(r => r.data)
  },

  delete(entrepriseId: number, contactId: number): Promise<void> {
    return api.delete(`/entreprises/${entrepriseId}/contacts/${contactId}`).then(() => undefined)
  },
}
