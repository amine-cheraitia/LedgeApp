import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { ref, nextTick } from 'vue'

// ── Mock API (hoiste) ────────────────────────────────────────────────────────
const { mockConflits } = vi.hoisted(() => ({ mockConflits: vi.fn() }))
vi.mock('@/api/modules/taches', () => ({ tachesApi: { conflits: mockConflits } }))

import { useTacheConflits } from '@/composables/useTacheConflits'
import type { ConflitTache } from '@/api/modules/taches'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const conflitBilan: ConflitTache = {
  id: 42,
  titre: 'Bilan annuel',
  date_debut: '2026-03-01',
  date_echeance: '2026-03-15',
  mission: { id: 5, reference: 'M2026-005' },
}

function setup(withExclude = false) {
  const collaborateurId = ref<number | null>(null)
  const dateDebut = ref<Date | null>(null)
  const dateEcheance = ref<Date | null>(null)
  const excludeId = withExclude ? ref<number | null>(9) : undefined
  const composable = useTacheConflits(collaborateurId, dateDebut, dateEcheance, excludeId)
  return { collaborateurId, dateDebut, dateEcheance, excludeId, ...composable }
}

/** Laisse le watcher se declencher puis ecoule le debounce de 400ms. */
async function ecoulerDebounce() {
  await nextTick()
  await vi.advanceTimersByTimeAsync(400)
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.useFakeTimers()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useTacheConflits — declenchement', () => {
  it('interroge le backend apres le debounce quand collaborateur + date sont renseignes', async () => {
    mockConflits.mockResolvedValue({ data: [conflitBilan] })
    const c = setup()

    c.collaborateurId.value = 5
    c.dateDebut.value = new Date(2026, 2, 10) // 10 mars 2026 (composants locaux)
    await nextTick()

    // Rien avant l'expiration du debounce
    await vi.advanceTimersByTimeAsync(399)
    expect(mockConflits).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1)
    expect(mockConflits).toHaveBeenCalledExactlyOnceWith({
      collaborateur_id: 5,
      date_debut: '2026-03-10',
      date_echeance: null,
      exclude_tache_id: undefined,
    })
    expect(c.conflits.value).toEqual([conflitBilan])
    expect(c.checking.value).toBe(false)
  })

  it("n'appelle pas l'API sans collaborateur, ou sans aucune date", async () => {
    const c = setup()

    // Une date mais pas de collaborateur
    c.dateDebut.value = new Date(2026, 0, 5)
    await ecoulerDebounce()
    expect(mockConflits).not.toHaveBeenCalled()
    expect(c.conflits.value).toEqual([])

    // Un collaborateur mais plus aucune date
    c.collaborateurId.value = 5
    c.dateDebut.value = null
    await ecoulerDebounce()
    expect(mockConflits).not.toHaveBeenCalled()
    expect(c.conflits.value).toEqual([])
    expect(c.checking.value).toBe(false)
  })

  it("vide les conflits si l'API echoue (avertissement non bloquant)", async () => {
    mockConflits.mockRejectedValue(new Error('500'))
    const c = setup()

    c.collaborateurId.value = 5
    c.dateDebut.value = new Date(2026, 2, 10)
    await ecoulerDebounce()

    expect(mockConflits).toHaveBeenCalledOnce()
    expect(c.conflits.value).toEqual([])
    expect(c.checking.value).toBe(false)
  })
})

describe('useTacheConflits — course entre requetes', () => {
  it('ignore la reponse obsolete quand une requete plus recente a deja abouti', async () => {
    let resolvePremiere!: (v: unknown) => void
    mockConflits
      .mockImplementationOnce(() => new Promise(r => { resolvePremiere = r }))
      .mockResolvedValueOnce({ data: [conflitBilan] })
    const c = setup()

    // 1re requete : reste en vol
    c.collaborateurId.value = 5
    c.dateDebut.value = new Date(2026, 2, 1)
    await ecoulerDebounce()
    expect(c.checking.value).toBe(true)

    // 2e requete : resolue immediatement -> resultat applique
    c.dateEcheance.value = new Date(2026, 2, 20)
    await ecoulerDebounce()
    expect(c.conflits.value).toEqual([conflitBilan])
    expect(c.checking.value).toBe(false)

    // La 1re revient trop tard : elle ne doit pas ecraser le resultat
    resolvePremiere({ data: [] })
    await nextTick()
    await nextTick()
    expect(c.conflits.value).toEqual([conflitBilan])
    expect(c.checking.value).toBe(false)
  })
})

describe('useTacheConflits — reset', () => {
  it("reset vide l'etat et annule la verification en attente", async () => {
    mockConflits.mockResolvedValue({ data: [conflitBilan] })
    const c = setup()

    c.collaborateurId.value = 5
    c.dateDebut.value = new Date(2026, 2, 10)
    await ecoulerDebounce()
    expect(c.conflits.value).toHaveLength(1)

    // Nouveau changement -> timer en attente, reset avant expiration
    c.dateDebut.value = new Date(2026, 2, 11)
    await nextTick()
    c.reset()
    expect(c.conflits.value).toEqual([])
    expect(c.checking.value).toBe(false)

    await vi.advanceTimersByTimeAsync(1000)
    expect(mockConflits).toHaveBeenCalledTimes(1) // pas de 2e appel
  })
})

describe('useTacheConflits — exclusion de la tache en edition', () => {
  it("transmet exclude_tache_id et re-verifie quand il change", async () => {
    mockConflits.mockResolvedValue({ data: [] })
    const c = setup(true)

    // Echeance seule (sans date de debut) suffit a declencher la verification
    c.collaborateurId.value = 5
    c.dateEcheance.value = new Date(2026, 3, 1) // 1er avril 2026
    await ecoulerDebounce()

    expect(mockConflits).toHaveBeenLastCalledWith(expect.objectContaining({
      collaborateur_id: 5,
      date_debut: null,
      date_echeance: '2026-04-01',
      exclude_tache_id: 9,
    }))

    // excludeId fait partie des sources observees -> son changement re-verifie
    c.excludeId!.value = null
    await ecoulerDebounce()
    expect(mockConflits).toHaveBeenCalledTimes(2)
    expect(mockConflits.mock.calls.at(-1)![0].exclude_tache_id).toBeUndefined()
  })
})
