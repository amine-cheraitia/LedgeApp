import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { devisApi, type DevisFilters, type DevisPayload, type ConvertirEnMissionPayload } from '@/api/modules/devis'
import type { Devis } from '@/types'

export function useDevis() {
  const toast = useToast()
  const devisList = ref<Devis[]>([])
  const loading = ref(false)
  const totalRecords = ref(0)
  const filters = ref<DevisFilters>({ page: 1, per_page: 15 })

  async function fetchDevis() {
    loading.value = true
    try {
      const response = await devisApi.getAll({
        ...filters.value,
        search: filters.value.search || undefined,
      })
      devisList.value = response.data
      totalRecords.value = response.meta?.total ?? response.data.length
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les devis.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function createDevis(data: DevisPayload) {
    const response = await devisApi.create(data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Devis cree.', life: 3000 })
    await fetchDevis()
    return response.data
  }

  async function envoyerDevis(id: number) {
    const response = await devisApi.envoyer(id)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Devis envoye.', life: 3000 })
    await fetchDevis()
    return response.data
  }

  async function accepterDevis(id: number) {
    const response = await devisApi.accepter(id)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Devis accepte.', life: 3000 })
    await fetchDevis()
    return response.data
  }

  async function refuserDevis(id: number) {
    const response = await devisApi.refuser(id)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Devis refuse.', life: 3000 })
    await fetchDevis()
    return response.data
  }

  async function convertirDevisEnMission(id: number, data: ConvertirEnMissionPayload) {
    const response = await devisApi.convertirEnMission(id, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Mission creee depuis le devis.', life: 3000 })
    await fetchDevis()
    return response.data
  }

  async function deleteDevis(id: number) {
    await devisApi.delete(id)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Devis supprime.', life: 3000 })
    await fetchDevis()
  }

  function onPage(event: { page: number }) {
    filters.value.page = event.page + 1
    fetchDevis()
  }

  function onSearch(search: string) {
    filters.value.search = search
    filters.value.page = 1
    fetchDevis()
  }

  return {
    devisList,
    loading,
    totalRecords,
    filters,
    fetchDevis,
    createDevis,
    envoyerDevis,
    accepterDevis,
    refuserDevis,
    convertirDevisEnMission,
    deleteDevis,
    onPage,
    onSearch,
  }
}
