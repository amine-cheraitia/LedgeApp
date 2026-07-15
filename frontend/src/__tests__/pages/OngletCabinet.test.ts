import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { CabinetStats } from '@/api/modules/stats'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetCabinetStats } = vi.hoisted(() => ({
  mockGetCabinetStats: vi.fn(),
}))

vi.mock('@/api/modules/stats', () => ({
  statsApi: {
    getCabinetStats: mockGetCabinetStats,
  },
}))

// ── Import du composant APRES les mocks ──────────────────────────────────────
import OngletCabinet from '@/pages/statistiques/OngletCabinet.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const cabinetStatsFull: CabinetStats = {
  top_entreprises: [
    { entreprise_id: 1, raison_sociale: 'SARL Atlas', ca_ht_net: 1250000 },
    { entreprise_id: 2, raison_sociale: 'EURL Numidia', ca_ht_net: 800000 },
  ],
  missions_par_prestation: [
    { prestation_id: 1, designation: 'Assistance comptable', total: 6 },
    { prestation_id: 2, designation: 'Audit', total: 3 },
  ],
  missions_par_etat: { en_cours: 5, terminee: 8, suspendue: 2, annulee: 1 },
  creances: {
    total_impaye: 1345000,
    aging: { retard_15_30: 200000, retard_30_60: 150000, retard_60_plus: 995000 },
    top_debiteurs: [
      { entreprise_id: 3, raison_sociale: 'SPA Aures', montant_impaye: 600000 },
      { entreprise_id: 4, raison_sociale: 'SARL Kabylie', montant_impaye: 400000 },
    ],
  },
}

const cabinetStatsVide: CabinetStats = {
  top_entreprises: [],
  missions_par_prestation: [],
  missions_par_etat: { en_cours: 0, terminee: 0, suspendue: 0, annulee: 0 },
  creances: {
    total_impaye: 0,
    aging: { retard_15_30: 0, retard_30_60: 0, retard_60_plus: 0 },
    top_debiteurs: [],
  },
}

async function mountOnglet(exerciceId: number | null = 1) {
  return mountPage(OngletCabinet, { props: { exerciceId } })
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetCabinetStats.mockResolvedValue({ data: cabinetStatsFull })
})

describe('OngletCabinet — chargement', () => {
  it('affiche le spinner pendant le fetch puis les 4 blocs', async () => {
    let resolveFetch!: (v: unknown) => void
    mockGetCabinetStats.mockReturnValue(new Promise((r) => { resolveFetch = r }))

    const { wrapper } = await mountOnglet()

    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Top entreprises contributrices')

    resolveFetch({ data: cabinetStatsFull })
    await flushPromises()

    const h2s = wrapper.findAll('h2').map((h) => h.text())
    expect(h2s).toEqual([
      'Top entreprises contributrices',
      'Répartition des missions par prestation',
      'Missions par état',
      'Créances',
    ])
  })

  it('appelle statsApi.getCabinetStats avec l\'exercice fourni', async () => {
    await mountOnglet(3)
    expect(mockGetCabinetStats).toHaveBeenCalledWith(3)
  })
})

describe('OngletCabinet — erreur de chargement', () => {
  it('affiche un etat d\'erreur accessible et relance le fetch au clic sur Réessayer', async () => {
    mockGetCabinetStats.mockRejectedValueOnce(new Error('network down'))
    const { wrapper } = await mountOnglet()

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(wrapper.text()).toContain('Impossible de charger les statistiques du cabinet.')
    expect(wrapper.text()).not.toContain('Top entreprises contributrices')

    const retry = findButton(wrapper, 'Réessayer')
    expect(retry).toBeTruthy()
    await retry!.trigger('click')
    await flushPromises()

    expect(mockGetCabinetStats).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Top entreprises contributrices')
  })
})

describe('OngletCabinet — refetch au changement d\'exercice', () => {
  it('refetch quand la prop exerciceId change', async () => {
    const { wrapper } = await mountOnglet(1)
    expect(mockGetCabinetStats).toHaveBeenCalledTimes(1)
    expect(mockGetCabinetStats).toHaveBeenLastCalledWith(1)

    await wrapper.setProps({ exerciceId: 2 })
    await flushPromises()

    expect(mockGetCabinetStats).toHaveBeenCalledTimes(2)
    expect(mockGetCabinetStats).toHaveBeenLastCalledWith(2)
  })
})

describe('OngletCabinet — bloc Top entreprises', () => {
  it('affiche la table sr-only avec les montants formates exactement', async () => {
    const { wrapper } = await mountOnglet()

    expect(wrapper.text()).toContain('CA facturé HT, avoirs déduits')
    expect(wrapper.text()).toContain('SARL Atlas')
    expect(wrapper.text()).toContain('EURL Numidia')
    expect(wrapper.text()).toMatch(/1\s*250\s*000,00\s*DA/)
    expect(wrapper.text()).toMatch(/800\s*000,00\s*DA/)

    const caption = wrapper.findAll('caption').find((c) => c.text().includes('Top entreprises contributrices'))
    expect(caption).toBeTruthy()
  })

  it('affiche l\'etat vide du bloc quand aucune entreprise', async () => {
    mockGetCabinetStats.mockResolvedValue({ data: cabinetStatsVide })
    const { wrapper } = await mountOnglet()

    expect(wrapper.text()).toContain('Aucune donnée sur cet exercice.')
  })
})

describe('OngletCabinet — bloc missions par prestation', () => {
  it('affiche la table sr-only avec les designations et totaux', async () => {
    const { wrapper } = await mountOnglet()

    expect(wrapper.text()).toContain('Assistance comptable')
    expect(wrapper.text()).toContain('Audit')
    const caption = wrapper.findAll('caption').find((c) => c.text().includes('Répartition des missions par prestation'))
    expect(caption).toBeTruthy()
  })
})

describe('OngletCabinet — bloc missions par état', () => {
  it('affiche la table sr-only avec les 4 etats', async () => {
    const { wrapper } = await mountOnglet()
    const table = wrapper.findAll('table').find((t) => t.text().includes('En cours'))
    expect(table).toBeTruthy()
    expect(table!.text()).toContain('En cours')
    expect(table!.text()).toContain('5')
    expect(table!.text()).toContain('Terminée')
    expect(table!.text()).toContain('8')
    expect(table!.text()).toContain('Suspendue')
    expect(table!.text()).toContain('2')
    expect(table!.text()).toContain('Annulée')
    expect(table!.text()).toContain('1')
  })

  it('affiche l\'etat vide quand toutes les missions sont a 0', async () => {
    mockGetCabinetStats.mockResolvedValue({ data: cabinetStatsVide })
    const { wrapper } = await mountOnglet()
    expect(wrapper.text()).toContain('Aucune donnée sur cet exercice.')
  })
})

describe('OngletCabinet — bloc créances', () => {
  it('affiche le total impaye en gros chiffre avec le title exact', async () => {
    const { wrapper } = await mountOnglet()

    const total = wrapper.find('.cab-total-value')
    expect(total.exists()).toBe(true)
    expect(total.attributes('title')).toMatch(/1\s*345\s*000,00\s*DA/)
    expect(wrapper.text()).toContain('avoirs déduits')
  })

  it('affiche les 3 barres d\'aging avec montants compacts', async () => {
    const { wrapper } = await mountOnglet()

    expect(wrapper.text()).toContain('15 – 30 jours')
    expect(wrapper.text()).toContain('30 – 60 jours')
    expect(wrapper.text()).toContain('60 jours et +')

    const bars = wrapper.findAll('.cab-bar')
    expect(bars).toHaveLength(3)
  })

  it('affiche le top 5 débiteurs avec des liens vers la fiche entreprise', async () => {
    const { wrapper } = await mountOnglet()

    const links = wrapper.findAll('a').filter((a) => a.attributes('href')?.startsWith('/entreprises/'))
    expect(links).toHaveLength(2)

    const first = links.find((a) => a.attributes('href') === '/entreprises/3')
    expect(first).toBeTruthy()
    expect(first!.text()).toContain('SPA Aures')
    expect(first!.attributes('aria-label')).toContain('SPA Aures')

    expect(wrapper.text()).toMatch(/600\s*000,00\s*DA/)
    expect(wrapper.text()).toMatch(/400\s*000,00\s*DA/)
  })

  it('affiche les etats vides du bloc créances quand il n\'y a aucune donnée', async () => {
    mockGetCabinetStats.mockResolvedValue({ data: cabinetStatsVide })
    const { wrapper } = await mountOnglet()

    // 3 messages "Aucune donnée" : top entreprises, missions par prestation,
    // missions par etat, aging, top debiteurs -> au moins un par bloc vide
    const messages = wrapper.findAll('p').filter((p) => p.text() === 'Aucune donnée sur cet exercice.')
    expect(messages.length).toBeGreaterThanOrEqual(4)
  })
})
