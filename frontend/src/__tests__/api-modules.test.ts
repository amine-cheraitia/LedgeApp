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
import { prestationsApi } from '@/api/modules/prestations'
import { usersApi } from '@/api/modules/users'
import { contactsApi } from '@/api/modules/contacts'
import { commentairesApi } from '@/api/modules/commentaires'
import { avoirsApi } from '@/api/modules/avoirs'
import { referentielsApi } from '@/api/modules/referentiels'
import { relancesApi } from '@/api/modules/relances'
import { creancesApi } from '@/api/modules/creances'
import { auditApi } from '@/api/modules/audit'
import { planningApi } from '@/api/modules/planning'
import { statsApi } from '@/api/modules/stats'
import { portailApi } from '@/api/modules/portail'

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
    mockPut.mockResolvedValue({ data: { data: { id: 1, notes: 'maj' } } })

    await devisApi.update(1, { notes: 'maj' })

    expect(mockPut).toHaveBeenCalledWith('/devis/1', { notes: 'maj' })
  })
})

describe('facturesApi', () => {
  it('create calls POST /factures', async () => {
    const payload = {
      mission_id: 1,
      date_facture: '2026-03-25',
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

describe('prestationsApi', () => {
  it('getAll calls GET /prestations', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    const result = await prestationsApi.getAll()

    expect(mockGet).toHaveBeenCalledWith('/prestations')
    expect(result.data).toEqual([])
  })

  it('getOne calls GET /prestations/:id', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 3 } } })

    await prestationsApi.getOne(3)

    expect(mockGet).toHaveBeenCalledWith('/prestations/3')
  })

  it('create calls POST /prestations', async () => {
    const payload = { code: 'ACMPT', designation: 'Assistance', tarif_initial: 120000, duree_mois: 12 }
    mockPost.mockResolvedValue({ data: { data: { id: 1, ...payload } } })

    await prestationsApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/prestations', payload)
  })

  it('update calls PUT /prestations/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 1 } } })

    await prestationsApi.update(1, { tarif_initial: 130000 })

    expect(mockPut).toHaveBeenCalledWith('/prestations/1', { tarif_initial: 130000 })
  })

  it('delete calls DELETE /prestations/:id', async () => {
    mockDelete.mockResolvedValue({})

    await prestationsApi.delete(1)

    expect(mockDelete).toHaveBeenCalledWith('/prestations/1')
  })

  it('calculerPrix posts regime_fiscal + categorie and returns prix_ht', async () => {
    mockPost.mockResolvedValue({ data: { prix_ht: 315000 } })

    const result = await prestationsApi.calculerPrix(1, 'reel', 'pme')

    expect(mockPost).toHaveBeenCalledWith('/prestations/1/calculer-prix', { regime_fiscal: 'reel', categorie: 'pme' })
    expect(result.prix_ht).toBe(315000)
  })
})

describe('usersApi', () => {
  it('getAll calls GET /users with params', async () => {
    const body = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }
    mockGet.mockResolvedValue({ data: body })

    const result = await usersApi.getAll({ page: 1, role: 'admin' })

    expect(mockGet).toHaveBeenCalledWith('/users', { params: { page: 1, role: 'admin' } })
    expect(result).toEqual(body)
  })

  it('create calls POST /users', async () => {
    const payload = { name: 'Amine', email: 'a@b.dz', role: 'collaborateur' }
    mockPost.mockResolvedValue({ data: { data: { id: 1, ...payload } } })

    await usersApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/users', payload)
  })

  it('update calls PUT /users/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 1 } } })

    await usersApi.update(1, { role: 'secretaire' })

    expect(mockPut).toHaveBeenCalledWith('/users/1', { role: 'secretaire' })
  })

  it('delete calls DELETE /users/:id', async () => {
    mockDelete.mockResolvedValue({})

    await usersApi.delete(1)

    expect(mockDelete).toHaveBeenCalledWith('/users/1')
  })
})

describe('contactsApi', () => {
  it('getAll calls GET /entreprises/:id/contacts', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await contactsApi.getAll(7)

    expect(mockGet).toHaveBeenCalledWith('/entreprises/7/contacts')
  })

  it('create calls POST /entreprises/:id/contacts', async () => {
    const payload = { nom: 'Doe', est_principal: true }
    mockPost.mockResolvedValue({ data: { data: { id: 1, ...payload } } })

    await contactsApi.create(7, payload)

    expect(mockPost).toHaveBeenCalledWith('/entreprises/7/contacts', payload)
  })

  it('update calls PUT /entreprises/:eid/contacts/:cid', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 2 } } })

    await contactsApi.update(7, 2, { email: 'a@b.dz' })

    expect(mockPut).toHaveBeenCalledWith('/entreprises/7/contacts/2', { email: 'a@b.dz' })
  })

  it('delete calls DELETE /entreprises/:eid/contacts/:cid', async () => {
    mockDelete.mockResolvedValue({})

    await contactsApi.delete(7, 2)

    expect(mockDelete).toHaveBeenCalledWith('/entreprises/7/contacts/2')
  })
})

describe('commentairesApi', () => {
  it('getAll calls GET /taches/:id/commentaires', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await commentairesApi.getAll(9)

    expect(mockGet).toHaveBeenCalledWith('/taches/9/commentaires')
  })

  it('create posts contenu', async () => {
    mockPost.mockResolvedValue({ data: { data: { id: 1, contenu: 'RAS' } } })

    await commentairesApi.create(9, 'RAS')

    expect(mockPost).toHaveBeenCalledWith('/taches/9/commentaires', { contenu: 'RAS' })
  })

  it('update puts contenu', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 1, contenu: 'maj' } } })

    await commentairesApi.update(9, 1, 'maj')

    expect(mockPut).toHaveBeenCalledWith('/taches/9/commentaires/1', { contenu: 'maj' })
  })

  it('delete calls DELETE /taches/:tid/commentaires/:id', async () => {
    mockDelete.mockResolvedValue({})

    await commentairesApi.delete(9, 1)

    expect(mockDelete).toHaveBeenCalledWith('/taches/9/commentaires/1')
  })
})

describe('avoirsApi', () => {
  it('index calls GET /factures/:id/avoirs', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    const res = await avoirsApi.index(4)

    expect(mockGet).toHaveBeenCalledWith('/factures/4/avoirs')
    expect(res.data.data).toEqual([])
  })

  it('store calls POST /factures/:id/avoirs', async () => {
    const payload = { montant_ht: 1000, date_avoir: '2026-03-25', motif: 'erreur de facturation' }
    mockPost.mockResolvedValue({ data: { data: { id: 1 } } })

    await avoirsApi.store(4, payload)

    expect(mockPost).toHaveBeenCalledWith('/factures/4/avoirs', payload)
  })

  it('getAll calls GET /avoirs with params and unwraps the body', async () => {
    const body = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }
    mockGet.mockResolvedValue({ data: body })

    const result = await avoirsApi.getAll({ exercice_id: 1 })

    expect(mockGet).toHaveBeenCalledWith('/avoirs', { params: { exercice_id: 1 } })
    expect(result).toEqual(body)
  })

  it('delete calls DELETE /avoirs/:id', async () => {
    mockDelete.mockResolvedValue({})

    await avoirsApi.delete(3)

    expect(mockDelete).toHaveBeenCalledWith('/avoirs/3')
  })
})

describe('relancesApi', () => {
  it('indexParFacture calls GET /factures/:id/relances', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await relancesApi.indexParFacture(4)

    expect(mockGet).toHaveBeenCalledWith('/factures/4/relances')
  })

  it('store posts the niveau', async () => {
    mockPost.mockResolvedValue({ data: { data: { id: 1, niveau: 1 } } })

    await relancesApi.store(4, { niveau: 1 })

    expect(mockPost).toHaveBeenCalledWith('/factures/4/relances', { niveau: 1 })
  })
})

describe('creancesApi', () => {
  it('index calls GET /creances', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await creancesApi.index()

    expect(mockGet).toHaveBeenCalledWith('/creances')
  })
})

describe('auditApi', () => {
  it('getAll calls GET /audit-logs with params', async () => {
    const body = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }
    mockGet.mockResolvedValue({ data: body })

    const result = await auditApi.getAll({ page: 2 })

    expect(mockGet).toHaveBeenCalledWith('/audit-logs', { params: { page: 2 } })
    expect(result).toEqual(body)
  })
})

describe('planningApi', () => {
  it('getCalendar calls GET /calendar with params', async () => {
    mockGet.mockResolvedValue({ data: { data: { missions: [], taches: [] } } })
    const params = { from: '2026-01-01', to: '2026-01-31' }

    const result = await planningApi.getCalendar(params)

    expect(mockGet).toHaveBeenCalledWith('/calendar', { params })
    expect(result.data.missions).toEqual([])
  })
})

describe('statsApi', () => {
  it('getDashboard without exercice sends empty params', async () => {
    mockGet.mockResolvedValue({ data: { data: {} } })

    await statsApi.getDashboard()

    expect(mockGet).toHaveBeenCalledWith('/stats', { params: {} })
  })

  it('getDashboard with exercice sends exercice_id', async () => {
    mockGet.mockResolvedValue({ data: { data: {} } })

    await statsApi.getDashboard(2)

    expect(mockGet).toHaveBeenCalledWith('/stats', { params: { exercice_id: 2 } })
  })

  it('getSecretaireDashboard calls GET /stats/secretaire', async () => {
    mockGet.mockResolvedValue({ data: { data: {} } })

    await statsApi.getSecretaireDashboard()

    expect(mockGet).toHaveBeenCalledWith('/stats/secretaire')
  })

  it('getCollaborateurDashboard calls GET /collaborateur/stats', async () => {
    mockGet.mockResolvedValue({ data: { data: {} } })

    await statsApi.getCollaborateurDashboard()

    expect(mockGet).toHaveBeenCalledWith('/collaborateur/stats')
  })

  it('upsertKpiObjectif posts to /kpi/objectifs', async () => {
    const payload = { user_id: 1, exercice_id: 1, type: 'ca_ht', valeur: 1000 }
    mockPost.mockResolvedValue({ data: { data: { id: 1, type: 'ca_ht', valeur: 1000 } } })

    await statsApi.upsertKpiObjectif(payload)

    expect(mockPost).toHaveBeenCalledWith('/kpi/objectifs', payload)
  })

  it('deleteKpiObjectif calls DELETE /kpi/objectifs/:id', async () => {
    mockDelete.mockResolvedValue({})

    await statsApi.deleteKpiObjectif(5)

    expect(mockDelete).toHaveBeenCalledWith('/kpi/objectifs/5')
  })
})

describe('portailApi', () => {
  it('me calls GET /portail/me', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 1 } } })

    await portailApi.me()

    expect(mockGet).toHaveBeenCalledWith('/portail/me')
  })

  it('getFactures calls GET /portail/factures with params', async () => {
    const body = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }
    mockGet.mockResolvedValue({ data: body })

    const result = await portailApi.getFactures({ statut_paiement: 'en_attente' })

    expect(mockGet).toHaveBeenCalledWith('/portail/factures', { params: { statut_paiement: 'en_attente' } })
    expect(result).toEqual(body)
  })

  it('getMissions calls GET /portail/missions with params', async () => {
    const body = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }
    mockGet.mockResolvedValue({ data: body })

    await portailApi.getMissions({ statut: 'en_cours' })

    expect(mockGet).toHaveBeenCalledWith('/portail/missions', { params: { statut: 'en_cours' } })
  })

  it('getMission calls GET /portail/missions/:id', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 3 } } })

    await portailApi.getMission(3)

    expect(mockGet).toHaveBeenCalledWith('/portail/missions/3')
  })

  it('getDocuments calls GET /portail/documents', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await portailApi.getDocuments()

    expect(mockGet).toHaveBeenCalledWith('/portail/documents')
  })

  it('rapportMissionPdfUrl builds the URL without any HTTP call', () => {
    expect(portailApi.rapportMissionPdfUrl(8)).toBe('/api/v1/portail/missions/8/rapport/pdf')
    expect(mockGet).not.toHaveBeenCalled()
  })
})

describe('referentielsApi', () => {
  it('getAllTvaTaux calls GET /referentiels/tva-taux', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    const result = await referentielsApi.getAllTvaTaux()

    expect(mockGet).toHaveBeenCalledWith('/referentiels/tva-taux')
    expect(result.data).toEqual([])
  })

  it('createTvaTaux calls POST /referentiels/tva-taux', async () => {
    const payload = { taux: 0, designation: 'Exonere', type: 'exonere' as const, date_debut: '2024-01-01', date_fin: null, actif: true }
    mockPost.mockResolvedValue({ data: { data: { id: 1, ...payload } } })

    await referentielsApi.createTvaTaux(payload)

    expect(mockPost).toHaveBeenCalledWith('/referentiels/tva-taux', payload)
  })

  it('updateTvaTaux calls PUT /referentiels/tva-taux/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 1, designation: 'maj' } } })

    await referentielsApi.updateTvaTaux(1, { designation: 'maj' })

    expect(mockPut).toHaveBeenCalledWith('/referentiels/tva-taux/1', { designation: 'maj' })
  })

  it('deleteTvaTaux calls DELETE /referentiels/tva-taux/:id', async () => {
    mockDelete.mockResolvedValue({})

    await referentielsApi.deleteTvaTaux(3)

    expect(mockDelete).toHaveBeenCalledWith('/referentiels/tva-taux/3')
  })
})
