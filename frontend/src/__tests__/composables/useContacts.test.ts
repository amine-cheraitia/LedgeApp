import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { Contact } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetAll, mockCreate, mockUpdate, mockDelete } = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
  mockCreate: vi.fn(),
  mockUpdate: vi.fn(),
  mockDelete: vi.fn(),
}))

vi.mock('@/api/modules/contacts', () => ({
  contactsApi: {
    getAll: mockGetAll,
    create: mockCreate,
    update: mockUpdate,
    delete: mockDelete,
  },
}))

// ── Mock PrimeVue toast ──────────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import APRES les mocks ───────────────────────────────────────────────────
import { useContacts } from '@/composables/useContacts'

const contactBenali: Contact = {
  id: 7,
  entreprise_id: 1,
  nom: 'Benali',
  prenom: 'Karim',
  email: 'k.benali@etoile.dz',
  telephone: '0551111111',
  poste: 'Gerant',
  est_principal: true,
  created_at: '',
  updated_at: '',
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: [contactBenali] })
})

describe('useContacts — fetchContacts', () => {
  it('charge les contacts de l entreprise et repasse loading a false', async () => {
    const c = useContacts()

    const promise = c.fetchContacts(1)
    expect(c.loading.value).toBe(true)
    await promise

    expect(mockGetAll).toHaveBeenCalledWith(1)
    expect(c.contacts.value).toEqual([contactBenali])
    expect(c.loading.value).toBe(false)
  })

  it('affiche un toast erreur et coupe le loading si le chargement echoue', async () => {
    mockGetAll.mockRejectedValue(new Error('500'))
    const c = useContacts()

    await c.fetchContacts(1)

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les contacts.' }),
    )
    expect(c.contacts.value).toEqual([])
    expect(c.loading.value).toBe(false)
  })
})

describe('useContacts — createContact', () => {
  it('cree le contact, notifie le succes puis refetch la liste', async () => {
    mockCreate.mockResolvedValue({ data: { ...contactBenali, id: 8, nom: 'Ziani' } })
    const c = useContacts()

    const created = await c.createContact(1, { nom: 'Ziani', est_principal: false })

    expect(mockCreate).toHaveBeenCalledWith(1, { nom: 'Ziani', est_principal: false })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Contact ajoute.' }),
    )
    expect(mockGetAll).toHaveBeenCalledWith(1)
    expect(created.id).toBe(8)
    expect(c.contacts.value).toEqual([contactBenali])
  })

  it('propage l erreur API sans toast succes ni refetch', async () => {
    mockCreate.mockRejectedValue(new Error('422'))
    const c = useContacts()

    await expect(c.createContact(1, { nom: 'X' })).rejects.toThrow('422')

    expect(toastAdd).not.toHaveBeenCalled()
    expect(mockGetAll).not.toHaveBeenCalled()
  })
})

describe('useContacts — updateContact', () => {
  it('met a jour le contact, notifie le succes puis refetch la liste', async () => {
    mockUpdate.mockResolvedValue({ data: { ...contactBenali, poste: 'DAF' } })
    const c = useContacts()

    const updated = await c.updateContact(1, 7, { poste: 'DAF' })

    expect(mockUpdate).toHaveBeenCalledWith(1, 7, { poste: 'DAF' })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Contact mis a jour.' }),
    )
    expect(mockGetAll).toHaveBeenCalledWith(1)
    expect(updated.poste).toBe('DAF')
  })
})

describe('useContacts — deleteContact', () => {
  it('supprime le contact, notifie le succes puis refetch la liste', async () => {
    mockDelete.mockResolvedValue(undefined)
    const c = useContacts()

    await c.deleteContact(1, 7)

    expect(mockDelete).toHaveBeenCalledWith(1, 7)
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Contact supprime.' }),
    )
    expect(mockGetAll).toHaveBeenCalledWith(1)
  })
})
