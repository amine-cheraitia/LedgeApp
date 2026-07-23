import { describe, it, expect, vi, beforeEach } from 'vitest'

// Couvre la suppression de mission : succes + remontee du message backend en cas
// d'echec (ex: 409 « mission avec factures associees »), pour eviter l'echec silencieux.

const { mockAdd } = vi.hoisted(() => ({ mockAdd: vi.fn() }))
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: mockAdd }) }))

const { getAll, create, update, remove } = vi.hoisted(() => ({
  getAll: vi.fn(),
  create: vi.fn(),
  update: vi.fn(),
  remove: vi.fn(),
}))
vi.mock('@/api/modules/missions', () => ({
  missionsApi: { getAll, create, update, delete: remove },
}))

import { useMissions } from '@/composables/useMissions'

beforeEach(() => {
  vi.clearAllMocks()
  getAll.mockResolvedValue({ data: [], meta: { total: 0 } })
})

describe('useMissions — deleteMission', () => {
  it('supprime puis refetch et affiche un toast succes', async () => {
    remove.mockResolvedValue(undefined)
    const c = useMissions()

    await c.deleteMission(7)

    expect(remove).toHaveBeenCalledWith(7)
    expect(getAll).toHaveBeenCalled()
    expect(mockAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }))
  })

  it('remonte le message backend (409) dans un toast erreur sans planter', async () => {
    remove.mockRejectedValue({ response: { data: { message: 'Impossible de supprimer une mission avec des factures associees.' } } })
    const c = useMissions()

    // Ne doit pas rejeter (l'erreur est geree en interne).
    await expect(c.deleteMission(7)).resolves.toBeUndefined()

    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: 'error',
        detail: 'Impossible de supprimer une mission avec des factures associees.',
      }),
    )
    // Pas de toast succes ni de refetch quand la suppression echoue.
    expect(mockAdd).not.toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }))
  })

  it('utilise un message de repli si le backend ne fournit pas de message', async () => {
    remove.mockRejectedValue(new Error('Network Error'))
    const c = useMissions()

    await c.deleteMission(7)

    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de supprimer la mission.' }),
    )
  })
})
