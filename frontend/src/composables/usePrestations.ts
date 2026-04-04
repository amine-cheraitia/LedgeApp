import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { prestationsApi, type PrestationPayload } from '@/api/modules/prestations'
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

  async function createPrestation(data: PrestationPayload) {
    const response = await prestationsApi.create(data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Prestation creee.', life: 3000 })
    await fetchPrestations()
    return response.data
  }

  async function updatePrestation(id: number, data: Partial<PrestationPayload>) {
    const response = await prestationsApi.update(id, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Prestation mise a jour.', life: 3000 })
    await fetchPrestations()
    return response.data
  }

  async function deletePrestation(id: number) {
    await prestationsApi.delete(id)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Prestation supprimee.', life: 3000 })
    await fetchPrestations()
  }

  function calculerPrix(prestationId: number, regime_fiscal: string, categorie: string) {
    return prestationsApi.calculerPrix(prestationId, regime_fiscal, categorie)
  }

  return {
    prestations,
    loading,
    fetchPrestations,
    createPrestation,
    updatePrestation,
    deletePrestation,
    calculerPrix,
  }
}
