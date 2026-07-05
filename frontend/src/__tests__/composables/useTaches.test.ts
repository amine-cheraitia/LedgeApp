import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { Tache } from '@/types'

// ── Mocks (hoistes) ──────────────────────────────────────────────────────────
const { mockAdd } = vi.hoisted(() => ({ mockAdd: vi.fn() }))
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: mockAdd }) }))

const { getAll, create, update, remove } = vi.hoisted(() => ({
  getAll: vi.fn(),
  create: vi.fn(),
  update: vi.fn(),
  remove: vi.fn(),
}))
vi.mock('@/api/modules/taches', () => ({
  tachesApi: { getAll, create, update, delete: remove },
}))

import { useTaches } from '@/composables/useTaches'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const tache: Tache = {
  id: 3,
  mission_id: 5,
  assigned_to: 2,
  titre: 'Saisie comptable',
  description: null,
  statut: 'a_faire',
  date_debut: '2026-03-01',
  date_echeance: '2026-03-15',
  priorite: 2,
  created_at: '2026-02-01T10:00:00.000000Z',
  updated_at: '2026-02-01T10:00:00.000000Z',
}

beforeEach(() => {
  vi.clearAllMocks()
  getAll.mockResolvedValue({ data: [tache] })
})

describe('useTaches — fetchTaches', () => {
  it('charge les tâches de la mission et bascule loading', async () => {
    const c = useTaches(5)
    expect(c.loading.value).toBe(false)

    const promise = c.fetchTaches()
    expect(c.loading.value).toBe(true)
    await promise

    expect(getAll).toHaveBeenCalledWith(5)
    expect(c.taches.value).toEqual([tache])
    expect(c.loading.value).toBe(false)
  })

  it('affiche un toast erreur si le chargement échoue', async () => {
    getAll.mockRejectedValue(new Error('500'))
    const c = useTaches(5)

    await c.fetchTaches()

    expect(c.taches.value).toEqual([])
    expect(c.loading.value).toBe(false)
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les taches.' }),
    )
  })
})

describe('useTaches — createTache', () => {
  it('crée la tâche sur la bonne mission, toast succès, refetch et retourne la ressource', async () => {
    create.mockResolvedValue({ data: tache })
    const c = useTaches(5)

    const result = await c.createTache({ titre: 'Saisie comptable', priorite: 2 })

    expect(create).toHaveBeenCalledWith(5, { titre: 'Saisie comptable', priorite: 2 })
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Tache creee.' }),
    )
    expect(getAll).toHaveBeenCalledWith(5)
    expect(result).toEqual(tache)
    expect(c.taches.value).toEqual([tache])
  })

  it("propage l'erreur de création (gérée par l'appelant) sans toast succès ni refetch", async () => {
    create.mockRejectedValue(new Error('422'))
    const c = useTaches(5)

    await expect(c.createTache({ titre: 'x' })).rejects.toThrow('422')

    expect(mockAdd).not.toHaveBeenCalled()
    expect(getAll).not.toHaveBeenCalled()
  })
})

describe('useTaches — updateTache', () => {
  it('met à jour la tâche, toast succès, refetch et retourne la ressource', async () => {
    const modifiee = { ...tache, statut: 'en_cours' as const }
    update.mockResolvedValue({ data: modifiee })
    const c = useTaches(5)

    const result = await c.updateTache(3, { statut: 'en_cours' })

    expect(update).toHaveBeenCalledWith(5, 3, { statut: 'en_cours' })
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Tache mise a jour.' }),
    )
    expect(getAll).toHaveBeenCalledWith(5)
    expect(result).toEqual(modifiee)
  })
})

describe('useTaches — deleteTache', () => {
  it('supprime la tâche, toast succès et refetch', async () => {
    remove.mockResolvedValue(undefined)
    const c = useTaches(5)

    await c.deleteTache(3)

    expect(remove).toHaveBeenCalledWith(5, 3)
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Tache supprimee.' }),
    )
    expect(getAll).toHaveBeenCalledWith(5)
  })
})
