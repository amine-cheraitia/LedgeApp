import { describe, it, expect, vi, beforeEach } from 'vitest'

// useEntreprises est representatif du patron CRUD partage par les composables de liste
// (fetch + create/update/delete + filtres). Le tester couvre ce patron.

const { mockAdd } = vi.hoisted(() => ({ mockAdd: vi.fn() }))
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: mockAdd }) }))

const { getAll, create, update, remove } = vi.hoisted(() => ({
  getAll: vi.fn(),
  create: vi.fn(),
  update: vi.fn(),
  remove: vi.fn(),
}))
vi.mock('@/api/modules/entreprises', () => ({
  entreprisesApi: { getAll, create, update, delete: remove },
}))

import { useEntreprises } from '@/composables/useEntreprises'

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useEntreprises — fetch', () => {
  it('peuple entreprises et totalRecords depuis meta.total', async () => {
    getAll.mockResolvedValue({ data: [{ id: 1 }, { id: 2 }], meta: { total: 42 } })
    const c = useEntreprises()

    await c.fetchEntreprises()

    expect(c.entreprises.value).toHaveLength(2)
    expect(c.totalRecords.value).toBe(42)
    expect(c.loading.value).toBe(false)
  })

  it('retombe sur data.length si meta.total est absent', async () => {
    getAll.mockResolvedValue({ data: [{ id: 1 }] })
    const c = useEntreprises()

    await c.fetchEntreprises()

    expect(c.totalRecords.value).toBe(1)
  })

  it('affiche un toast erreur et coupe le loading si le fetch echoue', async () => {
    getAll.mockRejectedValue(new Error('500'))
    const c = useEntreprises()

    await c.fetchEntreprises()

    expect(mockAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }))
    expect(c.loading.value).toBe(false)
  })
})

describe('useEntreprises — mutations', () => {
  it('createEntreprise appelle l API puis refetch', async () => {
    create.mockResolvedValue({ data: { id: 9 } })
    getAll.mockResolvedValue({ data: [], meta: { total: 0 } })
    const c = useEntreprises()

    const created = await c.createEntreprise({ raison_sociale: 'ACME' })

    expect(create).toHaveBeenCalledWith({ raison_sociale: 'ACME' })
    expect(getAll).toHaveBeenCalled()
    expect(created.id).toBe(9)
  })

  it('updateEntreprise appelle l API puis refetch', async () => {
    update.mockResolvedValue({ data: { id: 5 } })
    getAll.mockResolvedValue({ data: [], meta: { total: 0 } })
    const c = useEntreprises()

    await c.updateEntreprise(5, { wilaya: 'Alger' })

    expect(update).toHaveBeenCalledWith(5, { wilaya: 'Alger' })
    expect(getAll).toHaveBeenCalled()
  })

  it('deleteEntreprise appelle l API puis refetch', async () => {
    remove.mockResolvedValue(undefined)
    getAll.mockResolvedValue({ data: [], meta: { total: 0 } })
    const c = useEntreprises()

    await c.deleteEntreprise(3)

    expect(remove).toHaveBeenCalledWith(3)
    expect(getAll).toHaveBeenCalled()
  })
})

describe('useEntreprises — filtres', () => {
  beforeEach(() => {
    getAll.mockResolvedValue({ data: [], meta: { total: 0 } })
  })

  it('onSearch stocke la recherche et remet la page a 1', () => {
    const c = useEntreprises()
    c.filters.value.page = 5

    c.onSearch('acme')

    expect(c.filters.value.search).toBe('acme')
    expect(c.filters.value.page).toBe(1)
  })

  it('onPage convertit l index 0-based en page 1-based', () => {
    const c = useEntreprises()

    c.onPage({ page: 2 })

    expect(c.filters.value.page).toBe(3)
  })

  it('resetFilters revient a page 1 / per_page 15', () => {
    const c = useEntreprises()
    c.filters.value.statut = 'client'

    c.resetFilters()

    expect(c.filters.value).toEqual({ page: 1, per_page: 15 })
  })
})
