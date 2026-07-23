import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { TacheCommentaire } from '@/types'

// ── Mocks (hoistes) ──────────────────────────────────────────────────────────
const { mockAdd } = vi.hoisted(() => ({ mockAdd: vi.fn() }))
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: mockAdd }) }))

const { getAll, create, update, remove } = vi.hoisted(() => ({
  getAll: vi.fn(),
  create: vi.fn(),
  update: vi.fn(),
  remove: vi.fn(),
}))
vi.mock('@/api/modules/commentaires', () => ({
  commentairesApi: { getAll, create, update, delete: remove },
}))

import { useCommentaires } from '@/composables/useCommentaires'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const commentaire: TacheCommentaire = {
  id: 11,
  tache_id: 3,
  user_id: 1,
  contenu: 'Premier commentaire',
  visible_portail: false,
  user: { id: 1, name: 'Test User' },
  created_at: '2026-03-02T09:30:00.000000Z',
  updated_at: '2026-03-02T09:30:00.000000Z',
}

beforeEach(() => {
  vi.clearAllMocks()
  getAll.mockResolvedValue({ data: [commentaire] })
})

describe('useCommentaires — fetchCommentaires', () => {
  it('charge les commentaires de la tâche et bascule loading', async () => {
    const c = useCommentaires()
    expect(c.loading.value).toBe(false)

    const promise = c.fetchCommentaires(3)
    expect(c.loading.value).toBe(true)
    await promise

    expect(getAll).toHaveBeenCalledWith(3)
    expect(c.commentaires.value).toEqual([commentaire])
    expect(c.loading.value).toBe(false)
  })

  it('affiche un toast erreur si le chargement échoue', async () => {
    getAll.mockRejectedValue(new Error('500'))
    const c = useCommentaires()

    await c.fetchCommentaires(3)

    expect(c.commentaires.value).toEqual([])
    expect(c.loading.value).toBe(false)
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les commentaires.' }),
    )
  })
})

describe('useCommentaires — createCommentaire', () => {
  it('crée le commentaire, toast succès, refetch et retourne la ressource', async () => {
    create.mockResolvedValue({ data: commentaire })
    const c = useCommentaires()

    const result = await c.createCommentaire(3, 'Premier commentaire')

    expect(create).toHaveBeenCalledWith(3, 'Premier commentaire')
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Commentaire ajouté.' }),
    )
    expect(getAll).toHaveBeenCalledWith(3)
    expect(result).toEqual(commentaire)
    expect(c.commentaires.value).toEqual([commentaire])
  })

  it("retourne null et toast erreur si l'ajout échoue (pas de refetch)", async () => {
    create.mockRejectedValue(new Error('422'))
    const c = useCommentaires()

    const result = await c.createCommentaire(3, 'x')

    expect(result).toBeNull()
    expect(getAll).not.toHaveBeenCalled()
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: "Impossible d'ajouter le commentaire." }),
    )
  })
})

describe('useCommentaires — updateCommentaire', () => {
  it('modifie le commentaire, toast succès et refetch', async () => {
    update.mockResolvedValue({ data: commentaire })
    const c = useCommentaires()

    await c.updateCommentaire(3, 11, 'Contenu corrigé')

    expect(update).toHaveBeenCalledWith(3, 11, 'Contenu corrigé')
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Commentaire modifié.' }),
    )
    expect(getAll).toHaveBeenCalledWith(3)
  })

  it('toast erreur si la modification échoue, sans planter', async () => {
    update.mockRejectedValue(new Error('403'))
    const c = useCommentaires()

    await expect(c.updateCommentaire(3, 11, 'x')).resolves.toBeUndefined()

    expect(getAll).not.toHaveBeenCalled()
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de modifier le commentaire.' }),
    )
  })
})

describe('useCommentaires — deleteCommentaire', () => {
  it('supprime le commentaire, toast succès et refetch', async () => {
    remove.mockResolvedValue(undefined)
    const c = useCommentaires()

    await c.deleteCommentaire(3, 11)

    expect(remove).toHaveBeenCalledWith(3, 11)
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Commentaire supprimé.' }),
    )
    expect(getAll).toHaveBeenCalledWith(3)
  })

  it('toast erreur si la suppression échoue, sans planter', async () => {
    remove.mockRejectedValue(new Error('403'))
    const c = useCommentaires()

    await expect(c.deleteCommentaire(3, 11)).resolves.toBeUndefined()

    expect(getAll).not.toHaveBeenCalled()
    expect(mockAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de supprimer le commentaire.' }),
    )
  })
})
