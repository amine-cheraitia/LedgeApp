import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetAll } = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
}))

vi.mock('@/api/modules/exercices', () => ({
  exercicesApi: {
    getAll: mockGetAll,
  },
}))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import StatistiquesPage from '@/pages/statistiques/StatistiquesPage.vue'
import { mountPage, findButton } from '../helpers/mount'

const exercicesFixture = [
  { id: 1, annee: 2026, statut: 'ouvert' },
  { id: 2, annee: 2025, statut: 'cloture' },
]

async function mountStats() {
  return mountPage(StatistiquesPage, {
    stubs: {
      OngletCabinet: true,
      OngletCollaborateurs: true,
    },
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: exercicesFixture })
})

describe('StatistiquesPage', () => {
  it('affiche le titre, le filtre exercice et les 2 onglets', async () => {
    const { wrapper } = await mountStats()

    expect(wrapper.find('h1').text()).toBe('Statistiques')
    expect(wrapper.find('label[for="stats-exercice"]').text()).toBe('Exercice')

    const tabs = wrapper.findAll('[role="tab"]').map((t) => t.text())
    expect(tabs).toContain('Cabinet')
    expect(tabs).toContain('Collaborateurs')
  })

  it('transmet l\'exercice ouvert par defaut aux onglets stubbes', async () => {
    const { wrapper } = await mountStats()

    expect(wrapper.findComponent({ name: 'OngletCabinet' }).props('exerciceId')).toBe(1)
  })

  it('affiche un etat d\'erreur accessible et relance le chargement des exercices au clic sur Réessayer', async () => {
    mockGetAll.mockRejectedValueOnce(new Error('network down'))
    const { wrapper } = await mountStats()

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(wrapper.text()).toContain('Impossible de charger les exercices. Veuillez réessayer.')

    const retry = findButton(wrapper, 'Réessayer')
    expect(retry).toBeTruthy()
    await retry!.trigger('click')
    await flushPromises()

    expect(mockGetAll).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.find('h1').text()).toBe('Statistiques')
  })
})
