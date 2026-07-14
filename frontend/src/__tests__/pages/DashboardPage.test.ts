import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetDashboard, mockGetCollaborateur, mockGetSecretaire } = vi.hoisted(() => ({
  mockGetDashboard: vi.fn(),
  mockGetCollaborateur: vi.fn(),
  mockGetSecretaire: vi.fn(),
}))

vi.mock('@/api/modules/stats', () => ({
  statsApi: {
    getDashboard: mockGetDashboard,
    getCollaborateurDashboard: mockGetCollaborateur,
    getSecretaireDashboard: mockGetSecretaire,
  },
}))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import DashboardPage from '@/pages/dashboard/DashboardPage.vue'
import { mountPage, findButton } from '../helpers/mount'
import { useLayout } from '@/layout/composables/layout'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const daysAgo = (n: number) => new Date(Date.now() - n * 86400000).toISOString()
const daysAhead = (n: number) => new Date(Date.now() + n * 86400000).toISOString()

const adminStatsFull = {
  exercices: [
    { id: 1, annee: 2026, statut: 'ouvert' },
    { id: 2, annee: 2025, statut: 'cloture' },
  ],
  exercice_courant: 1,
  kpi: { ca_mois: 450000, tva_collectee: 85500, taux_recouvrement: 82, seuil_recouvrement: 70 },
  alertes: [
    { type: 'danger', message: 'Factures en retard critique' },
    { type: 'warn', message: 'Seuil de recouvrement proche' },
    { type: 'info', message: 'Nouvel exercice disponible' },
  ],
  entreprises: { total: 30, clients: 22, prospects: 8 },
  missions: { total: 14, en_cours: 6, terminees: 5, suspendues: 2, annulees: 1, ca_ht: 3000000 },
  ca_mensuel: { annee: 2026, data: [100000, 200000, 0, 0, 0, 0, 0, 0, 0, 0, 0, 300000] },
  factures: {
    total: 20, en_attente: 4, partielles: 3, soldees: 13,
    ca_ttc: 2400000, total_paye: 1900000, total_impaye: 500000, en_retard: 2,
  },
  devis: { total: 9, en_attente: 3, acceptes: 5, ca_potentiel: 800000 },
  recentes: {
    factures: [
      { id: 1, numero: 'FF2026-001', montant_ttc: 119000, statut_paiement: 'en_attente', date_facture: '2026-06-15', entreprise: { raison_sociale: 'SARL Atlas' } },
      { id: 2, numero: 'FF2026-002', montant_ttc: 238000, statut_paiement: 'solde', date_facture: '2026-06-20' },
      { id: 3, numero: 'FF2026-003', montant_ttc: 50000, statut_paiement: 'partiel', date_facture: '2026-06-22', entreprise: { raison_sociale: 'EURL Numidia' } },
      { id: 4, numero: 'FF2026-004', montant_ttc: 10000, statut_paiement: 'inconnu', date_facture: '2026-06-25', entreprise: { raison_sociale: 'SPA Aures' } },
    ],
    missions: [
      { id: 11, reference: 'M2026-001', statut: 'en_cours', prix_ht: 315000, entreprise: { raison_sociale: 'SARL Atlas' }, prestation: { libelle: 'Assistance comptable' }, date_debut: '2026-01-10' },
      { id: 12, reference: 'M2026-002', statut: 'mystere', prix_ht: 100000 },
    ],
  },
}

const adminStatsVide = {
  ...adminStatsFull,
  kpi: { ca_mois: 0, tva_collectee: 0, taux_recouvrement: 40, seuil_recouvrement: 70 },
  alertes: [],
  missions: { total: 0, en_cours: 0, terminees: 0, suspendues: 0, annulees: 0, ca_ht: 0 },
  ca_mensuel: { annee: 2026, data: Array(12).fill(0) },
  factures: {
    total: 0, en_attente: 0, partielles: 0, soldees: 0,
    ca_ttc: 0, total_paye: 0, total_impaye: 0, en_retard: 0,
  },
  recentes: { factures: [], missions: [] },
}

const collabStatsFull = {
  missions: { total: 8, en_cours: 3, terminees: 5 },
  taches: { total: 10, a_faire: 1, en_cours: 1, terminees: 7, bloquees: 1, taux_completion: 70, en_retard: 2 },
  mes_missions: [
    { id: 1, reference: 'M2026-010', statut: 'en_cours', entreprise: 'SARL Atlas', prestation: 'ACMPT', date_fin: null, taches_total: 4, taches_terminees: 2, progression: 50 },
    { id: 2, reference: 'M2026-011', statut: 'terminee', entreprise: null, prestation: null, date_fin: null, taches_total: 2, taches_terminees: 2, progression: 100 },
    { id: 3, reference: 'M2026-012', statut: 'suspendue', entreprise: 'EURL Numidia', prestation: null, date_fin: null, taches_total: 0, taches_terminees: 0, progression: 0 },
  ],
  mes_taches_urgentes: [
    { id: 21, mission_id: 1, titre: 'Saisie comptable mai', statut: 'en_cours', priorite: 4, date_fin: daysAhead(2), mission_reference: 'M2026-010', entreprise: 'SARL Atlas', en_retard: false },
    { id: 22, mission_id: 2, titre: 'Relance pieces manquantes', statut: 'a_faire', priorite: 1, date_fin: null, mission_reference: 'M2026-011', entreprise: null, en_retard: false },
    { id: 23, mission_id: 3, titre: 'Declaration G50', statut: 'a_faire', priorite: 3, date_fin: daysAgo(2), mission_reference: 'M2026-012', entreprise: 'EURL Numidia', en_retard: true },
  ],
  echeances: [
    { type: 'mission', id: 1, titre: 'Cloture M2026-010', entreprise: 'SARL Atlas', mission_id: 1, mission_reference: 'M2026-010', date: daysAhead(3), en_retard: false },
    { type: 'tache', id: 21, titre: 'Saisie comptable mai', entreprise: null, mission_id: null, mission_reference: null, date: daysAhead(20), en_retard: false },
    { type: 'tache', id: 23, titre: 'Declaration G50', entreprise: 'EURL Numidia', mission_id: 3, mission_reference: 'M2026-012', date: daysAgo(2), en_retard: true },
  ],
  activite_recente: [
    { id: 31, titre: 'Bilan valide', mission_reference: 'M2026-010', mission_id: 1, date: daysAgo(0) },
    { id: 32, titre: 'TVA deposee', mission_reference: null, mission_id: null, date: daysAgo(1) },
    { id: 33, titre: 'Rapport envoye', mission_reference: 'M2026-012', mission_id: 3, date: daysAgo(3) },
    { id: 34, titre: 'Ancienne tache', mission_reference: 'M2026-011', mission_id: 2, date: daysAgo(20) },
  ],
}

const collabStatsVide = {
  missions: { total: 0, en_cours: 0, terminees: 0 },
  taches: { total: 0, a_faire: 0, en_cours: 0, terminees: 0, bloquees: 0, taux_completion: 0, en_retard: 0 },
  mes_missions: [],
  mes_taches_urgentes: [],
  echeances: [],
  activite_recente: [],
}

const secretaireStats = {
  alertes: [],
  actions: [],
  creances: { total_impaye: 0, clients_debiteurs: 0, en_retard: 0 },
  aging: { retard_15_30: 0, retard_30_60: 0, retard_60_plus: 0 },
  relances_dues: { niveau_1: 0, niveau_2: 0, niveau_3: 0 },
  top_debiteurs: [],
  encaissements_mois: { count: 0, montant: 0 },
  recentes_creances: [],
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetDashboard.mockResolvedValue({ data: adminStatsFull })
  mockGetCollaborateur.mockResolvedValue({ data: collabStatsFull })
  mockGetSecretaire.mockResolvedValue({ data: secretaireStats })
})

afterEach(() => {
  // layoutConfig est un etat module partage -> on remet le theme clair
  useLayout().layoutConfig.darkTheme = false
})

describe('DashboardPage — chargement', () => {
  it('affiche le spinner pendant le fetch puis le contenu admin', async () => {
    let resolveFetch!: (v: unknown) => void
    mockGetDashboard.mockReturnValue(new Promise((r) => { resolveFetch = r }))

    const { wrapper } = await mountPage(DashboardPage)

    expect(wrapper.find('.p-progressspinner').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('CA du mois')

    resolveFetch({ data: adminStatsFull })
    await flushPromises()

    expect(wrapper.find('.p-progressspinner').exists()).toBe(false)
    expect(wrapper.text()).toContain('CA du mois')
  })
})

describe('DashboardPage — erreur de chargement', () => {
  it('affiche un etat d\'erreur accessible et relance le fetch au clic sur Réessayer', async () => {
    mockGetDashboard.mockRejectedValueOnce(new Error('network down'))
    const { wrapper } = await mountPage(DashboardPage)

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(wrapper.text()).toContain('Impossible de charger le tableau de bord.')
    expect(wrapper.text()).not.toContain('CA du mois')

    const retry = findButton(wrapper, 'Réessayer')
    expect(retry).toBeTruthy()
    await retry!.trigger('click')
    await flushPromises()

    expect(mockGetDashboard).toHaveBeenCalledTimes(2)
    expect(wrapper.find('.dashboard-error').exists()).toBe(false)
    expect(wrapper.text()).toContain('CA du mois')
  })
})

describe('DashboardPage — CA du mois indisponible', () => {
  it('affiche "—" et un message explicatif quand ca_mois est null (exercice hors annee courante)', async () => {
    mockGetDashboard.mockResolvedValue({
      data: { ...adminStatsFull, kpi: { ...adminStatsFull.kpi, ca_mois: null } },
    })
    const { wrapper } = await mountPage(DashboardPage)

    expect(wrapper.text()).toContain("Mois courant hors de l'exercice sélectionné")
    const figures = wrapper.findAll('.dash-figure').map((el) => el.text())
    expect(figures).toContain('—')
  })
})

describe('DashboardPage — admin', () => {
  it('charge les stats admin et affiche KPI, alertes et tables recentes', async () => {
    const { wrapper } = await mountPage(DashboardPage)

    expect(mockGetDashboard).toHaveBeenCalledWith(null)
    expect(mockGetCollaborateur).not.toHaveBeenCalled()

    // Header + filtre exercice (admin uniquement)
    expect(wrapper.text()).toContain('Tableau de bord')
    expect(wrapper.text()).toContain('Bienvenue, Test User')
    expect(wrapper.find('#filtre-exercice').exists()).toBe(true)

    // Alertes (3 severites)
    expect(wrapper.text()).toContain('Factures en retard critique')
    expect(wrapper.text()).toContain('Seuil de recouvrement proche')
    expect(wrapper.text()).toContain('Nouvel exercice disponible')

    // KPI
    expect(wrapper.text()).toContain('CA du mois')
    expect(wrapper.text()).toContain('TVA collectée')
    expect(wrapper.text()).toContain('82%')
    expect(wrapper.text()).toContain('Seuil : 70%')
    expect(wrapper.text()).toContain('DA')

    // Formats montants : entier sous 1M (formatDAKpi), compact au-dela
    // (Intl.NumberFormat fr-DZ utilise des espaces insecables entre groupes -> \s)
    expect(wrapper.text()).toMatch(/450\s*000\s*DA/) // ca_mois = 450000
    expect(wrapper.text()).toMatch(/85\s*500\s*DA/) // tva_collectee = 85500
    expect(wrapper.text()).toMatch(/500\s*000\s*DA/) // total_impaye = 500000
    expect(wrapper.text()).toMatch(/2,4\s*M DA/) // ca_ttc = 2400000 (compact)

    // Stat cards
    expect(wrapper.text()).toContain('22')
    expect(wrapper.text()).toContain('8 prospects')
    expect(wrapper.text()).toContain('5 terminées')
    expect(wrapper.text()).toContain('2 en retard')

    // Sous-lignes de scope exercice (aucun exercice selectionne par defaut)
    expect(wrapper.text()).toContain('Tous exercices') // carte Clients (globale)
    expect(wrapper.text()).toContain('Toutes années') // CA / Impayés / Devis (scopes)

    // Devis
    expect(wrapper.text()).toContain('CA potentiel')

    // Tables recentes : statuts connus + inconnus, entreprise absente -> '—'
    expect(wrapper.text()).toContain('FF2026-001')
    expect(wrapper.text()).toContain('SARL Atlas')
    expect(wrapper.text()).toContain('En attente')
    expect(wrapper.text()).toContain('Soldé')
    expect(wrapper.text()).toContain('Partiel')
    expect(wrapper.text()).toContain('inconnu')
    expect(wrapper.text()).toContain('M2026-001')
    expect(wrapper.text()).toContain('mystere')
    expect(wrapper.text()).toContain('—')

    // Progressbar recouvrement accessible (RGAA)
    const bar = wrapper.find('[aria-label="Taux de recouvrement : 82%"]')
    expect(bar.attributes('role')).toBe('progressbar')
    expect(bar.attributes('aria-valuenow')).toBe('82')
  })

  it('expose des donnees de charts coherentes (data, options, aria)', async () => {
    const { wrapper } = await mountPage(DashboardPage)
    const vm = wrapper.vm as any

    // CA mensuel
    expect(vm.caMensuelVide).toBe(false)
    expect(vm.caMensuelData.datasets[0].data).toEqual(adminStatsFull.ca_mensuel.data)
    expect(vm.caMensuelData.datasets[0].backgroundColor).toBe('#64748b') // theme clair
    expect(vm.caMensuelAria).toContain('Janvier')
    expect(vm.caMensuelAria).toContain('2026')

    // Callbacks des options Chart.js (formatDA / formatDACompact)
    const yTick = vm.caMensuelOptions.scales.y.ticks.callback(1500000)
    expect(yTick).toContain('DA')
    const caTooltip = vm.caMensuelOptions.plugins.tooltip.callbacks.label({ parsed: { y: 12345 } })
    expect(caTooltip).toContain('DA')

    // Missions par statut
    expect(vm.missionsVides).toBe(false)
    expect(vm.missionsData.datasets[0].data).toEqual([6, 5, 2, 1])
    expect(vm.missionsAria).toContain('En cours 6')
    const mTooltip = vm.missionsOptions.plugins.tooltip.callbacks.label({ label: 'En cours', parsed: 6 })
    expect(mTooltip).toBe('En cours : 6')

    // Table sr-only du graphique (RGAA)
    expect(wrapper.text()).toContain('Chiffre d\'affaires TTC par mois')
  })

  it('refetch les stats avec l\'exercice selectionne et met a jour le libelle d\'exercice', async () => {
    const { wrapper } = await mountPage(DashboardPage)
    const vm = wrapper.vm as any

    expect(wrapper.text()).toContain('Toutes années')

    vm.exerciceId = 2
    await vm.fetchStats()
    await nextTick()

    expect(mockGetDashboard).toHaveBeenLastCalledWith(2)
    expect(mockGetDashboard).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Exercice 2025')
  })

  it('rend les cartes KPI cliquables (drill-down) vers les bonnes pages', async () => {
    const { wrapper } = await mountPage(DashboardPage)

    const links = wrapper.findAll('a')
    const findByLabel = (needle: string) =>
      links.find((a) => a.attributes('aria-label')?.includes(needle))

    const impayes = findByLabel('Voir les créances — impayés')
    expect(impayes?.attributes('href')).toBe('/creances')
    expect(impayes?.attributes('aria-label')).toMatch(/500\s*000,00\s*DA/)

    const missions = findByLabel('Voir les missions — missions actives')
    expect(missions?.attributes('href')).toBe('/missions')

    const clients = findByLabel('Voir les entreprises — clients')
    expect(clients?.attributes('href')).toBe('/entreprises')

    const ca = findByLabel('Voir les factures — chiffre d\'affaires')
    expect(ca?.attributes('href')).toBe('/factures')

    const caMois = findByLabel('Voir les factures — CA du mois')
    expect(caMois?.attributes('href')).toBe('/factures')

    const recouvrement = findByLabel('Voir les créances — taux de recouvrement')
    expect(recouvrement?.attributes('href')).toBe('/creances')
  })

  it('affiche les etats vides (CA nul, aucune mission, aucun retard)', async () => {
    mockGetDashboard.mockResolvedValue({ data: adminStatsVide })
    const { wrapper } = await mountPage(DashboardPage)
    const vm = wrapper.vm as any

    expect(vm.caMensuelVide).toBe(true)
    expect(vm.missionsVides).toBe(true)
    expect(wrapper.text()).toContain('Aucune facture sur cet exercice.')
    expect(wrapper.text()).toContain('Aucune mission sur cet exercice.')
    expect(wrapper.text()).toContain('Aucun retard')
    expect(wrapper.text()).toContain('Aucune facture pour le moment.')
    expect(wrapper.text()).toContain('Aucune mission pour le moment.')
    // Taux sous le seuil -> rendu quand meme, style warn
    expect(wrapper.text()).toContain('40%')
    // Pas d'alertes
    expect(wrapper.text()).not.toContain('Factures en retard critique')
  })

  it('adapte les couleurs du chart au theme sombre', async () => {
    const { wrapper } = await mountPage(DashboardPage)
    const vm = wrapper.vm as any
    const { layoutConfig } = useLayout()

    expect(vm.caMensuelData.datasets[0].backgroundColor).toBe('#64748b')

    layoutConfig.darkTheme = true
    await nextTick()
    await flushPromises()

    expect(vm.caMensuelData.datasets[0].backgroundColor).toBe('#94a3b8')
  })
})

describe('DashboardPage — collaborateur', () => {
  it('charge les stats collaborateur : hero, KPI, donut, timeline, activite', async () => {
    const { wrapper } = await mountPage(DashboardPage, { role: 'collaborateur' })
    const vm = wrapper.vm as any

    expect(mockGetCollaborateur).toHaveBeenCalledOnce()
    expect(mockGetDashboard).not.toHaveBeenCalled()
    expect(wrapper.find('#filtre-exercice').exists()).toBe(false)

    // Hero banner : pluriels + chip succes (taux 70 >= 70) + alerte retard
    expect(wrapper.text()).toContain('Bonjour, Test User')
    expect(wrapper.text()).toContain('3 missions en cours')
    expect(wrapper.text()).toContain('1 tâche en cours')
    expect(wrapper.text()).toContain('70% complété')
    expect(wrapper.find('.hero-chip--success').exists()).toBe(true)
    expect(wrapper.text()).toContain('Tâches en retard')
    expect(wrapper.text()).toContain('2 tâches dépassées')

    // KPI cards : aria-labels sur les valeurs brutes (le count-up anime l'affichage)
    expect(wrapper.find('[aria-label="8 missions assignées"]').exists()).toBe(true)
    expect(wrapper.find('[aria-label="10 tâches"]').exists()).toBe(true)
    expect(wrapper.find('.kpi-card--red').exists()).toBe(true) // 1 tache bloquee
    expect(wrapper.text()).toContain('Nécessitent votre attention')
    expect(wrapper.text()).toContain('7 / 10 tâches terminées')

    // Donut : 4 segments actifs, gap applique, pourcentages
    expect(vm.donutSegments).toHaveLength(4)
    expect(vm.donutSegments[0].color).toBe('#22c55e')
    expect(vm.donutSegments[0].pct).toBe(70)
    expect(vm.donutSegments[3].color).toBe('#ef4444')
    expect(wrapper.text()).toContain('Bloquées')

    // Progression missions : les 3 classes de remplissage
    expect(wrapper.find('.bar-fill--green').exists()).toBe(true)
    expect(wrapper.find('.bar-fill--blue').exists()).toBe(true)
    expect(wrapper.find('.bar-fill--empty').exists()).toBe(true)
    expect(wrapper.text()).toContain('M2026-010')
    expect(wrapper.text()).toContain('Suspendue')

    // Echeances : badges Retard / J-x, urgent (<=7j) et ok (>7j)
    expect(wrapper.text()).toContain('Retard')
    expect(wrapper.text()).toContain('J-3')
    expect(wrapper.text()).toContain('J-20')
    expect(wrapper.find('.timeline-badge--retard').exists()).toBe(true)
    expect(wrapper.find('.timeline-badge--urgent').exists()).toBe(true)
    expect(wrapper.find('.timeline-badge--ok').exists()).toBe(true)

    // Taches urgentes : badge priorite seulement si >= 2
    expect(wrapper.text()).toContain('Urgente')
    expect(wrapper.text()).toContain('Haute')
    expect(wrapper.text()).not.toContain('Faible')
    expect(wrapper.text()).toContain('Declaration G50')

    // Activite recente : dates relatives
    expect(wrapper.text()).toContain("Aujourd'hui")
    expect(wrapper.text()).toContain('Hier')
    expect(wrapper.text()).toContain('Il y a 3 jours')
    expect(wrapper.text()).toContain('Bilan valide')
  })

  it('affiche les etats vides et le statut "À jour" sans retard', async () => {
    mockGetCollaborateur.mockResolvedValue({ data: collabStatsVide })
    const { wrapper } = await mountPage(DashboardPage, { role: 'collaborateur' })
    const vm = wrapper.vm as any

    expect(wrapper.text()).toContain('À jour')
    expect(wrapper.text()).toContain('Aucune tâche en retard')
    expect(wrapper.text()).toContain('Aucune tâche assignée.')
    expect(wrapper.text()).toContain('Aucune mission assignée.')
    expect(wrapper.text()).toContain('Aucune échéance imminente.')
    expect(wrapper.text()).toContain('Aucune tâche en cours.')
    expect(wrapper.text()).toContain('Aucune activité récente.')
    expect(wrapper.text()).toContain('Aucun blocage actif')
    // taux 0 < 70 -> chip warn
    expect(wrapper.find('.hero-chip--warn').exists()).toBe(true)

    // Donut vide + computeds admin sans stats (branches nulles)
    expect(vm.donutSegments).toEqual([])
    expect(vm.caMensuelAria).toBe('')
    expect(vm.missionsAria).toBe('')
    expect(vm.missionsData.datasets[0].data).toEqual([0, 0, 0, 0])
    expect(vm.caMensuelVide).toBe(true)
  })

  it('rend un donut a segment unique sans gap (100%)', async () => {
    mockGetCollaborateur.mockResolvedValue({
      data: {
        ...collabStatsVide,
        taches: { total: 5, a_faire: 0, en_cours: 5, terminees: 0, bloquees: 0, taux_completion: 0, en_retard: 0 },
      },
    })
    const { wrapper } = await mountPage(DashboardPage, { role: 'collaborateur' })
    const vm = wrapper.vm as any

    expect(vm.donutSegments).toHaveLength(1)
    expect(vm.donutSegments[0].pct).toBe(100)
    expect(vm.donutSegments[0].color).toBe('#3b82f6')
    // segment unique -> pas de gap, l'arc couvre toute la circonference
    const circumference = 2 * Math.PI * 38
    expect(vm.donutSegments[0].dasharray).toBe(`${circumference} ${circumference}`)
  })
})

describe('DashboardPage — secretaire', () => {
  it('delegue le rendu a SecretaireDashboardSection avec ses stats', async () => {
    const { wrapper } = await mountPage(DashboardPage, {
      role: 'secretaire',
      stubs: { SecretaireDashboardSection: true },
    })

    expect(mockGetSecretaire).toHaveBeenCalledOnce()
    expect(mockGetDashboard).not.toHaveBeenCalled()
    expect(mockGetCollaborateur).not.toHaveBeenCalled()
    expect(wrapper.find('secretaire-dashboard-section-stub').exists()).toBe(true)
  })
})
