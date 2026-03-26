import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { prestationsApi } from '@/api/modules/prestations'
import type { Prestation } from '@/types'

export function usePrestations() {
  const toast = useToast()
  const prestations = ref<Prestation[]>([])
  const loading = ref(false)

  async function fetchPrestations() {
    loading.value = true
    try {
      const response = await prestationsApi.getAll()
      prestations.value = response.data
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les prestations.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function calculerPrix(prestationId: number, regime_fiscal: string, categorie: string) {
    return prestationsApi.calculerPrix(prestationId, regime_fiscal, categorie)
  }

  return {
    prestations,
    loading,
    fetchPrestations,
    calculerPrix,
  }
}
