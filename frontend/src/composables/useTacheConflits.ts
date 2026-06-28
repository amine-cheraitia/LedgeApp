import { ref, watch, type Ref } from 'vue'
import { tachesApi, type ConflitTache } from '@/api/modules/taches'
import { toIsoDate } from '@/utils/date'

/**
 * Détection réactive de conflit d'affectation de tâche.
 * Quand un collaborateur et au moins une date sont renseignés, interroge le backend
 * (debounce) pour lister ses tâches qui chevauchent la période — toutes missions
 * confondues. Avertissement non bloquant : l'admin reste libre d'enregistrer.
 */
export function useTacheConflits(
  collaborateurId: Ref<number | null | undefined>,
  dateDebut: Ref<Date | null>,
  dateEcheance: Ref<Date | null>,
  excludeId?: Ref<number | null | undefined>,
) {
  const conflits = ref<ConflitTache[]>([])
  const checking = ref(false)

  let timer: ReturnType<typeof window.setTimeout> | undefined
  let seq = 0

  function reset() {
    if (timer) window.clearTimeout(timer)
    conflits.value = []
    checking.value = false
  }

  async function check() {
    const collab = collaborateurId.value
    const debut = toIsoDate(dateDebut.value)
    const echeance = toIsoDate(dateEcheance.value)

    // Besoin d'un collaborateur ET d'au moins une date pour avoir du sens.
    if (!collab || (!debut && !echeance)) {
      conflits.value = []
      checking.value = false
      return
    }

    checking.value = true
    const current = ++seq
    try {
      const res = await tachesApi.conflits({
        collaborateur_id: collab,
        date_debut: debut,
        date_echeance: echeance,
        exclude_tache_id: excludeId?.value ?? undefined,
      })
      // Ignore les réponses obsolètes (course entre requêtes successives).
      if (current === seq) conflits.value = res.data
    } catch {
      if (current === seq) conflits.value = []
    } finally {
      if (current === seq) checking.value = false
    }
  }

  const sources: Ref<unknown>[] = [collaborateurId, dateDebut, dateEcheance]
  if (excludeId) sources.push(excludeId)

  watch(sources, () => {
    if (timer) window.clearTimeout(timer)
    timer = window.setTimeout(check, 400)
  })

  return { conflits, checking, reset }
}
