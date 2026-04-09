import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { commentairesApi } from '@/api/modules/commentaires'
import type { TacheCommentaire } from '@/types'

export function useCommentaires() {
  const toast = useToast()
  const commentaires = ref<TacheCommentaire[]>([])
  const loading = ref(false)

  async function fetchCommentaires(tacheId: number): Promise<void> {
    loading.value = true
    try {
      const response = await commentairesApi.getAll(tacheId)
      commentaires.value = response.data
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les commentaires.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function createCommentaire(tacheId: number, contenu: string): Promise<TacheCommentaire | null> {
    try {
      const response = await commentairesApi.create(tacheId, contenu)
      toast.add({ severity: 'success', summary: 'Succès', detail: 'Commentaire ajouté.', life: 3000 })
      await fetchCommentaires(tacheId)
      return response.data
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible d\'ajouter le commentaire.', life: 3000 })
      return null
    }
  }

  async function updateCommentaire(tacheId: number, id: number, contenu: string): Promise<void> {
    try {
      await commentairesApi.update(tacheId, id, contenu)
      toast.add({ severity: 'success', summary: 'Succès', detail: 'Commentaire modifié.', life: 3000 })
      await fetchCommentaires(tacheId)
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de modifier le commentaire.', life: 3000 })
    }
  }

  async function deleteCommentaire(tacheId: number, id: number): Promise<void> {
    try {
      await commentairesApi.delete(tacheId, id)
      toast.add({ severity: 'success', summary: 'Succès', detail: 'Commentaire supprimé.', life: 3000 })
      await fetchCommentaires(tacheId)
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer le commentaire.', life: 3000 })
    }
  }

  return { commentaires, loading, fetchCommentaires, createCommentaire, updateCommentaire, deleteCommentaire }
}
