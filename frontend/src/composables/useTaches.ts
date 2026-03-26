import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { tachesApi, type TachePayload } from '@/api/modules/taches'
import type { Tache } from '@/types'

export function useTaches(missionId: number) {
  const toast = useToast()
  const taches = ref<Tache[]>([])
  const loading = ref(false)

  async function fetchTaches() {
    loading.value = true
    try {
      const response = await tachesApi.getAll(missionId)
      taches.value = response.data
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les taches.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function createTache(data: TachePayload) {
    const response = await tachesApi.create(missionId, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Tache creee.', life: 3000 })
    await fetchTaches()
    return response.data
  }

  async function updateTache(tacheId: number, data: Partial<TachePayload>) {
    const response = await tachesApi.update(missionId, tacheId, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Tache mise a jour.', life: 3000 })
    await fetchTaches()
    return response.data
  }

  async function deleteTache(tacheId: number) {
    await tachesApi.delete(missionId, tacheId)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Tache supprimee.', life: 3000 })
    await fetchTaches()
  }

  return {
    taches,
    loading,
    fetchTaches,
    createTache,
    updateTache,
    deleteTache,
  }
}
