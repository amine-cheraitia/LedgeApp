import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { exercicesApi, type ExercicePayload } from '@/api/modules/exercices'
import type { Exercice } from '@/types'

export function useExercices() {
  const toast = useToast()
  const exercices = ref<Exercice[]>([])
  const loading = ref(false)

  async function fetchExercices() {
    loading.value = true
    try {
      const response = await exercicesApi.getAll()
      exercices.value = response.data
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les exercices.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function createExercice(data: ExercicePayload) {
    const response = await exercicesApi.create(data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Exercice cree.', life: 3000 })
    await fetchExercices()
    return response.data
  }

  async function updateExercice(id: number, data: Partial<ExercicePayload>) {
    const response = await exercicesApi.update(id, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Exercice mis a jour.', life: 3000 })
    await fetchExercices()
    return response.data
  }

  return {
    exercices,
    loading,
    fetchExercices,
    createExercice,
    updateExercice,
  }
}
