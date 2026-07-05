import { describe, it, expect, vi, beforeEach } from 'vitest'

// ── Mock du module API stats (jamais le client axios) ───────────────────────
const { mockGetDashboard, mockGetCollab, mockGetSecretaire } = vi.hoisted(() => ({
  mockGetDashboard: vi.fn(),
  mockGetCollab: vi.fn(),
  mockGetSecretaire: vi.fn(),
}))

vi.mock('@/api/modules/stats', () => ({
  statsApi: {
    getDashboard: mockGetDashboard,
    getCollaborateurDashboard: mockGetCollab,
    getSecretaireDashboard: mockGetSecretaire,
  },
}))

import { useDashboardStats } from '@/composables/useDashboardStats'

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useDashboardStats — etat initial', () => {
  it('expose des stats nulles et loading a false', () => {
    const c = useDashboardStats()

    expect(c.loading.value).toBe(false)
    expect(c.adminStats.value).toBeNull()
    expect(c.collaborateurStats.value).toBeNull()
    expect(c.secretaireStats.value).toBeNull()
  })
})

describe('useDashboardStats — fetchAdminStats', () => {
  it('transmet exercice_id au module API et stocke les stats', async () => {
    const stats = { kpi: { ca_mois: 315000 }, alertes: [] }
    mockGetDashboard.mockResolvedValue({ data: stats })
    const c = useDashboardStats()

    await c.fetchAdminStats(5)

    expect(mockGetDashboard).toHaveBeenCalledWith(5)
    expect(c.adminStats.value).toEqual(stats)
    expect(c.loading.value).toBe(false)
  })

  it('passe loading a true pendant le chargement puis a false', async () => {
    let resolve!: (v: unknown) => void
    mockGetDashboard.mockReturnValue(new Promise((r) => { resolve = r }))
    const c = useDashboardStats()

    const pending = c.fetchAdminStats()
    expect(c.loading.value).toBe(true)

    resolve({ data: { kpi: {} } })
    await pending
    expect(c.loading.value).toBe(false)
    expect(mockGetDashboard).toHaveBeenCalledWith(undefined)
  })

  it('remet adminStats a null en cas d erreur API sans rejeter', async () => {
    mockGetDashboard.mockResolvedValueOnce({ data: { kpi: {} } })
    const c = useDashboardStats()
    await c.fetchAdminStats()
    expect(c.adminStats.value).not.toBeNull()

    mockGetDashboard.mockRejectedValueOnce(new Error('500'))
    await expect(c.fetchAdminStats()).resolves.toBeUndefined()

    expect(c.adminStats.value).toBeNull()
    expect(c.loading.value).toBe(false)
  })
})

describe('useDashboardStats — fetchCollaborateurStats', () => {
  it('stocke les stats collaborateur au succes', async () => {
    const stats = { missions: { total: 3, en_cours: 2, terminees: 1 } }
    mockGetCollab.mockResolvedValue({ data: stats })
    const c = useDashboardStats()

    await c.fetchCollaborateurStats()

    expect(mockGetCollab).toHaveBeenCalledOnce()
    expect(c.collaborateurStats.value).toEqual(stats)
    expect(c.loading.value).toBe(false)
  })

  it('remet collaborateurStats a null en cas d erreur', async () => {
    mockGetCollab.mockRejectedValue(new Error('403'))
    const c = useDashboardStats()

    await expect(c.fetchCollaborateurStats()).resolves.toBeUndefined()

    expect(c.collaborateurStats.value).toBeNull()
    expect(c.loading.value).toBe(false)
  })
})

describe('useDashboardStats — fetchSecretaireStats', () => {
  it('stocke les stats secretaire au succes', async () => {
    const stats = { creances: { total_impaye: 120000, clients_debiteurs: 2, en_retard: 1 } }
    mockGetSecretaire.mockResolvedValue({ data: stats })
    const c = useDashboardStats()

    await c.fetchSecretaireStats()

    expect(mockGetSecretaire).toHaveBeenCalledOnce()
    expect(c.secretaireStats.value).toEqual(stats)
    expect(c.loading.value).toBe(false)
  })

  it('remet secretaireStats a null en cas d erreur', async () => {
    mockGetSecretaire.mockRejectedValue(new Error('réseau'))
    const c = useDashboardStats()

    await expect(c.fetchSecretaireStats()).resolves.toBeUndefined()

    expect(c.secretaireStats.value).toBeNull()
    expect(c.loading.value).toBe(false)
  })

  it('chaque instance du composable est independante', async () => {
    mockGetSecretaire.mockResolvedValue({ data: { creances: {} } })
    const a = useDashboardStats()
    const b = useDashboardStats()

    await a.fetchSecretaireStats()

    expect(a.secretaireStats.value).not.toBeNull()
    expect(b.secretaireStats.value).toBeNull()
  })
})
