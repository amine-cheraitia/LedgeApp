import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import type { Facture } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetAll, mockCreate, mockDelete, mockCreatePaiement, mockTransmettre, mockGetPdf } = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
  mockCreate: vi.fn(),
  mockDelete: vi.fn(),
  mockCreatePaiement: vi.fn(),
  mockTransmettre: vi.fn(),
  mockGetPdf: vi.fn(),
}))

vi.mock('@/api/modules/factures', () => ({
  facturesApi: {
    getAll: mockGetAll,
    create: mockCreate,
    delete: mockDelete,
    createPaiement: mockCreatePaiement,
    transmettre: mockTransmettre,
    getPdf: mockGetPdf,
  },
}))

// ── Mock PrimeVue toast ──────────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import du composable APRES les mocks ─────────────────────────────────────
import { useFactures } from '@/composables/useFactures'

const facture = { id: 1, numero: 'FF2026-001' } as Facture

const origCreateObjectURL = (URL as any).createObjectURL
const origRevokeObjectURL = (URL as any).revokeObjectURL

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: [facture], meta: { total: 42 } })
})

afterEach(() => {
  vi.useRealTimers()
  ;(URL as any).createObjectURL = origCreateObjectURL
  ;(URL as any).revokeObjectURL = origRevokeObjectURL
})

describe('useFactures — fetchFactures', () => {
  it('charge les factures et lit le total dans meta', async () => {
    const c = useFactures()

    const pending = c.fetchFactures()
    expect(c.loading.value).toBe(true)
    await pending

    expect(c.loading.value).toBe(false)
    expect(c.factures.value).toEqual([facture])
    expect(c.totalRecords.value).toBe(42)
    expect(mockGetAll).toHaveBeenCalledWith({ page: 1, per_page: 15, search: undefined })
  })

  it('retombe sur la longueur des donnees quand meta est absent', async () => {
    mockGetAll.mockResolvedValue({ data: [facture, { ...facture, id: 2 }] })
    const c = useFactures()

    await c.fetchFactures()

    expect(c.totalRecords.value).toBe(2)
  })

  it('normalise une recherche vide en undefined', async () => {
    const c = useFactures()
    c.filters.value.search = ''

    await c.fetchFactures()

    expect(mockGetAll).toHaveBeenCalledWith(expect.objectContaining({ search: undefined }))
  })

  it("affiche un toast erreur si l'API echoue, sans rester bloque en loading", async () => {
    mockGetAll.mockRejectedValue(new Error('500'))
    const c = useFactures()

    await c.fetchFactures()

    expect(c.loading.value).toBe(false)
    expect(c.factures.value).toEqual([])
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les factures.' }),
    )
  })
})

describe('useFactures — createFacture', () => {
  it('cree la facture, toast succes puis refetch', async () => {
    mockCreate.mockResolvedValue({ data: { id: 9 } })
    const c = useFactures()

    const created = await c.createFacture({ mission_id: 5, date_facture: '2026-03-15', type_tva: 'standard' })

    expect(mockCreate).toHaveBeenCalledWith({ mission_id: 5, date_facture: '2026-03-15', type_tva: 'standard' })
    expect(created).toEqual({ id: 9 })
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Facture creee.' }))
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })

  it("laisse remonter l'erreur de creation (geree par la page)", async () => {
    mockCreate.mockRejectedValue({ response: { data: { message: 'Exercice clos.' } } })
    const c = useFactures()

    await expect(c.createFacture({ mission_id: 5, date_facture: '2026-03-15' })).rejects.toBeTruthy()

    expect(toastAdd).not.toHaveBeenCalled()
    expect(mockGetAll).not.toHaveBeenCalled()
  })
})

describe('useFactures — deleteFacture', () => {
  it('supprime, toast succes puis refetch', async () => {
    mockDelete.mockResolvedValue(undefined)
    const c = useFactures()

    await c.deleteFacture(7)

    expect(mockDelete).toHaveBeenCalledWith(7)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Facture supprimee.' }))
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })

  it("laisse remonter l'erreur 409 (popup geree par la page)", async () => {
    mockDelete.mockRejectedValue({ response: { status: 409, data: { message: 'Pas la derniere facture.' } } })
    const c = useFactures()

    await expect(c.deleteFacture(7)).rejects.toBeTruthy()

    expect(toastAdd).not.toHaveBeenCalled()
    expect(mockGetAll).not.toHaveBeenCalled()
  })
})

describe('useFactures — addPaiement', () => {
  it('enregistre le paiement, toast succes puis refetch', async () => {
    mockCreatePaiement.mockResolvedValue({ data: { id: 3 } })
    const c = useFactures()

    const paiement = await c.addPaiement(1, { montant: 1000, date_paiement: '2026-03-20', mode_paiement: 'virement' })

    expect(mockCreatePaiement).toHaveBeenCalledWith(1, expect.objectContaining({ montant: 1000, mode_paiement: 'virement' }))
    expect(paiement).toEqual({ id: 3 })
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Paiement enregistre.' }))
    expect(mockGetAll).toHaveBeenCalledTimes(1)
  })
})

describe('useFactures — transmettreFacture', () => {
  it('transmet la facture et affiche un toast succes', async () => {
    mockTransmettre.mockResolvedValue({ message: 'ok' })
    const c = useFactures()

    await c.transmettreFacture(4)

    expect(mockTransmettre).toHaveBeenCalledWith(4)
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Facture transmise au client par mail.' }),
    )
  })

  it("toast erreur + rethrow si la transmission echoue", async () => {
    mockTransmettre.mockRejectedValue(new Error('SMTP down'))
    const c = useFactures()

    await expect(c.transmettreFacture(4)).rejects.toBeTruthy()

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }))
    expect(toastAdd).not.toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }))
  })
})
