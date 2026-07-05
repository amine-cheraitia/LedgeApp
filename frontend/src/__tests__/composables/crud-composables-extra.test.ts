import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { TvaTaux } from '@/types'

// ── Mock toast partage ───────────────────────────────────────────────────────
const { mockAdd } = vi.hoisted(() => ({ mockAdd: vi.fn() }))
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: mockAdd }) }))

// ── Mocks des modules API ────────────────────────────────────────────────────
const { tvaGetAll, tvaCreate, tvaUpdate, tvaDelete } = vi.hoisted(() => ({
  tvaGetAll: vi.fn(),
  tvaCreate: vi.fn(),
  tvaUpdate: vi.fn(),
  tvaDelete: vi.fn(),
}))
vi.mock('@/api/modules/referentiels', () => ({
  referentielsApi: {
    getAllTvaTaux: tvaGetAll,
    createTvaTaux: tvaCreate,
    updateTvaTaux: tvaUpdate,
    deleteTvaTaux: tvaDelete,
  },
}))

const { exGetAll, exGetCurrent, exCreate, exUpdate } = vi.hoisted(() => ({
  exGetAll: vi.fn(),
  exGetCurrent: vi.fn(),
  exCreate: vi.fn(),
  exUpdate: vi.fn(),
}))
vi.mock('@/api/modules/exercices', () => ({
  exercicesApi: {
    getAll: exGetAll,
    getCurrent: exGetCurrent,
    create: exCreate,
    update: exUpdate,
  },
}))

const { setGetAll, setUpdate } = vi.hoisted(() => ({
  setGetAll: vi.fn(),
  setUpdate: vi.fn(),
}))
vi.mock('@/api/modules/settings', () => ({
  settingsApi: { getAll: setGetAll, update: setUpdate },
}))

import { useTvaTaux } from '@/composables/useTvaTaux'

beforeEach(() => {
  vi.clearAllMocks()
})

// ─────────────────────────────────────────────────────────────────────────────
// useTvaTaux
// ─────────────────────────────────────────────────────────────────────────────
function makeTaux(overrides: Partial<TvaTaux>): TvaTaux {
  return {
    id: 1, taux: 19, designation: 'TVA', type: 'standard',
    date_debut: '2020-01-01', date_fin: null, actif: true,
    created_at: '', updated_at: '',
    ...overrides,
  }
}

describe('useTvaTaux — fetch', () => {
  it('peuple taux et coupe le loading', async () => {
    tvaGetAll.mockResolvedValue({ data: [makeTaux({ id: 1 })] })
    const c = useTvaTaux()

    await c.fetchTaux()

    expect(c.taux.value).toHaveLength(1)
    expect(c.loading.value).toBe(false)
  })

  it('affiche un toast erreur si le fetch echoue', async () => {
    tvaGetAll.mockRejectedValue(new Error('500'))
    const c = useTvaTaux()

    await c.fetchTaux()

    expect(mockAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }))
    expect(c.loading.value).toBe(false)
  })
})

describe('useTvaTaux — mutations', () => {
  beforeEach(() => {
    tvaGetAll.mockResolvedValue({ data: [] })
  })

  it('createTaux appelle l API, toaste succes, refetch et renvoie la donnee', async () => {
    tvaCreate.mockResolvedValue({ data: makeTaux({ id: 9 }) })
    const c = useTvaTaux()

    const created = await c.createTaux({ taux: 19, designation: 'TVA', type: 'standard', date_debut: '2026-01-01' })

    expect(tvaCreate).toHaveBeenCalledWith(expect.objectContaining({ designation: 'TVA' }))
    expect(mockAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }))
    expect(tvaGetAll).toHaveBeenCalled()
    expect(created.id).toBe(9)
  })

  it('createTaux relance l erreur et toaste en echec', async () => {
    tvaCreate.mockRejectedValue(new Error('422'))
    const c = useTvaTaux()

    await expect(c.createTaux({ taux: 19, designation: 'X', type: 'standard', date_debut: '2026-01-01' }))
      .rejects.toThrow('422')
    expect(mockAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }))
    expect(tvaGetAll).not.toHaveBeenCalled()
  })

  it('updateTaux appelle l API puis refetch', async () => {
    tvaUpdate.mockResolvedValue({ data: makeTaux({ id: 5 }) })
    const c = useTvaTaux()

    await c.updateTaux(5, { designation: 'Maj' })

    expect(tvaUpdate).toHaveBeenCalledWith(5, { designation: 'Maj' })
    expect(tvaGetAll).toHaveBeenCalled()
  })

  it('updateTaux relance l erreur en echec', async () => {
    tvaUpdate.mockRejectedValue(new Error('422'))
    const c = useTvaTaux()

    await expect(c.updateTaux(5, {})).rejects.toThrow('422')
    expect(mockAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }))
  })

  it('deleteTaux supprime puis refetch', async () => {
    tvaDelete.mockResolvedValue(undefined)
    const c = useTvaTaux()

    await c.deleteTaux(3)

    expect(tvaDelete).toHaveBeenCalledWith(3)
    expect(tvaGetAll).toHaveBeenCalled()
  })

  it('deleteTaux ne relance pas l erreur mais toaste (protection suppression cote API)', async () => {
    tvaDelete.mockRejectedValue(new Error('409'))
    const c = useTvaTaux()

    await expect(c.deleteTaux(3)).resolves.toBeUndefined()
    expect(mockAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }))
    expect(tvaGetAll).not.toHaveBeenCalled()
  })
})

describe('useTvaTaux — tauxEnVigueur (miroir de TvaTaux::enVigueurLe)', () => {
  function composableAvecTaux(liste: TvaTaux[]) {
    const c = useTvaTaux()
    c.taux.value = liste
    return c
  }

  it('renvoie null sans date', () => {
    const c = composableAvecTaux([makeTaux({})])
    expect(c.tauxEnVigueur('standard', null)).toBeNull()
  })

  it('resout le taux actif du bon type a une date string (TVA historisee, jamais now)', () => {
    const c = composableAvecTaux([
      makeTaux({ id: 1, taux: 17, date_debut: '2010-01-01', date_fin: '2016-12-31' }),
      makeTaux({ id: 2, taux: 19, date_debut: '2017-01-01', date_fin: null }),
      makeTaux({ id: 3, taux: 0, type: 'exonere', date_debut: '2010-01-01' }),
    ])

    // Une facture de 2026 retourne 19 meme consultee plus tard
    expect(c.tauxEnVigueur('standard', '2026-03-15')).toBe(19)
    // Une facture de 2015 retombe sur l ancien taux clos
    expect(c.tauxEnVigueur('standard', '2015-06-01')).toBe(17)
    // Le type exonere est isole
    expect(c.tauxEnVigueur('exonere', '2026-03-15')).toBe(0)
  })

  it('accepte un objet Date (conversion ISO locale)', () => {
    const c = composableAvecTaux([makeTaux({ id: 1, taux: 19, date_debut: '2020-01-01' })])
    expect(c.tauxEnVigueur('standard', new Date(2026, 2, 15))).toBe(19)
  })

  it('ignore les taux inactifs et renvoie null sans candidat', () => {
    const c = composableAvecTaux([makeTaux({ id: 1, actif: false })])
    expect(c.tauxEnVigueur('standard', '2026-01-01')).toBeNull()
  })

  it('departage par date_debut la plus recente puis id le plus grand', () => {
    const c = composableAvecTaux([
      makeTaux({ id: 1, taux: 19, date_debut: '2020-01-01' }),
      makeTaux({ id: 2, taux: 21, date_debut: '2025-01-01' }),
      makeTaux({ id: 3, taux: 22, date_debut: '2025-01-01' }),
    ])
    // Meme date_debut pour 2 et 3 -> id le plus grand gagne
    expect(c.tauxEnVigueur('standard', '2026-01-01')).toBe(22)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// useExercices
// ─────────────────────────────────────────────────────────────────────────────
