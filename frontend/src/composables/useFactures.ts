import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { facturesApi, type FactureFilters, type FacturePayload, type PaiementPayload } from '@/api/modules/factures'
import type { Facture } from '@/types'

export function useFactures() {
  const toast = useToast()
  const factures = ref<Facture[]>([])
  const loading = ref(false)
  const totalRecords = ref(0)
  const filters = ref<FactureFilters>({ page: 1, per_page: 15 })

  async function fetchFactures() {
    loading.value = true
    try {
      const response = await facturesApi.getAll({
        ...filters.value,
        search: filters.value.search || undefined,
      })
      factures.value = response.data
      totalRecords.value = response.meta?.total ?? response.data.length
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les factures.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function createFacture(data: FacturePayload) {
    const response = await facturesApi.create(data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Facture creee.', life: 3000 })
    await fetchFactures()
    return response.data
  }

  async function deleteFacture(id: number) {
    await facturesApi.delete(id)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Facture supprimee.', life: 3000 })
    await fetchFactures()
  }

  async function addPaiement(factureId: number, data: PaiementPayload) {
    const response = await facturesApi.createPaiement(factureId, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Paiement enregistre.', life: 3000 })
    await fetchFactures()
    return response.data
  }

  async function telechargerPdf(id: number, numero: string) {
    try {
      const blob = await facturesApi.getPdf(id)
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `facture-${numero}.pdf`
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de generer le PDF.', life: 3000 })
    }
  }

  function onPage(event: { page: number }) {
    filters.value.page = event.page + 1
    fetchFactures()
  }

  function onSearch(search: string) {
    filters.value.search = search
    filters.value.page = 1
    fetchFactures()
  }

  return {
    factures,
    loading,
    totalRecords,
    filters,
    fetchFactures,
    createFacture,
    deleteFacture,
    addPaiement,
    telechargerPdf,
    onPage,
    onSearch,
  }
}
