import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const {
  mockGetOne,
  mockMissionsGetAll,
  mockDevisGetAll,
  mockFacturesGetAll,
  mockExercicesGetAll,
} = vi.hoisted(() => ({
  mockGetOne: vi.fn(),
  mockMissionsGetAll: vi.fn(),
  mockDevisGetAll: vi.fn(),
  mockFacturesGetAll: vi.fn(),
  mockExercicesGetAll: vi.fn(),
}))

vi.mock('@/api/modules/entreprises', () => ({ entreprisesApi: { getOne: mockGetOne } }))
vi.mock('@/api/modules/missions', () => ({ missionsApi: { getAll: mockMissionsGetAll } }))
vi.mock('@/api/modules/devis', () => ({ devisApi: { getAll: mockDevisGetAll } }))
vi.mock('@/api/modules/factures', () => ({ facturesApi: { getAll: mockFacturesGetAll } }))
vi.mock('@/api/modules/exercices', () => ({ exercicesApi: { getAll: mockExercicesGetAll } }))

// ── Mock PrimeVue toast (utilise par useExercices) ───────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import EntrepriseDetailPage from '@/pages/entreprises/EntrepriseDetailPage.vue'
import { mountPage, findButton } from '../helpers/mount'
import type { MountPageOptions } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const entreprise = {
  id: 1,
  raison_sociale: 'SARL Etoile',
  nif: '00123456789',
  nis: '98765',
  num_rc: null,
  article_imposition: null,
  regime_fiscal: 'Reel',
  categorie: 'PME',
  secteur_activite: null,
  adresse: '12 rue des Oliviers',
  ville: 'Alger',
  wilaya: 'Alger',
  telephone: '0550000000',
  email: 'contact@etoile.dz',
  contact_principal: null,
  statut: 'client',
  notes: 'Client fidele depuis 2020',
  contacts: [
    {
      id: 1, entreprise_id: 1, nom: 'Benali', prenom: 'Karim',
      email: 'k.benali@etoile.dz', telephone: '0551111111', poste: 'Gerant',
      est_principal: true, created_at: '', updated_at: '',
    },
    {
      id: 2, entreprise_id: 1, nom: 'Ziani', prenom: null,
      email: null, telephone: null, poste: null,
      est_principal: false, created_at: '', updated_at: '',
    },
  ],
  created_at: '',
  updated_at: '',
}

const exercices = [
  { id: 10, annee: 2026, date_ouverture: '2026-01-01', date_cloture: '2026-12-31', statut: 'ouvert', created_at: '', updated_at: '' },
  { id: 9, annee: 2025, date_ouverture: '2025-01-01', date_cloture: '2025-12-31', statut: 'cloture', created_at: '', updated_at: '' },
]

const missions = [
  {
    id: 5, reference: 'M2026-001', statut: 'en_cours', prix_ht: 315000,
    date_debut: '2026-01-15', date_fin: null,
    prestation: { designation: 'Assistance comptable' },
  },
  {
    id: 6, reference: 'M2026-002', statut: 'terminee', prix_ht: 100000,
    date_debut: '2026-02-01', date_fin: '2026-05-01',
    prestation: { designation: 'Audit legal' },
  },
]

const devisList = [
  {
    id: 3, numero: 'DV2026-001', statut: 'envoye', montant_ttc: 119000,
    date_devis: '2026-02-10', date_validite: '2026-03-10', prestation: null,
  },
]

const facturesExercice = [
  {
    id: 11, numero: 'FF2026-001', type: 'FF', montant_ttc: 238000,
    montant_restant: 238000, statut_paiement: 'en_attente',
    date_facture: '2026-03-10', date_echeance: '2026-04-10',
  },
]

// KPI (tous exercices) : CA FF = 200000 (2026) + 100000 (2025) => variation +100% up.
// Impayes FF non soldes = 238000 sur 2 factures. La FA et la facture sans date
// couvrent les branches d'exclusion des computed.
const facturesKpi = [
  { id: 11, numero: 'FF2026-001', type: 'FF', montant_ht: 200000, montant_restant: 238000, statut_paiement: 'en_attente', date_facture: '2026-03-10' },
  { id: 12, numero: 'FF2025-004', type: 'FF', montant_ht: 100000, montant_restant: 0, statut_paiement: 'solde', date_facture: '2025-06-01' },
  { id: 13, numero: 'FA2026-001', type: 'FA', montant_ht: 50000, montant_restant: 5000, statut_paiement: 'en_attente', date_facture: '2026-04-01' },
  { id: 14, numero: 'FF2026-009', type: 'FF', montant_ht: 0, montant_restant: 0, statut_paiement: 'partiel', date_facture: null },
]

function mountDetail(opts: MountPageOptions = {}) {
  return mountPage(EntrepriseDetailPage, {
    path: '/entreprises/1',
    routePattern: '/entreprises/:id',
    ...opts,
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetOne.mockResolvedValue({ data: entreprise })
  mockExercicesGetAll.mockResolvedValue({ data: exercices })
  mockMissionsGetAll.mockResolvedValue({ data: missions })
  mockDevisGetAll.mockResolvedValue({ data: devisList })
  mockFacturesGetAll.mockImplementation((params?: { per_page?: number }) =>
    Promise.resolve({ data: params?.per_page === 500 ? facturesKpi : facturesExercice }),
  )
})

describe('EntrepriseDetailPage — chargement', () => {
  it('charge l entreprise depuis le param de route et affiche coordonnees, contacts et notes', async () => {
    const { wrapper } = await mountDetail()

    expect(mockGetOne).toHaveBeenCalledWith(1)
    const text = wrapper.text()
    expect(text).toContain('SARL Etoile')
    expect(text).toContain('00123456789')
    expect(text).toContain('Reel')
    expect(text).toContain('PME')
    expect(text).toContain('12 rue des Oliviers')
    expect(text).toContain('Contacts (2)')
    expect(text).toContain('Benali Karim')
    expect(text).toContain('Principal')
    expect(text).toContain('Gerant')
    expect(text).toContain('Ziani')
    expect(text).toContain('Client fidele depuis 2020')
  })

  it('pre-selectionne l exercice ouvert et filtre missions/devis/factures avec celui-ci', async () => {
    const { wrapper } = await mountDetail()

    expect(mockMissionsGetAll).toHaveBeenCalledWith({ entreprise_id: 1, exercice_id: 10, per_page: 100 })
    expect(mockDevisGetAll).toHaveBeenCalledWith({ entreprise_id: 1, exercice_id: 10, per_page: 100 })
    expect(mockFacturesGetAll).toHaveBeenCalledWith({ entreprise_id: 1, exercice_id: 10, per_page: 100 })
    // KPI : factures tous exercices confondus, sans filtre exercice
    expect(mockFacturesGetAll).toHaveBeenCalledWith({ entreprise_id: 1, per_page: 500 })

    const text = wrapper.text()
    expect(text).toContain('M2026-001')
    expect(text).toContain('Assistance comptable')
    expect(text).toContain('DV2026-001')
    expect(text).toContain('FF2026-001')
    // devis sans prestation -> tiret
    expect(text).toContain('—')
  })
})

describe('EntrepriseDetailPage — KPIs', () => {
  it('calcule CA total HT (FF uniquement), impayes et tendance vs exercice precedent', async () => {
    const { wrapper } = await mountDetail()
    const vm = wrapper.vm as any

    expect(vm.totalFacture).toBe(300000) // la FA de 50000 est exclue
    expect(vm.totalImpaye).toBe(238000) // FF non soldees uniquement
    expect(vm.nbFacturesImpayees).toBe(2)
    expect(vm.missionsActives).toBe(1)
    expect(vm.exerciceCourantAnnee).toBe(2026)
    expect(vm.variationCa).toEqual({ pct: 100, direction: 'up' })

    const text = wrapper.text()
    expect(text).toContain('Impayé')
    expect(text).toContain('2 factures')
    expect(text).toContain('CA total')
    expect(text).toContain('100% vs 2025')
    expect(text).toContain('Missions actives')
    expect(text).toContain('Exercice 2026')
  })

  it('expose les KPIs en role=status avec des aria-label explicites (RGAA)', async () => {
    const { wrapper } = await mountDetail()

    const statuses = wrapper.findAll('[role="status"]')
    const labels = statuses.map(s => s.attributes('aria-label') ?? '')
    expect(labels.some(l => l.includes("Chiffre d'affaires total HT"))).toBe(true)
    expect(labels.some(l => l.includes('Montant impayé'))).toBe(true)
    expect(labels.some(l => l.includes('Missions actives'))).toBe(true)
  })

  it('retombe sur une liste vide si le fetch KPI echoue (pas de carte impaye)', async () => {
    mockFacturesGetAll.mockImplementation((params?: { per_page?: number }) =>
      params?.per_page === 500 ? Promise.reject(new Error('500')) : Promise.resolve({ data: facturesExercice }),
    )
    const { wrapper } = await mountDetail()
    const vm = wrapper.vm as any

    expect(vm.facturesKpi).toEqual([])
    expect(vm.totalFacture).toBe(0)
    expect(wrapper.text()).not.toContain('Impayé')
    expect(wrapper.text()).toContain('0 DA')
  })

  it('sans exercice ouvert : fetch sans filtre et libelles par defaut', async () => {
    mockExercicesGetAll.mockResolvedValue({
      data: [{ id: 9, annee: 2025, date_ouverture: '2025-01-01', date_cloture: '2025-12-31', statut: 'cloture', created_at: '', updated_at: '' }],
    })
    const { wrapper } = await mountDetail()
    const vm = wrapper.vm as any

    expect(vm.exerciceSelectionne).toBeNull()
    expect(vm.exerciceCourantAnnee).toBeNull()
    expect(vm.variationCa).toBeNull()
    expect(mockMissionsGetAll).toHaveBeenCalledWith({ entreprise_id: 1, exercice_id: undefined, per_page: 100 })
    expect(wrapper.text()).toContain('Exercice courant')
    expect(wrapper.text()).toContain('Tous exercices, HT')
  })
})

describe('EntrepriseDetailPage — filtre exercice', () => {
  it('applyExercice(null) recharge les trois listes sans filtre exercice', async () => {
    const { wrapper } = await mountDetail()
    vi.clearAllMocks()
    mockMissionsGetAll.mockResolvedValue({ data: [] })
    mockDevisGetAll.mockResolvedValue({ data: [] })
    mockFacturesGetAll.mockResolvedValue({ data: [] })

    await (wrapper.vm as any).applyExercice(null)
    await flushPromises()

    expect((wrapper.vm as any).exerciceSelectionne).toBeNull()
    expect(mockMissionsGetAll).toHaveBeenCalledWith({ entreprise_id: 1, exercice_id: undefined, per_page: 100 })
    expect(mockDevisGetAll).toHaveBeenCalledWith({ entreprise_id: 1, exercice_id: undefined, per_page: 100 })
    expect(mockFacturesGetAll).toHaveBeenCalledWith({ entreprise_id: 1, exercice_id: undefined, per_page: 100 })
  })

  it('affiche les messages vides quand aucune donnee pour l exercice', async () => {
    mockMissionsGetAll.mockResolvedValue({ data: [] })
    mockDevisGetAll.mockResolvedValue({ data: [] })
    mockFacturesGetAll.mockResolvedValue({ data: [] })
    const { wrapper } = await mountDetail()

    const text = wrapper.text()
    expect(text).toContain('Aucune mission pour cet exercice.')
    expect(text).toContain('Aucun devis pour cet exercice.')
    expect(text).toContain('Aucune facture pour cet exercice.')
    expect(text).not.toContain('Impayé')
  })
})

describe('EntrepriseDetailPage — role secretaire', () => {
  it('masque l onglet et le KPI missions et n appelle jamais l API missions', async () => {
    const { wrapper } = await mountDetail({ role: 'secretaire' })

    expect(mockMissionsGetAll).not.toHaveBeenCalled()
    expect(wrapper.text()).not.toContain('Missions actives')

    const tabs = wrapper.findAll('[role="tab"]').map(t => t.text())
    expect(tabs.some(t => t.includes('Missions'))).toBe(false)
    expect(tabs.some(t => t.includes('Devis'))).toBe(true)
    expect(tabs.some(t => t.includes('Factures'))).toBe(true)
    // Les devis/factures restent charges
    expect(mockDevisGetAll).toHaveBeenCalled()
    expect(wrapper.text()).toContain('DV2026-001')
  })
})

describe('EntrepriseDetailPage — navigation', () => {
  it('le bouton retour renvoie a la liste des entreprises', async () => {
    const { wrapper, router } = await mountDetail()

    await findButton(wrapper, 'Retour a la liste')!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/entreprises')
  })

  it('le lien de reference mission navigue vers la fiche mission', async () => {
    const { wrapper, router } = await mountDetail()

    const lien = wrapper.findAll('a.link').find(a => a.text() === 'M2026-001')
    expect(lien).toBeTruthy()
    await lien!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/missions/5')
  })
})

describe('EntrepriseDetailPage — formatters', () => {
  it('gere les fallbacks montant/date et les couleurs de statut inconnues', async () => {
    const { wrapper } = await mountDetail()
    const vm = wrapper.vm as any

    expect(vm.formatMontant('abc')).toBe('0 DA')
    expect(vm.formatMontant(315000)).toContain('DA')
    expect(vm.formatDate(null)).toBe('—')
    expect(vm.formatDate('2026-01-15')).toContain('2026')

    expect(vm.statutMissionColor('annulee')).toBe('danger')
    expect(vm.statutMissionColor('inconnu')).toBe('secondary')
    expect(vm.statutDevisColor('refuse')).toBe('danger')
    expect(vm.statutDevisColor('inconnu')).toBe('secondary')
    expect(vm.statutFactureColor('solde')).toBe('success')
    expect(vm.statutFactureColor('inconnu')).toBe('secondary')
    expect(vm.statutEntrepriseColor('prospect')).toBe('warn')
    expect(vm.statutEntrepriseColor('inconnu')).toBe('secondary')
  })
})
