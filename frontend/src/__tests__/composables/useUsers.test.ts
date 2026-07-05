import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { User } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetAll, mockCreate, mockUpdate, mockDelete, mockRenvoyer } = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
  mockCreate: vi.fn(),
  mockUpdate: vi.fn(),
  mockDelete: vi.fn(),
  mockRenvoyer: vi.fn(),
}))

vi.mock('@/api/modules/users', () => ({
  usersApi: {
    getAll: mockGetAll,
    create: mockCreate,
    update: mockUpdate,
    delete: mockDelete,
    renvoyerInvitation: mockRenvoyer,
  },
}))

// ── Mock PrimeVue toast ──────────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import APRES les mocks ───────────────────────────────────────────────────
import { useUsers } from '@/composables/useUsers'

const userKarim: User = {
  id: 7,
  name: 'Karim Benali',
  email: 'k.benali@ledge.dz',
  entreprise_id: null,
  portail_actif: false,
  email_verified_at: null,
  roles: ['collaborateur'],
  created_at: '2026-01-10T00:00:00.000000Z',
  updated_at: '2026-01-10T00:00:00.000000Z',
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: [userKarim], meta: { total: 42 } })
})

describe('useUsers — fetchUsers', () => {
  it('charge les utilisateurs avec les filtres par defaut et lit le total dans meta', async () => {
    const u = useUsers()

    const promise = u.fetchUsers()
    expect(u.loading.value).toBe(true)
    await promise

    expect(mockGetAll).toHaveBeenCalledWith({ page: 1, per_page: 15, search: undefined })
    expect(u.users.value).toEqual([userKarim])
    expect(u.totalRecords.value).toBe(42)
    expect(u.loading.value).toBe(false)
  })

  it('retombe sur data.length quand la reponse ne contient pas de meta', async () => {
    mockGetAll.mockResolvedValue({ data: [userKarim] })
    const u = useUsers()

    await u.fetchUsers()

    expect(u.totalRecords.value).toBe(1)
  })

  it('passe la recherche fournie en override', async () => {
    const u = useUsers()

    await u.fetchUsers({ search: 'karim' })

    expect(mockGetAll).toHaveBeenCalledWith(expect.objectContaining({ search: 'karim' }))
  })

  it('affiche un toast erreur et coupe le loading si le chargement echoue', async () => {
    mockGetAll.mockRejectedValue(new Error('500'))
    const u = useUsers()

    await u.fetchUsers()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les utilisateurs.' }),
    )
    expect(u.users.value).toEqual([])
    expect(u.loading.value).toBe(false)
  })
})

describe('useUsers — createUser', () => {
  it('cree l utilisateur, notifie le succes, refetch et renvoie la reponse (invitation_url)', async () => {
    mockCreate.mockResolvedValue({ data: { ...userKarim, id: 8 }, invitation_url: 'https://ledge.dz/definir-mot-de-passe?token=abc' })
    const u = useUsers()

    const res = await u.createUser({ name: 'Sara', email: 'sara@ledge.dz', role: 'secretaire' })

    expect(mockCreate).toHaveBeenCalledWith({ name: 'Sara', email: 'sara@ledge.dz', role: 'secretaire' })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Utilisateur cree. Invitation envoyee.' }),
    )
    expect(mockGetAll).toHaveBeenCalledOnce()
    expect(res.invitation_url).toContain('token=abc')
  })

  it('propage l erreur et affiche le message API en toast', async () => {
    const erreur = { response: { data: { message: 'Cet email est deja utilise.' } } }
    mockCreate.mockRejectedValue(erreur)
    const u = useUsers()

    await expect(u.createUser({ name: 'X', email: 'x@x.dz', role: 'admin' })).rejects.toBe(erreur)

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Cet email est deja utilise.' }),
    )
    expect(mockGetAll).not.toHaveBeenCalled()
  })

  it('utilise le message generique quand l erreur ne porte pas de message API', async () => {
    mockCreate.mockRejectedValue(new Error('reseau'))
    const u = useUsers()

    await expect(u.createUser({ name: 'X', email: 'x@x.dz', role: 'admin' })).rejects.toThrow('reseau')

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Erreur lors de la creation.' }),
    )
  })
})

describe('useUsers — resendInvitation', () => {
  it('renvoie l invitation et retourne le nouveau lien', async () => {
    mockRenvoyer.mockResolvedValue({ message: 'ok', invitation_url: 'https://ledge.dz/definir-mot-de-passe?token=neuf' })
    const u = useUsers()

    const res = await u.resendInvitation(7)

    expect(mockRenvoyer).toHaveBeenCalledWith(7)
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Invitation renvoyee.' }),
    )
    expect(res.invitation_url).toContain('token=neuf')
  })

  it('propage l erreur avec le message generique par defaut', async () => {
    mockRenvoyer.mockRejectedValue(new Error('500'))
    const u = useUsers()

    await expect(u.resendInvitation(7)).rejects.toThrow('500')

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: "Erreur lors de l'envoi de l'invitation." }),
    )
  })
})

describe('useUsers — updateUser', () => {
  it('met a jour, notifie le succes, refetch et renvoie le user', async () => {
    mockUpdate.mockResolvedValue({ data: { ...userKarim, name: 'Karim B.' } })
    const u = useUsers()

    const updated = await u.updateUser(7, { name: 'Karim B.' })

    expect(mockUpdate).toHaveBeenCalledWith(7, { name: 'Karim B.' })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Utilisateur mis a jour.' }),
    )
    expect(mockGetAll).toHaveBeenCalledOnce()
    expect(updated.name).toBe('Karim B.')
  })

  it('propage l erreur avec le message API', async () => {
    mockUpdate.mockRejectedValue({ response: { data: { message: 'Role invalide.' } } })
    const u = useUsers()

    await expect(u.updateUser(7, { role: 'x' })).rejects.toBeTruthy()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Role invalide.' }),
    )
    expect(mockGetAll).not.toHaveBeenCalled()
  })
})
