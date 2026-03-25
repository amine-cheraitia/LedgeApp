import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { entreprisesApi, type EntrepriseFilters } from '@/api/modules/entreprises'
import type { Entreprise } from '@/types'

export function useEntreprises() {
  const toast = useToast()
  const entreprises = ref<Entreprise[]>([])
  const loading = ref(false)
  const totalRecords = ref(0)
  const filters = ref<EntrepriseFilters>({ page: 1, per_page: 15 })

  async function fetchEntreprises() {
    loading.value = true
    try {
      const response = await entreprisesApi.getAll({
        ...filters.value,
        search: filters.value.search || undefined,
      })
      entreprises.value = response.data
      totalRecords.value = response.meta?.total ?? response.data.length
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les entreprises.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function createEntreprise(data: Partial<Entreprise>) {
    const response = await entreprisesApi.create(data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Entreprise creee.', life: 3000 })
    await fetchEntreprises()
    return response.data
  }

  async function updateEntreprise(id: number, data: Partial<Entreprise>) {
    const response = await entreprisesApi.update(id, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Entreprise mise a jour.', life: 3000 })
    await fetchEntreprises()
    return response.data
  }

  async function deleteEntreprise(id: number) {
    await entreprisesApi.delete(id)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Entreprise supprimee.', life: 3000 })
    await fetchEntreprises()
  }

  function onPage(event: { page: number }) {
    filters.value.page = event.page + 1
    fetchEntreprises()
  }

  function onSearch(search: string) {
    filters.value.search = search
    filters.value.page = 1
    fetchEntreprises()
  }

  return {
    entreprises,
    loading,
    totalRecords,
    filters,
    fetchEntreprises,
    createEntreprise,
    updateEntreprise,
    deleteEntreprise,
    onPage,
    onSearch,
  }
}
