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

  let _debounceTimer: ReturnType<typeof setTimeout> | null = null

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

  async function telechargerPdf(id: number, numero: string) {
    try {
      const blob = await devisApi.getPdf(id)
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `devis-${numero}.pdf`
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de generer le PDF.', life: 3000 })
    }
  }

  async function updateDevis(id: number, data: { entreprise_id?: number; prestation_id?: number; date_devis?: string; date_validite?: string; notes?: string }) {
    await devisApi.update(id, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Devis mis a jour.', life: 3000 })
    await fetchDevis()
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
    if (_debounceTimer) clearTimeout(_debounceTimer)
    _debounceTimer = setTimeout(() => {
      filters.value.search = search
      filters.value.page = 1
      fetchDevis()
    }, 300)
  }

  function onSort(event: { sortField?: string | ((item: any) => string) | null; sortOrder?: number | null }) {
    filters.value.sort_field = typeof event.sortField === 'string' ? event.sortField : undefined
    filters.value.sort_direction = event.sortOrder === 1 ? 'asc' : 'desc'
    filters.value.page = 1
    fetchDevis()
  }

  function setExercice(exerciceId: number | undefined) {
    filters.value.exercice_id = exerciceId
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
    updateDevis,
    envoyerDevis,
    accepterDevis,
    refuserDevis,
    convertirDevisEnMission,
    telechargerPdf,
    deleteDevis,
    onPage,
    onSearch,
    onSort,
    setExercice,
  }
}
