import api from '@/api/client'
import type { TacheCommentaire } from '@/types'

export const commentairesApi = {
  getAll(tacheId: number): Promise<{ data: TacheCommentaire[] }> {
    return api.get(`/taches/${tacheId}/commentaires`).then(r => r.data)
  },

  create(tacheId: number, contenu: string): Promise<{ data: TacheCommentaire }> {
    return api.post(`/taches/${tacheId}/commentaires`, { contenu }).then(r => r.data)
  },

  update(tacheId: number, id: number, contenu: string): Promise<{ data: TacheCommentaire }> {
    return api.put(`/taches/${tacheId}/commentaires/${id}`, { contenu }).then(r => r.data)
  },

  delete(tacheId: number, id: number): Promise<void> {
    return api.delete(`/taches/${tacheId}/commentaires/${id}`)
  },
}
