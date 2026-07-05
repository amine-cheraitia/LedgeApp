import { describe, it, expect, vi, beforeEach, beforeAll } from 'vitest'

// ── Mock du client axios partage ─────────────────────────────────────────────
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
  resetCsrf: vi.fn(),
}))

import { avoirsApi } from '@/api/modules/avoirs'
import { devisApi } from '@/api/modules/devis'
import { entreprisesApi } from '@/api/modules/entreprises'
import { exercicesApi } from '@/api/modules/exercices'
import { facturesApi } from '@/api/modules/factures'
import { authApi } from '@/api/modules/auth'
import { statsApi } from '@/api/modules/stats'
import { missionsApi } from '@/api/modules/missions'
import { tachesApi } from '@/api/modules/taches'
import { usersApi } from '@/api/modules/users'
import { prestationsApi } from '@/api/modules/prestations'

const PAGINATED = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }

// Telechargement PDF : URL.createObjectURL / click() ne sont pas exploitables
// sous happy-dom -> on les remplace et on capture le nom de fichier telecharge.
const downloads: string[] = []
beforeAll(() => {
  ;(URL as unknown as { createObjectURL: unknown }).createObjectURL = vi.fn(() => 'blob:mock-url')
  ;(URL as unknown as { revokeObjectURL: unknown }).revokeObjectURL = vi.fn()
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
    downloads.push(this.download)
  })
})

beforeEach(() => {
  vi.clearAllMocks()
  downloads.length = 0
})

describe('avoirsApi', () => {
  it('index appelle GET /factures/:id/avoirs', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    const res = await avoirsApi.index(12)

    expect(mockGet).toHaveBeenCalledWith('/factures/12/avoirs')
    expect(res.data.data).toEqual([])
  })

  it('store appelle POST /factures/:id/avoirs avec le payload', async () => {
    const payload = { montant_ht: 5000, date_avoir: '2026-02-10', motif: 'remise commerciale' }
    mockPost.mockResolvedValue({ data: { data: { id: 1 } } })

    await avoirsApi.store(12, payload)

    expect(mockPost).toHaveBeenCalledWith('/factures/12/avoirs', payload)
  })

  it('getAll appelle GET /avoirs avec filtres et deballe la reponse paginee', async () => {
    mockGet.mockResolvedValue({ data: PAGINATED })

    const result = await avoirsApi.getAll({ page: 2, search: 'FA' })

    expect(mockGet).toHaveBeenCalledWith('/avoirs', { params: { page: 2, search: 'FA' } })
    expect(result).toEqual(PAGINATED)
  })

  it('delete appelle DELETE /avoirs/:id et resout en undefined', async () => {
    mockDelete.mockResolvedValue({ status: 204 })

    const result = await avoirsApi.delete(9)

    expect(mockDelete).toHaveBeenCalledWith('/avoirs/9')
    expect(result).toBeUndefined()
  })

  it('telechargerPdf recupere le blob et declenche le telechargement nomme', async () => {
    mockGet.mockResolvedValue({ data: 'PDFBYTES' })

    await avoirsApi.telechargerPdf(12, 9, 'FA2026-007')

    expect(mockGet).toHaveBeenCalledWith('/factures/12/avoirs/9/pdf', { responseType: 'blob' })
    expect(URL.createObjectURL).toHaveBeenCalledWith(expect.any(Blob))
    expect(downloads).toEqual(['avoir-FA2026-007.pdf'])
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:mock-url')
  })
})

describe('devisApi', () => {
  it('getAll appelle GET /devis avec filtres', async () => {
    mockGet.mockResolvedValue({ data: PAGINATED })

    const result = await devisApi.getAll({ statut: 'envoye', exercice_id: 3 })

    expect(mockGet).toHaveBeenCalledWith('/devis', { params: { statut: 'envoye', exercice_id: 3 } })
    expect(result).toEqual(PAGINATED)
  })

  it('getOne appelle GET /devis/:id', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 4, numero: 'DV2026-004' } } })

    const result = await devisApi.getOne(4)

    expect(mockGet).toHaveBeenCalledWith('/devis/4')
    expect(result.data.numero).toBe('DV2026-004')
  })

  it('create appelle POST /devis', async () => {
    const payload = { entreprise_id: 1, prestation_id: 2, date_devis: '2026-01-05', date_validite: '2026-02-05' }
    mockPost.mockResolvedValue({ data: { data: { id: 1 } } })

    await devisApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/devis', payload)
  })

  it('update appelle PUT /devis/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 4 } } })

    await devisApi.update(4, { date_validite: '2026-03-01' })

    expect(mockPut).toHaveBeenCalledWith('/devis/4', { date_validite: '2026-03-01' })
  })

  it('envoyer / accepter / refuser postent sur les transitions du devis', async () => {
    mockPost.mockResolvedValue({ data: { data: { id: 4 } } })

    await devisApi.envoyer(4)
    await devisApi.accepter(4)
    await devisApi.refuser(4)

    expect(mockPost).toHaveBeenNthCalledWith(1, '/devis/4/envoyer')
    expect(mockPost).toHaveBeenNthCalledWith(2, '/devis/4/accepter')
    expect(mockPost).toHaveBeenNthCalledWith(3, '/devis/4/refuser')
  })

  it('convertirEnMission poste les dates et collaborateurs', async () => {
    const payload = { date_debut: '2026-04-01', date_fin: '2027-03-31', collaborateur_ids: [2, 5] }
    mockPost.mockResolvedValue({ data: { data: { id: 10 } } })

    const result = await devisApi.convertirEnMission(4, payload)

    expect(mockPost).toHaveBeenCalledWith('/devis/4/convertir-en-mission', payload)
    expect(result.data.id).toBe(10)
  })

  it('getPdf demande un blob et renvoie le corps', async () => {
    const blob = new Blob(['%PDF'])
    mockGet.mockResolvedValue({ data: blob })

    const result = await devisApi.getPdf(4)

    expect(mockGet).toHaveBeenCalledWith('/devis/4/pdf', { responseType: 'blob' })
    expect(result).toBe(blob)
  })

  it('delete appelle DELETE /devis/:id', async () => {
    mockDelete.mockResolvedValue({})

    await devisApi.delete(4)

    expect(mockDelete).toHaveBeenCalledWith('/devis/4')
  })
})

describe('entreprisesApi', () => {
  it('getAll appelle GET /entreprises avec filtres', async () => {
    mockGet.mockResolvedValue({ data: PAGINATED })

    const result = await entreprisesApi.getAll({ search: 'sarl', wilaya: 'Alger' })

    expect(mockGet).toHaveBeenCalledWith('/entreprises', { params: { search: 'sarl', wilaya: 'Alger' } })
    expect(result).toEqual(PAGINATED)
  })

  it('getOne appelle GET /entreprises/:id', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 7, raison_sociale: 'Atlas SARL' } } })

    const result = await entreprisesApi.getOne(7)

    expect(mockGet).toHaveBeenCalledWith('/entreprises/7')
    expect(result.data.raison_sociale).toBe('Atlas SARL')
  })

  it('create / update / delete utilisent les bons verbes', async () => {
    mockPost.mockResolvedValue({ data: { data: { id: 1 } } })
    mockPut.mockResolvedValue({ data: { data: { id: 1 } } })
    mockDelete.mockResolvedValue({})

    await entreprisesApi.create({ raison_sociale: 'Nouvelle' })
    await entreprisesApi.update(1, { raison_sociale: 'Renommee' })
    await entreprisesApi.delete(1)

    expect(mockPost).toHaveBeenCalledWith('/entreprises', { raison_sociale: 'Nouvelle' })
    expect(mockPut).toHaveBeenCalledWith('/entreprises/1', { raison_sociale: 'Renommee' })
    expect(mockDelete).toHaveBeenCalledWith('/entreprises/1')
  })

  it("activerPortail poste nom + email et renvoie l'URL d'invitation (jamais de mot de passe)", async () => {
    mockPost.mockResolvedValue({ data: { message: 'Invitation envoyee', invitation_url: 'https://ledge.dz/definir-mot-de-passe?token=abc' } })

    const result = await entreprisesApi.activerPortail(7, { name: 'Client Atlas', email: 'contact@atlas.dz' })

    expect(mockPost).toHaveBeenCalledWith('/entreprises/7/activer-portail', { name: 'Client Atlas', email: 'contact@atlas.dz' })
    expect(result.invitation_url).toContain('definir-mot-de-passe')
    expect(JSON.stringify(result)).not.toContain('password')
  })

  it('renvoyerInvitation appelle POST /entreprises/:id/renvoyer-invitation', async () => {
    mockPost.mockResolvedValue({ data: { message: 'ok', invitation_url: 'https://x' } })

    await entreprisesApi.renvoyerInvitation(7)

    expect(mockPost).toHaveBeenCalledWith('/entreprises/7/renvoyer-invitation')
  })

  it('togglePortail appelle POST /entreprises/:id/toggle-portail', async () => {
    mockPost.mockResolvedValue({ data: { portail_actif: false } })

    const result = await entreprisesApi.togglePortail(7)

    expect(mockPost).toHaveBeenCalledWith('/entreprises/7/toggle-portail')
    expect(result.portail_actif).toBe(false)
  })

  it('wilayas appelle GET /entreprises/wilayas', async () => {
    mockGet.mockResolvedValue({ data: { data: ['Alger', 'Oran'] } })

    const result = await entreprisesApi.wilayas()

    expect(mockGet).toHaveBeenCalledWith('/entreprises/wilayas')
    expect(result.data).toEqual(['Alger', 'Oran'])
  })

  it('exportCsv demande un blob avec les filtres (hors pagination)', async () => {
    const blob = new Blob(['id;raison_sociale'])
    mockGet.mockResolvedValue({ data: blob })

    const result = await entreprisesApi.exportCsv({ statut: 'client' })

    expect(mockGet).toHaveBeenCalledWith('/entreprises/export-csv', { params: { statut: 'client' }, responseType: 'blob' })
    expect(result).toBe(blob)
  })
})

describe('exercicesApi', () => {
  it('getAll appelle GET /exercices', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await exercicesApi.getAll()

    expect(mockGet).toHaveBeenCalledWith('/exercices')
  })

  it('getOne appelle GET /exercices/:id', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 2, annee: 2025 } } })

    const result = await exercicesApi.getOne(2)

    expect(mockGet).toHaveBeenCalledWith('/exercices/2')
    expect(result.data.annee).toBe(2025)
  })

  it("getCurrent appelle GET /exercices/current (exercice ouvert de l'annee)", async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 3, annee: 2026, statut: 'ouvert' } } })

    const result = await exercicesApi.getCurrent()

    expect(mockGet).toHaveBeenCalledWith('/exercices/current')
    expect(result.data.statut).toBe('ouvert')
  })

  it('create appelle POST /exercices', async () => {
    const payload = { annee: 2027, date_ouverture: '2027-01-01', date_cloture: '2027-12-31', statut: 'ouvert' as const }
    mockPost.mockResolvedValue({ data: { data: { id: 4 } } })

    await exercicesApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/exercices', payload)
  })

  it('update appelle PUT /exercices/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 3, statut: 'cloture' } } })

    await exercicesApi.update(3, { statut: 'cloture' })

    expect(mockPut).toHaveBeenCalledWith('/exercices/3', { statut: 'cloture' })
  })

  it('rapportCloturePdf demande le PDF de cloture en blob', async () => {
    const blob = new Blob(['%PDF'])
    mockGet.mockResolvedValue({ data: blob })

    const result = await exercicesApi.rapportCloturePdf(3)

    expect(mockGet).toHaveBeenCalledWith('/exercices/3/rapport-cloture/pdf', { responseType: 'blob' })
    expect(result).toBe(blob)
  })
})

describe('facturesApi', () => {
  it('getAll appelle GET /factures avec filtres de paiement', async () => {
    mockGet.mockResolvedValue({ data: PAGINATED })

    await facturesApi.getAll({ statut_paiement: 'en_attente', sort_field: 'date_facture', sort_direction: 'desc' })

    expect(mockGet).toHaveBeenCalledWith('/factures', {
      params: { statut_paiement: 'en_attente', sort_field: 'date_facture', sort_direction: 'desc' },
    })
  })

  it('getOne appelle GET /factures/:id', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 8, numero: 'FF2026-008' } } })

    const result = await facturesApi.getOne(8)

    expect(mockGet).toHaveBeenCalledWith('/factures/8')
    expect(result.data.numero).toBe('FF2026-008')
  })

  it('create appelle POST /factures', async () => {
    const payload = { mission_id: 5, date_facture: '2026-06-01', type_tva: 'standard' as const }
    mockPost.mockResolvedValue({ data: { data: { id: 8 } } })

    await facturesApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/factures', payload)
  })

  it('getPdf demande le PDF en blob', async () => {
    const blob = new Blob(['%PDF'])
    mockGet.mockResolvedValue({ data: blob })

    const result = await facturesApi.getPdf(8)

    expect(mockGet).toHaveBeenCalledWith('/factures/8/pdf', { responseType: 'blob' })
    expect(result).toBe(blob)
  })

  it('transmettre appelle POST /factures/:id/transmettre', async () => {
    mockPost.mockResolvedValue({ data: { message: 'Facture transmise.' } })

    const result = await facturesApi.transmettre(8)

    expect(mockPost).toHaveBeenCalledWith('/factures/8/transmettre')
    expect(result.message).toBe('Facture transmise.')
  })

  it('delete appelle DELETE /factures/:id', async () => {
    mockDelete.mockResolvedValue({})

    await facturesApi.delete(8)

    expect(mockDelete).toHaveBeenCalledWith('/factures/8')
  })

  it('getPaiements / createPaiement / deletePaiement ciblent la sous-ressource paiements', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })
    mockPost.mockResolvedValue({ data: { data: { id: 1 } } })
    mockDelete.mockResolvedValue({})
    const paiement = { montant: 94500, date_paiement: '2026-06-10', mode_paiement: 'cheque' as const, reference: 'CHQ-42' }

    await facturesApi.getPaiements(8)
    await facturesApi.createPaiement(8, paiement)
    await facturesApi.deletePaiement(8, 3)

    expect(mockGet).toHaveBeenCalledWith('/factures/8/paiements')
    expect(mockPost).toHaveBeenCalledWith('/factures/8/paiements', paiement)
    expect(mockDelete).toHaveBeenCalledWith('/factures/8/paiements/3')
  })
})

describe('authApi', () => {
  it('forgotPassword poste l\'email sur /forgot-password et renvoie le message generique', async () => {
    mockPost.mockResolvedValue({ data: { message: 'Si un compte existe, un email a ete envoye.' } })

    const result = await authApi.forgotPassword('client@atlas.dz')

    expect(mockPost).toHaveBeenCalledWith('/forgot-password', { email: 'client@atlas.dz' })
    expect(result.message).toContain('email')
  })

  it('resetPassword poste le jeton et le nouveau mot de passe sur /reset-password', async () => {
    const payload = {
      token: 'jeton-usage-unique',
      email: 'client@atlas.dz',
      password: 'S3cret!S3cret!',
      password_confirmation: 'S3cret!S3cret!',
    }
    mockPost.mockResolvedValue({ data: { message: 'Mot de passe defini.' } })

    const result = await authApi.resetPassword(payload)

    expect(mockPost).toHaveBeenCalledWith('/reset-password', payload)
    expect(result.message).toBe('Mot de passe defini.')
  })
})

describe('statsApi', () => {
  it('getDashboard sans exercice envoie des params vides', async () => {
    mockGet.mockResolvedValue({ data: { data: {} } })

    await statsApi.getDashboard(null)

    expect(mockGet).toHaveBeenCalledWith('/stats', { params: {} })
  })

  it('getDashboard avec exercice envoie exercice_id', async () => {
    mockGet.mockResolvedValue({ data: { data: {} } })

    await statsApi.getDashboard(4)

    expect(mockGet).toHaveBeenCalledWith('/stats', { params: { exercice_id: 4 } })
  })

  it('getCollaborateurDashboard appelle GET /collaborateur/stats', async () => {
    mockGet.mockResolvedValue({ data: { data: {} } })

    await statsApi.getCollaborateurDashboard()

    expect(mockGet).toHaveBeenCalledWith('/collaborateur/stats')
  })

  it('getSecretaireDashboard appelle GET /stats/secretaire', async () => {
    mockGet.mockResolvedValue({ data: { data: {} } })

    await statsApi.getSecretaireDashboard()

    expect(mockGet).toHaveBeenCalledWith('/stats/secretaire')
  })

  it('getKpiObjectifs sans exercice envoie des params vides', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await statsApi.getKpiObjectifs()

    expect(mockGet).toHaveBeenCalledWith('/kpi/objectifs', { params: {} })
  })

  it('getKpiObjectifs avec exercice envoie exercice_id', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await statsApi.getKpiObjectifs(2)

    expect(mockGet).toHaveBeenCalledWith('/kpi/objectifs', { params: { exercice_id: 2 } })
  })

  it('upsertKpiObjectif poste user/exercice/type/valeur', async () => {
    const payload = { user_id: 3, exercice_id: 2, type: 'ca_ht', valeur: 500000 }
    mockPost.mockResolvedValue({ data: { data: { id: 1, type: 'ca_ht', valeur: 500000 } } })

    const result = await statsApi.upsertKpiObjectif(payload)

    expect(mockPost).toHaveBeenCalledWith('/kpi/objectifs', payload)
    expect(result.data.valeur).toBe(500000)
  })

  it('deleteKpiObjectif appelle DELETE /kpi/objectifs/:id', async () => {
    mockDelete.mockResolvedValue({})

    await statsApi.deleteKpiObjectif(6)

    expect(mockDelete).toHaveBeenCalledWith('/kpi/objectifs/6')
  })
})

describe('missionsApi', () => {
  it('getAll appelle GET /missions avec filtres', async () => {
    mockGet.mockResolvedValue({ data: PAGINATED })

    await missionsApi.getAll({ statut: 'en_cours', entreprise_id: 7 })

    expect(mockGet).toHaveBeenCalledWith('/missions', { params: { statut: 'en_cours', entreprise_id: 7 } })
  })

  it('getOne appelle GET /missions/:id', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 5, reference: 'M2026-005' } } })

    const result = await missionsApi.getOne(5)

    expect(mockGet).toHaveBeenCalledWith('/missions/5')
    expect(result.data.reference).toBe('M2026-005')
  })

  it('create appelle POST /missions', async () => {
    const payload = { entreprise_id: 7, prestation_id: 1, date_debut: '2026-01-01', date_fin: '2026-12-31', collaborateur_ids: [2] }
    mockPost.mockResolvedValue({ data: { data: { id: 5 } } })

    await missionsApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/missions', payload)
  })

  it('update appelle PUT /missions/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 5, visible_portail: true } } })

    await missionsApi.update(5, { visible_portail: true })

    expect(mockPut).toHaveBeenCalledWith('/missions/5', { visible_portail: true })
  })

  it('delete appelle DELETE /missions/:id', async () => {
    mockDelete.mockResolvedValue({})

    await missionsApi.delete(5)

    expect(mockDelete).toHaveBeenCalledWith('/missions/5')
  })

  it('les helpers PDF construisent les URLs sans appel HTTP', () => {
    expect(missionsApi.rapportPdfUrl(5)).toBe('/api/v1/missions/5/rapport/pdf')
    expect(missionsApi.conventionPdfUrl(5)).toBe('/api/v1/missions/5/convention/pdf')
    expect(missionsApi.mandatPdfUrl(5)).toBe('/api/v1/missions/5/mandat/pdf')
    expect(mockGet).not.toHaveBeenCalled()
  })
})

describe('tachesApi', () => {
  it('getAll appelle GET /missions/:id/taches', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await tachesApi.getAll(5)

    expect(mockGet).toHaveBeenCalledWith('/missions/5/taches')
  })

  it('getOne appelle GET /missions/:mid/taches/:tid', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 3, titre: 'Bilan' } } })

    const result = await tachesApi.getOne(5, 3)

    expect(mockGet).toHaveBeenCalledWith('/missions/5/taches/3')
    expect(result.data.titre).toBe('Bilan')
  })

  it('create appelle POST /missions/:id/taches', async () => {
    const payload = { titre: 'Saisie ecritures', priorite: 1, assigned_to: 2 }
    mockPost.mockResolvedValue({ data: { data: { id: 3 } } })

    await tachesApi.create(5, payload)

    expect(mockPost).toHaveBeenCalledWith('/missions/5/taches', payload)
  })

  it('update appelle PUT /missions/:mid/taches/:tid', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 3, statut: 'en_cours' } } })

    await tachesApi.update(5, 3, { statut: 'en_cours' })

    expect(mockPut).toHaveBeenCalledWith('/missions/5/taches/3', { statut: 'en_cours' })
  })

  it('delete appelle DELETE /missions/:mid/taches/:tid', async () => {
    mockDelete.mockResolvedValue({})

    await tachesApi.delete(5, 3)

    expect(mockDelete).toHaveBeenCalledWith('/missions/5/taches/3')
  })

  it('conflits interroge GET /taches/conflits avec la periode du collaborateur', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })
    const params = { collaborateur_id: 2, date_debut: '2026-05-01', date_echeance: '2026-05-15', exclude_tache_id: 3 }

    const result = await tachesApi.conflits(params)

    expect(mockGet).toHaveBeenCalledWith('/taches/conflits', { params })
    expect(result.data).toEqual([])
  })
})

describe('usersApi', () => {
  it('getAll appelle GET /users avec role multiple (tableau)', async () => {
    mockGet.mockResolvedValue({ data: PAGINATED })

    await usersApi.getAll({ role: ['admin', 'collaborateur'] })

    expect(mockGet).toHaveBeenCalledWith('/users', { params: { role: ['admin', 'collaborateur'] } })
  })

  it('getOne appelle GET /users/:id', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 2, name: 'Sonia' } } })

    const result = await usersApi.getOne(2)

    expect(mockGet).toHaveBeenCalledWith('/users/2')
    expect(result.data.name).toBe('Sonia')
  })

  it("create poste le user sans mot de passe et recoit l'URL d'invitation", async () => {
    const payload = { name: 'Sonia', email: 'sonia@ledge.dz', role: 'secretaire' }
    mockPost.mockResolvedValue({ data: { data: { id: 2 }, invitation_url: 'https://ledge.dz/definir-mot-de-passe?token=xyz' } })

    const result = await usersApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/users', payload)
    expect(result.invitation_url).toContain('definir-mot-de-passe')
    expect(payload).not.toHaveProperty('password')
  })

  it('update appelle PUT /users/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 2 } } })

    await usersApi.update(2, { role: 'collaborateur' })

    expect(mockPut).toHaveBeenCalledWith('/users/2', { role: 'collaborateur' })
  })

  it('renvoyerInvitation appelle POST /users/:id/renvoyer-invitation', async () => {
    mockPost.mockResolvedValue({ data: { message: 'ok', invitation_url: 'https://x' } })

    await usersApi.renvoyerInvitation(2)

    expect(mockPost).toHaveBeenCalledWith('/users/2/renvoyer-invitation')
  })

  it('delete appelle DELETE /users/:id', async () => {
    mockDelete.mockResolvedValue({})

    await usersApi.delete(2)

    expect(mockDelete).toHaveBeenCalledWith('/users/2')
  })
})

describe('prestationsApi', () => {
  it('getAll appelle GET /prestations', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await prestationsApi.getAll()

    expect(mockGet).toHaveBeenCalledWith('/prestations')
  })

  it('getOne appelle GET /prestations/:id', async () => {
    mockGet.mockResolvedValue({ data: { data: { id: 1, code: 'ACMPT' } } })

    const result = await prestationsApi.getOne(1)

    expect(mockGet).toHaveBeenCalledWith('/prestations/1')
    expect(result.data.code).toBe('ACMPT')
  })

  it('create appelle POST /prestations', async () => {
    const payload = { code: 'AUDIT', designation: 'Audit legal', tarif_initial: 200000, duree_mois: 6 }
    mockPost.mockResolvedValue({ data: { data: { id: 2 } } })

    await prestationsApi.create(payload)

    expect(mockPost).toHaveBeenCalledWith('/prestations', payload)
  })

  it('update appelle PUT /prestations/:id', async () => {
    mockPut.mockResolvedValue({ data: { data: { id: 1 } } })

    await prestationsApi.update(1, { actif: false })

    expect(mockPut).toHaveBeenCalledWith('/prestations/1', { actif: false })
  })

  it('delete appelle DELETE /prestations/:id et resout en undefined', async () => {
    mockDelete.mockResolvedValue({ status: 204 })

    const result = await prestationsApi.delete(1)

    expect(mockDelete).toHaveBeenCalledWith('/prestations/1')
    expect(result).toBeUndefined()
  })

  it('calculerPrix poste regime fiscal + categorie et renvoie le prix HT calcule', async () => {
    mockPost.mockResolvedValue({
      data: { prestation: 'ACMPT', tarif_initial: 120000, regime_fiscal: 'Reel', categorie: 'PME', prix_ht: 315000 },
    })

    const result = await prestationsApi.calculerPrix(1, 'reel', 'pme')

    expect(mockPost).toHaveBeenCalledWith('/prestations/1/calculer-prix', { regime_fiscal: 'reel', categorie: 'pme' })
    expect(result.prix_ht).toBe(315000)
  })
})
