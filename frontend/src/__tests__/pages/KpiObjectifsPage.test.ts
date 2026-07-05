import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { KpiCollaborateur } from '@/api/modules/stats'
import type { Exercice } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetKpi, mockUpsert, mockDeleteKpi, mockGetExercices } = vi.hoisted(() => ({
  mockGetKpi: vi.fn(),
  mockUpsert: vi.fn(),
  mockDeleteKpi: vi.fn(),
  mockGetExercices: vi.fn(),
}))

vi.mock('@/api/modules/stats', () => ({
  statsApi: {
    getKpiObjectifs: mockGetKpi,
    upsertKpiObjectif: mockUpsert,
    deleteKpiObjectif: mockDeleteKpi,
  },
}))

vi.mock('@/api/modules/exercices', () => ({
  exercicesApi: { getAll: mockGetExercices },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import KpiObjectifsPage from '@/pages/dashboard/KpiObjectifsPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const exercices: Exercice[] = [
  { id: 1, annee: 2026, date_ouverture: '2026-01-01', date_cloture: '2026-12-31', statut: 'ouvert', created_at: '', updated_at: '' },
  { id: 2, annee: 2025, date_ouverture: '2025-01-01', date_cloture: '2025-12-31', statut: 'cloture', created_at: '', updated_at: '' },
]

function makeCollabs(): KpiCollaborateur[] {
  return [
    {
      user: { id: 10, name: 'ali benali', email: 'ali@ledge.dz' },
      objectifs: { ca_ht: 1000000 },
      realise: { ca_ht: 500000, missions_cloturees: 3, taches_terminees: 12, taches_en_retard: 2, delai_moyen_tache: 3.46 },
    },
    {
      user: { id: 11, name: 'Sara Khelifi', email: 'sara@ledge.dz' },
      objectifs: {},
      realise: { ca_ht: 0, missions_cloturees: 0, taches_terminees: 5, taches_en_retard: 0, delai_moyen_tache: 1.2 },
    },
  ]
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetExercices.mockResolvedValue({ data: exercices })
  mockGetKpi.mockResolvedValue({ data: makeCollabs() })
})

describe('KpiObjectifsPage — chargement initial', () => {
  it('charge les exercices, sélectionne l exercice ouvert et charge les KPI', async () => {
    const { wrapper } = await mountPage(KpiObjectifsPage)

    expect(mockGetExercices).toHaveBeenCalledOnce()
    expect(mockGetKpi).toHaveBeenCalledWith(1)
    expect((wrapper.vm as any).exerciceId).toBe(1)

    expect(wrapper.text()).toContain('KPI objectifs collaborateurs')
    expect(wrapper.text()).toContain('ali benali')
    expect(wrapper.text()).toContain('sara@ledge.dz')
    // avatar = initiale majuscule
    expect(wrapper.find('.collab-avatar').text()).toBe('A')
  })

  it('pré-remplit les valeurs d édition depuis les objectifs existants', async () => {
    const { wrapper } = await mountPage(KpiObjectifsPage)
    const editing = (wrapper.vm as any).editing

    expect(editing['10-ca_ht']).toBe(1000000)
    expect(editing['10-missions_cloturees']).toBeNull()
    expect(editing['11-ca_ht']).toBeNull()
  })

  it('affiche l état de chargement pendant l appel KPI', async () => {
    mockGetKpi.mockReturnValue(new Promise(() => {}))
    const { wrapper } = await mountPage(KpiObjectifsPage)

    const status = wrapper.find('[role="status"]')
    expect(status.exists()).toBe(true)
    expect(wrapper.text()).toContain('Chargement…')
  })

  it('affiche l état vide quand aucun collaborateur', async () => {
    mockGetKpi.mockResolvedValue({ data: [] })
    const { wrapper } = await mountPage(KpiObjectifsPage)

    expect(wrapper.text()).toContain('Aucun collaborateur trouvé.')
  })

  it('affiche un toast erreur si le chargement des KPI échoue', async () => {
    mockGetKpi.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountPage(KpiObjectifsPage)

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les KPI.' }),
    )
    expect((wrapper.vm as any).loading).toBe(false)
  })

  it('sans exercice ouvert, charge les KPI sans filtre et désactive la saisie', async () => {
    mockGetExercices.mockResolvedValue({ data: [exercices[1]] })
    const { wrapper } = await mountPage(KpiObjectifsPage)

    expect((wrapper.vm as any).exerciceId).toBeNull()
    expect(mockGetKpi).toHaveBeenCalledWith(null)
    const bouton = findButton(wrapper, 'Sauvegarder les objectifs de ali benali')!
    expect(bouton.attributes('disabled')).toBeDefined()
  })
})

describe('KpiObjectifsPage — progression et formats', () => {
  it('affiche la progression quand un objectif est fixé, sinon "Pas d objectif fixé"', async () => {
    const { wrapper } = await mountPage(KpiObjectifsPage)
    const text = wrapper.text()

    // 500000 / 1000000 = 50 %
    expect(text).toContain('50%')
    expect(text).toContain("Pas d'objectif fixé")
    // realises formates : DA pour le CA, arrondi pour les compteurs, delai en jours
    expect(text).toContain('DA')
    expect(text).toMatch(/12\s*terminées/)
    expect(text).toMatch(/2\s*en retard/)
    expect(text).toContain('3.5 j')
  })

  it('borne le pourcentage à 100 et gère l absence d objectif', async () => {
    const { wrapper } = await mountPage(KpiObjectifsPage)
    const vm = wrapper.vm as any

    expect(vm.pourcentage(150, 100)).toBe(100)
    expect(vm.pourcentage(50, 100)).toBe(50)
    expect(vm.pourcentage(10, undefined)).toBe(0)
    expect(vm.pourcentage(10, 0)).toBe(0)
  })

  it('déduit la sévérité de la barre de progression par palier', async () => {
    const { wrapper } = await mountPage(KpiObjectifsPage)
    const vm = wrapper.vm as any

    expect(vm.progressSeverity(100)).toBe('success')
    expect(vm.progressSeverity(70)).toBe('info')
    expect(vm.progressSeverity(40)).toBe('warn')
    expect(vm.progressSeverity(10)).toBe('danger')
  })

  it('formate les valeurs selon leur unité (DA, jours, entier)', async () => {
    const { wrapper } = await mountPage(KpiObjectifsPage)
    const vm = wrapper.vm as any

    expect(vm.formatValeur(120000, 'DA')).toContain('DA')
    expect(vm.formatValeur(3.14, 'jours')).toBe('3.1 j')
    expect(vm.formatValeur(4.6, '')).toBe('5')
  })

  it('construit les options du filtre exercice avec le suffixe (ouvert)', async () => {
    const { wrapper } = await mountPage(KpiObjectifsPage)

    expect((wrapper.vm as any).exerciceOptions).toEqual([
      { label: '2026 (ouvert)', value: 1 },
      { label: '2025', value: 2 },
    ])
  })
})
