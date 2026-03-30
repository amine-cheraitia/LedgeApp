import { describe, it, expect, vi, beforeEach } from 'vitest'

const { mockGet, mockPost, mockPut, mockDelete } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockPut: vi.fn(),
  mockDelete: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: {
    get: mockGet,
    post: mockPost,
    put: mockPut,
    delete: mockDelete,
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import { entreprisesApi } from '@/api/modules/entreprises'
import { exercicesApi } from '@/api/modules/exercices'
import { devisApi } from '@/api/modules/devis'
import { facturesApi } from '@/api/modules/factures'
import { settingsApi } from '@/api/modules/settings'
import { missionsApi } from '@/api/modules/missions'
import { tachesApi } from '@/api/modules/taches'

beforeEach(() => {
  vi.clearAllMocks()
})

describe('entreprisesApi', () => {
  it('getAll calls GET /entreprises with params', async () => {
    const mockData = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }
    mockGet.mockResolvedValue({ data: mockData })

    const result = await entreprisesApi.getAll({ page: 1, search: 'test' })

    expect(mockGet).toHaveBeenCalledWith('/entreprises', { params: { page: 1, search: 'test' } })
    expect(result).toEqual(mockData)
  })

  it('create calls POST /entreprises', async () => {
    const newEntreprise = { raison_sociale: 'Test SARL' }
    mockPost.mockResolvedValue({ data: { data: { id: 1, ...newEntreprise } } })

    const result = await entreprisesApi.create(newEntreprise)

    expect(mockPost).toHaveBeenCalledWith('/entreprises', newEntreprise)
    expect(result.data.raison_sociale).toBe('Test SARL')
  })

  it('update calls PUT /entreprises/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 1, raison_sociale: 'Updated' } } })

    await entreprisesApi.update(1, { raison_sociale: 'Updated' })

    expect(mockPut).toHaveBeenCalledWith('/entreprises/1', { raison_sociale: 'Updated' })
  })

  it('delete calls DELETE /entreprises/:id', async () => {
    mockDelete.mockResolvedValue({})

    await entreprisesApi.delete(1)

    expect(mockDelete).toHaveBeenCalledWith('/entreprises/1')
  })
})

describe('exercicesApi', () => {
  it('getAll calls GET /exercices', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await exercicesApi.getAll()

    expect(mockGet).toHaveBeenCalledWith('/exercices')
  })

  it('getCurrent calls GET /exercices/current', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 1, annee: 2026 } } })

    const result = await exercicesApi.getCurrent()

    expect(mockGet).toHaveBeenCalledWith('/exercices/current')
    expect(result.data.annee).toBe(2026)
  })
})

describe('devisApi', () => {
  it('create calls POST /devis with payload', async () => {
    const payload = {
      entreprise_id: 1,
      prestation_id: 1,
      date_devis: '2026-03-25',
      date_validite: '2026-04-25',
    }
    mockPost.mockResolvedValue({ data: { data: { id: 1, numero: 'DV2026-001' } } })

    const result = await devisApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/devis', payload)
    expect(result.data.numero).toBe('DV2026-001')
  })

  it('update calls PUT /devis/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 1, statut: 'envoye' } } })

    await devisApi.update(1, { statut: 'envoye' })

    expect(mockPut).toHaveBeenCalledWith('/devis/1', { statut: 'envoye' })
  })
})

describe('facturesApi', () => {
  it('create calls POST /factures', async () => {
    const payload = {
      entreprise_id: 1,
      type: 'FF' as const,
      date_facture: '2026-03-25',
      date_echeance: '2026-04-25',
      lignes: [{ designation: 'Service', quantite: 1, prix_unitaire_ht: 100000 }],
    }
    mockPost.mockResolvedValue({ data: { data: { id: 1, numero: 'FF2026-001' } } })

    const result = await facturesApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/factures', payload)
    expect(result.data.numero).toBe('FF2026-001')
  })

  it('createPaiement calls POST /factures/:id/paiements', async () => {
    const paiement = { montant: 50000, date_paiement: '2026-03-26', mode_paiement: 'virement' as const }
    mockPost.mockResolvedValue({ data: { data: { id: 1, montant: 50000 } } })

    await facturesApi.createPaiement(1, paiement)

    expect(mockPost).toHaveBeenCalledWith('/factures/1/paiements', paiement)
  })

  it('getPaiements calls GET /factures/:id/paiements', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await facturesApi.getPaiements(1)

    expect(mockGet).toHaveBeenCalledWith('/factures/1/paiements')
  })
})

describe('missionsApi', () => {
  it('getAll calls GET /missions with params', async () => {
    const mockData = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }
    mockGet.mockResolvedValue({ data: mockData })

    const result = await missionsApi.getAll({ page: 1, statut: 'en_cours' })

    expect(mockGet).toHaveBeenCalledWith('/missions', { params: { page: 1, statut: 'en_cours' } })
    expect(result).toEqual(mockData)
  })

  it('getOne calls GET /missions/:id', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 1, reference: 'M2026-001' } } })

    const result = await missionsApi.getOne(1)

    expect(mockGet).toHaveBeenCalledWith('/missions/1')
    expect(result.data.reference).toBe('M2026-001')
  })

  it('create calls POST /missions', async () => {
    const payload = { entreprise_id: 1, prestation_id: 2, date_debut: '2026-04-01', date_fin: '2027-03-31' }
    mockPost.mockResolvedValue({ data: { data: { id: 1, ...payload } } })

    const result = await missionsApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/missions', payload)
    expect(result.data.entreprise_id).toBe(1)
  })

  it('update calls PUT /missions/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 1, statut: 'terminee' } } })

    await missionsApi.update(1, { statut: 'terminee' })

    expect(mockPut).toHaveBeenCalledWith('/missions/1', { statut: 'terminee' })
  })

  it('delete calls DELETE /missions/:id', async () => {
    mockDelete.mockResolvedValue({})

    await missionsApi.delete(1)

    expect(mockDelete).toHaveBeenCalledWith('/missions/1')
  })
})

describe('tachesApi', () => {
  it('getAll calls GET /missions/:id/taches', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    const result = await tachesApi.getAll(5)

    expect(mockGet).toHaveBeenCalledWith('/missions/5/taches')
    expect(result.data).toEqual([])
  })

  it('create calls POST /missions/:id/taches', async () => {
    const payload = { titre: 'Collecte documents', priorite: 2 }
    mockPost.mockResolvedValue({ data: { data: { id: 1, ...payload } } })

    await tachesApi.create(5, payload)

    expect(mockPost).toHaveBeenCalledWith('/missions/5/taches', payload)
  })

  it('update calls PUT /missions/:mid/taches/:tid', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 1, statut: 'terminee' } } })

    await tachesApi.update(5, 1, { statut: 'terminee' })

    expect(mockPut).toHaveBeenCalledWith('/missions/5/taches/1', { statut: 'terminee' })
  })

  it('delete calls DELETE /missions/:mid/taches/:tid', async () => {
    mockDelete.mockResolvedValue({})

    await tachesApi.delete(5, 1)

    expect(mockDelete).toHaveBeenCalledWith('/missions/5/taches/1')
  })
})

describe('settingsApi', () => {
  it('getAll calls GET /settings', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await settingsApi.getAll()

    expect(mockGet).toHaveBeenCalledWith('/settings')
  })

  it('update calls PUT /settings with payload', async () => {
    const settings = [{ key: 'cabinet_nom', value: 'Test Cabinet' }]
    mockPut.mockResolvedValue({})

    await settingsApi.update(settings)

    expect(mockPut).toHaveBeenCalledWith('/settings', { settings })
  })
})
