import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import type { Devis } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const {
  mockGetAll, mockCreate, mockUpdate, mockDelete,
  mockEnvoyer, mockAccepter, mockRefuser, mockConvertir, mockGetPdf,
} = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
  mockCreate: vi.fn(),
  mockUpdate: vi.fn(),
  mockDelete: vi.fn(),
  mockEnvoyer: vi.fn(),
  mockAccepter: vi.fn(),
  mockRefuser: vi.fn(),
  mockConvertir: vi.fn(),
  mockGetPdf: vi.fn(),
}))

vi.mock('@/api/modules/devis', () => ({
  devisApi: {
    getAll: mockGetAll,
    create: mockCreate,
    update: mockUpdate,
    delete: mockDelete,
    envoyer: mockEnvoyer,
    accepter: mockAccepter,
    refuser: mockRefuser,
    convertirEnMission: mockConvertir,
    getPdf: mockGetPdf,
  },
}))

// ── Mock PrimeVue toast ──────────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import du composable APRES les mocks ─────────────────────────────────────
import { useDevis } from '@/composables/useDevis'

const devis = { id: 1, numero: 'DV2026-001' } as Devis

const origCreateObjectURL = (URL as any).createObjectURL
const origRevokeObjectURL = (URL as any).revokeObjectURL

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: [devis], meta: { total: 37 } })
})

afterEach(() => {
  vi.useRealTimers()
  ;(URL as any).createObjectURL = origCreateObjectURL
  ;(URL as any).revokeObjectURL = origRevokeObjectURL
})

describe('useDevis — fetchDevis', () => {
  it('charge les devis et lit le total dans meta', async () => {
    const c = useDevis()

    const pending = c.fetchDevis()
    expect(c.loading.value).toBe(true)
    await pending

    expect(c.loading.value).toBe(false)
    expect(c.devisList.value).toEqual([devis])
    expect(c.totalRecords.value).toBe(37)
    expect(mockGetAll).toHaveBeenCalledWith({ page: 1, per_page: 15, search: undefined })
  })

  it('retombe sur la longueur des donnees quand meta est absent', async () => {
    mockGetAll.mockResolvedValue({ data: [devis, { ...devis, id: 2 }] })
    const c = useDevis()

    await c.fetchDevis()

    expect(c.totalRecords.value).toBe(2)
  })

  it('normalise une recherche vide en undefined', async () => {
    const c = useDevis()
    c.filters.value.search = ''

    await c.fetchDevis()

    expect(mockGetAll).toHaveBeenCalledWith(expect.objectContaining({ search: undefined }))
  })

  it("toast erreur si l'API echoue, sans rester bloque en loading", async () => {
    mockGetAll.mockRejectedValue(new Error('500'))
    const c = useDevis()

    await c.fetchDevis()

    expect(c.loading.value).toBe(false)
    expect(c.devisList.value).toEqual([])
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les devis.' }),
    )
  })
})

describe('useDevis — createDevis', () => {
  it('cree le devis, toast succes puis refetch', async () => {
    mockCreate.mockResolvedValue({ data: { id: 9 } })
    const c = useDevis()

    const created = await c.createDevis({
      entreprise_id: 1, prestation_id: 2, date_devis: '2026-03-15', date_validite: '2026-05-15', type_tva: 'standard',
    })

    expect(mockCreate).toHaveBeenCalledWith(expect.objectContaining({ entreprise_id: 1, prestation_id: 2 }))
    expect(created).toEqual({ id: 9 })
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Devis cree.' }))
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })

  it("laisse remonter l'erreur de creation (geree par la page)", async () => {
    mockCreate.mockRejectedValue(new Error('422'))
    const c = useDevis()

    await expect(
      c.createDevis({ entreprise_id: 1, prestation_id: 2, date_devis: '2026-03-15', date_validite: '2026-05-15' }),
    ).rejects.toBeTruthy()

    expect(toastAdd).not.toHaveBeenCalled()
    expect(mockGetAll).not.toHaveBeenCalled()
  })
})

describe('useDevis — cycle de vie', () => {
  it('envoyerDevis : toast succes + refetch', async () => {
    mockEnvoyer.mockResolvedValue({ data: { ...devis, statut: 'envoye' } })
    const c = useDevis()

    const sent = await c.envoyerDevis(1)

    expect(mockEnvoyer).toHaveBeenCalledWith(1)
    expect(sent).toEqual(expect.objectContaining({ statut: 'envoye' }))
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Devis envoye au client par mail.' }),
    )
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })

  it("envoyerDevis : toast erreur + rethrow si l'envoi echoue", async () => {
    mockEnvoyer.mockRejectedValue(new Error('SMTP down'))
    const c = useDevis()

    await expect(c.envoyerDevis(1)).rejects.toBeTruthy()

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }))
    expect(toastAdd).not.toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }))
    expect(mockGetAll).not.toHaveBeenCalled()
  })

  it('accepterDevis : toast succes + refetch', async () => {
    mockAccepter.mockResolvedValue({ data: { ...devis, statut: 'accepte' } })
    const c = useDevis()

    const accepted = await c.accepterDevis(1)

    expect(mockAccepter).toHaveBeenCalledWith(1)
    expect(accepted).toEqual(expect.objectContaining({ statut: 'accepte' }))
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Devis accepte.' }))
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })

  it('refuserDevis : toast succes + refetch', async () => {
    mockRefuser.mockResolvedValue({ data: { ...devis, statut: 'refuse' } })
    const c = useDevis()

    await c.refuserDevis(1)

    expect(mockRefuser).toHaveBeenCalledWith(1)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Devis refuse.' }))
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })

  it('convertirDevisEnMission : envoie le payload, toast succes + refetch', async () => {
    mockConvertir.mockResolvedValue({ data: { id: 50, reference: 'M2026-001' } })
    const c = useDevis()

    const mission = await c.convertirDevisEnMission(1, {
      date_debut: '2026-07-01', date_fin: null, collaborateur_ids: [4, 5],
    })

    expect(mockConvertir).toHaveBeenCalledWith(1, { date_debut: '2026-07-01', date_fin: null, collaborateur_ids: [4, 5] })
    expect(mission).toEqual(expect.objectContaining({ reference: 'M2026-001' }))
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Mission creee depuis le devis.' }),
    )
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })
})

describe('useDevis — telechargerPdf', () => {
  it('telecharge le PDF via un lien temporaire nomme devis-{numero}.pdf', async () => {
    mockGetPdf.mockResolvedValue(new Blob(['%PDF']))
    const createObjectURL = vi.fn(() => 'blob:mock')
    const revokeObjectURL = vi.fn()
    ;(URL as any).createObjectURL = createObjectURL
    ;(URL as any).revokeObjectURL = revokeObjectURL

    const anchor = document.createElement('a')
    anchor.click = vi.fn()
    const createSpy = vi.spyOn(document, 'createElement').mockReturnValue(anchor as any)

    const c = useDevis()
    await c.telechargerPdf(3, 'DV2026-003')
    createSpy.mockRestore()

    expect(mockGetPdf).toHaveBeenCalledWith(3)
    expect(anchor.download).toBe('devis-DV2026-003.pdf')
    expect(anchor.click).toHaveBeenCalledOnce()
    expect(createObjectURL).toHaveBeenCalledOnce()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:mock')
    expect(toastAdd).not.toHaveBeenCalled()
  })

  it('toast erreur si la generation du PDF echoue', async () => {
    mockGetPdf.mockRejectedValue(new Error('500'))
    const c = useDevis()

    await c.telechargerPdf(3, 'DV2026-003')

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de generer le PDF.' }),
    )
  })
})

describe('useDevis — updateDevis / deleteDevis', () => {
  it('updateDevis : met a jour, toast succes puis refetch', async () => {
    mockUpdate.mockResolvedValue({ data: devis })
    const c = useDevis()

    await c.updateDevis(1, { entreprise_id: 2, date_devis: '2026-04-01' })

    expect(mockUpdate).toHaveBeenCalledWith(1, { entreprise_id: 2, date_devis: '2026-04-01' })
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Devis mis a jour.' }))
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })

  it('deleteDevis : supprime, toast succes puis refetch', async () => {
    mockDelete.mockResolvedValue(undefined)
    const c = useDevis()

    await c.deleteDevis(7)

    expect(mockDelete).toHaveBeenCalledWith(7)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Devis supprime.' }))
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })
})

describe('useDevis — pagination, recherche, tri, filtre exercice', () => {
  it('onPage passe a la page demandee (index 0 -> page API 1-based)', () => {
    const c = useDevis()

    c.onPage({ page: 2 })

    expect(c.filters.value.page).toBe(3)
    expect(mockGetAll).toHaveBeenCalledWith(expect.objectContaining({ page: 3 }))
  })

  it('onSearch debounce 300ms et ne garde que la derniere saisie', () => {
    vi.useFakeTimers()
    const c = useDevis()
    c.filters.value.page = 4

    c.onSearch('DV')
    c.onSearch('DV2026')
    expect(mockGetAll).not.toHaveBeenCalled()

    vi.advanceTimersByTime(300)

    expect(mockGetAll).toHaveBeenCalledTimes(1)
    expect(mockGetAll).toHaveBeenCalledWith(expect.objectContaining({ search: 'DV2026', page: 1 }))
    expect(c.filters.value.search).toBe('DV2026')
  })

  it('onSort applique champ + direction et revient page 1', () => {
    const c = useDevis()
    c.filters.value.page = 2

    c.onSort({ sortField: 'numero', sortOrder: 1 })

    expect(c.filters.value.sort_field).toBe('numero')
    expect(c.filters.value.sort_direction).toBe('asc')
    expect(c.filters.value.page).toBe(1)
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })

  it('onSort ignore un sortField non-string et retombe en desc', () => {
    const c = useDevis()

    c.onSort({ sortField: (item: unknown) => String(item), sortOrder: -1 })

    expect(c.filters.value.sort_field).toBeUndefined()
    expect(c.filters.value.sort_direction).toBe('desc')
  })

  it('setExercice filtre par exercice et revient page 1', () => {
    const c = useDevis()
    c.filters.value.page = 5

    c.setExercice(3)

    expect(c.filters.value.exercice_id).toBe(3)
    expect(c.filters.value.page).toBe(1)
    expect(mockGetAll).toHaveBeenCalledWith(expect.objectContaining({ exercice_id: 3, page: 1 }))
  })
})
