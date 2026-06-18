import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { referentielsApi, type TvaTauxPayload } from '@/api/modules/referentiels'
import { getApiErrorMessage } from '@/composables/useApiError'
import type { TvaTaux } from '@/types'

export function useTvaTaux() {
  const toast = useToast()
  const taux = ref<TvaTaux[]>([])
  const loading = ref(false)

  async function fetchTaux() {
    loading.value = true
    try {
      const response = await referentielsApi.getAllTvaTaux()
      taux.value = response.data
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les taux de TVA.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function createTaux(data: TvaTauxPayload) {
    try {
      const response = await referentielsApi.createTvaTaux(data)
      toast.add({ severity: 'success', summary: 'Succes', detail: 'Taux de TVA cree.', life: 3000 })
      await fetchTaux()
      return response.data
    } catch (e) {
      toast.add({ severity: 'error', summary: 'Erreur', detail: getApiErrorMessage(e, 'Creation impossible.'), life: 4000 })
      throw e
    }
  }

  async function updateTaux(id: number, data: Partial<TvaTauxPayload>) {
    try {
      const response = await referentielsApi.updateTvaTaux(id, data)
      toast.add({ severity: 'success', summary: 'Succes', detail: 'Taux de TVA mis a jour.', life: 3000 })
      await fetchTaux()
      return response.data
    } catch (e) {
      toast.add({ severity: 'error', summary: 'Erreur', detail: getApiErrorMessage(e, 'Mise a jour impossible.'), life: 4000 })
      throw e
    }
  }

  async function deleteTaux(id: number) {
    try {
      await referentielsApi.deleteTvaTaux(id)
      toast.add({ severity: 'success', summary: 'Succes', detail: 'Taux de TVA supprime.', life: 3000 })
      await fetchTaux()
    } catch (e) {
      toast.add({ severity: 'error', summary: 'Erreur', detail: getApiErrorMessage(e, 'Suppression impossible.'), life: 4000 })
    }
  }

  function isoLocal(d: Date): string {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }

  // Miroir cote client de TvaTaux::enVigueurLe : taux ACTIF en vigueur du type a une date donnee
  function tauxEnVigueur(type: string, date: Date | string | null): number | null {
    if (!date) return null
    const dStr = typeof date === 'string' ? date.slice(0, 10) : isoLocal(date)
    const candidats = taux.value
      .filter(t => t.type === type && t.actif && t.date_debut <= dStr && (!t.date_fin || t.date_fin >= dStr))
      // Meme regle que le backend enVigueurLe : date_debut la plus recente, puis id le plus grand (departage stable)
      .sort((a, b) => (a.date_debut !== b.date_debut ? (a.date_debut < b.date_debut ? 1 : -1) : b.id - a.id))
    return candidats.length ? Number(candidats[0].taux) : null
  }

  return { taux, loading, fetchTaux, createTaux, updateTaux, deleteTaux, tauxEnVigueur }
}
