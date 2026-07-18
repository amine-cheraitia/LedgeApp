import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Router } from 'vue-router'
import type { Mission, Tache } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const {
  mockGetOne, mockUpdate,
  mockTachesGetAll, mockTacheCreate, mockTacheUpdate, mockTacheDelete, mockConflits,
  mockUsersGetAll,
} = vi.hoisted(() => ({
  mockGetOne: vi.fn(),
  mockUpdate: vi.fn(),
  mockTachesGetAll: vi.fn(),
  mockTacheCreate: vi.fn(),
  mockTacheUpdate: vi.fn(),
  mockTacheDelete: vi.fn(),
  mockConflits: vi.fn(),
  mockUsersGetAll: vi.fn(),
}))

vi.mock('@/api/modules/missions', () => ({
  missionsApi: {
    getOne: mockGetOne,
    update: mockUpdate,
    rapportPdfUrl: (id: number) => `/api/v1/missions/${id}/rapport/pdf`,
    conventionPdfUrl: (id: number) => `/api/v1/missions/${id}/convention/pdf`,
    mandatPdfUrl: (id: number) => `/api/v1/missions/${id}/mandat/pdf`,
  },
}))

vi.mock('@/api/modules/taches', () => ({
  tachesApi: {
    getAll: mockTachesGetAll,
    create: mockTacheCreate,
    update: mockTacheUpdate,
    delete: mockTacheDelete,
    conflits: mockConflits,
  },
}))

vi.mock('@/api/modules/users', () => ({
  usersApi: {
    getAll: mockUsersGetAll,
    create: vi.fn(),
    update: vi.fn(),
    delete: vi.fn(),
    renvoyerInvitation: vi.fn(),
  },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

const confirmRequire = vi.fn()
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import MissionDetailPage from '@/pages/missions/MissionDetailPage.vue'
import { formatDA } from '@/utils/currency'
import { mountPage, findButton } from '../helpers/mount'
import type { MountPageOptions } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
function makeMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 5,
    entreprise_id: 1,
    exercice_id: 1,
    prestation_id: 1,
    reference: 'M2026-001',
    convention_numero: 'CV2026-001',
    mandat_numero: null,
    visible_portail: false,
    prix_ht: 315000,
    date_debut: '2026-01-15',
    date_fin: '2026-12-31',
    statut: 'en_cours',
    notes: 'Dossier prioritaire',
    entreprise: { id: 1, raison_sociale: 'SARL Numidia Conseil' } as any,
    prestation: {
      id: 1, code: 'ACMPT', designation: 'Assistance comptable',
      tarif_initial: 120000, duree_mois: 12, description: null, actif: true,
      created_at: '', updated_at: '',
    },
    collaborateurs: [{ id: 2, name: 'Karim Benali' } as any],
    factures: [
      { id: 1, numero: 'FF2026-001', montant_ttc: 112455, statut_paiement: 'solde' } as any,
      { id: 2, numero: 'FF2026-002', montant_ttc: 50000, statut_paiement: 'partiel' } as any,
      { id: 3, numero: 'FF2026-003', montant_ttc: 20000, statut_paiement: 'en_attente' } as any,
    ],
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

function makeTaches(): Tache[] {
  return [
    {
      id: 10, mission_id: 5, assigned_to: 2, titre: 'Collecte des pièces',
      description: 'Récupérer les justificatifs', statut: 'en_cours',
      date_debut: '2026-02-01', date_echeance: '2026-02-15', priorite: 3,
      assignee: { id: 2, name: 'Karim Benali' } as any,
      created_at: '', updated_at: '',
    },
    {
      id: 11, mission_id: 5, assigned_to: null, titre: 'Saisie comptable',
      description: null, statut: 'a_faire',
      date_debut: null, date_echeance: null, priorite: 1,
      created_at: '', updated_at: '',
    },
  ]
}

/** Le harnais n'enregistre pas de routes nommees : on ajoute celles que la page cible. */
function addNamedRoutes(router: Router) {
  router.addRoute({ path: '/liste-missions', name: 'missions', component: { template: '<div />' } })
  router.addRoute({ path: '/liste-factures', name: 'factures', component: { template: '<div />' } })
  router.addRoute({ path: '/missions/:id/taches/:tacheId', name: 'tache-detail', component: { template: '<div />' } })
}

async function mountDetail(options: MountPageOptions = {}) {
  const result = await mountPage(MissionDetailPage, {
    path: '/missions/5',
    routePattern: '/missions/:id',
    ...options,
  })
  addNamedRoutes(result.router)
  return result
}

let openSpy: ReturnType<typeof vi.spyOn>

beforeEach(() => {
  vi.clearAllMocks()
  mockGetOne.mockResolvedValue({ data: makeMission() })
  mockTachesGetAll.mockResolvedValue({ data: makeTaches() })
  mockUsersGetAll.mockResolvedValue({
    data: [{ id: 1, name: 'Admin Test' }, { id: 2, name: 'Karim Benali' }],
    meta: { total: 2 },
  })
  mockConflits.mockResolvedValue({ data: [] })
  openSpy = vi.spyOn(window, 'open').mockImplementation(() => null) as any
})

describe('MissionDetailPage — chargement', () => {
  it('charge la mission et ses tâches puis affiche la fiche complète (admin)', async () => {
    const { wrapper } = await mountDetail()

    expect(mockGetOne).toHaveBeenCalledWith(5)
    expect(mockTachesGetAll).toHaveBeenCalledWith(5)
    // Seuls admin/collaborateur sont assignables -> fetchUsers avec ces roles
    expect(mockUsersGetAll).toHaveBeenCalledWith(
      expect.objectContaining({ role: ['admin', 'collaborateur'], per_page: 100 }),
    )

    const text = wrapper.text()
    expect(text).toContain('M2026-001')
    expect(text).toContain('SARL Numidia Conseil')
    expect(text).toContain('Assistance comptable')
    expect(text).toContain('DA') // prix HT formate
    expect(text).toContain('Karim Benali')
    expect(text).toContain('Dossier prioritaire')
    // Tâches : titres, assignation (initiales KB) et priorités
    expect(text).toContain('Collecte des pièces')
    expect(text).toContain('Saisie comptable')
    expect(text).toContain('KB')
    expect(text).toContain('Non assignée')
    expect(text).toContain('Haute')
    expect(text).toContain('Faible')
    // RGAA : bouton retour avec aria-label
    expect(findButton(wrapper, 'Retour aux missions')).toBeTruthy()
  })

  it('affiche le spinner pendant le chargement puis la fiche une fois résolue', async () => {
    let resolveGetOne!: (v: unknown) => void
    mockGetOne.mockImplementation(() => new Promise(res => { resolveGetOne = res }))

    const { wrapper } = await mountPage(MissionDetailPage, {
      path: '/missions/5', routePattern: '/missions/:id', skipFlush: true,
    })
    await flushPromises()
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true)

    resolveGetOne({ data: makeMission() })
    await flushPromises()
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('M2026-001')
  })

  it('redirige vers la liste des missions avec un toast si la mission est introuvable', async () => {
    let rejectGetOne!: (e: unknown) => void
    mockGetOne.mockImplementation(() => new Promise((_, reject) => { rejectGetOne = reject }))

    const { router } = await mountPage(MissionDetailPage, {
      path: '/missions/999', routePattern: '/missions/:id', skipFlush: true,
    })
    addNamedRoutes(router)
    rejectGetOne(new Error('404'))
    await flushPromises()

    expect(mockGetOne).toHaveBeenCalledWith(999)
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Mission introuvable.' }),
    )
    expect(router.currentRoute.value.name).toBe('missions')
  })

  it("reste affichée si le chargement des tâches échoue (erreur silencieuse)", async () => {
    mockTachesGetAll.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountDetail()

    expect(wrapper.text()).toContain('M2026-001')
    expect(wrapper.text()).toContain('Tâches')
    // aucun toast d'erreur pour les tâches
    expect(toastAdd).not.toHaveBeenCalled()
  })
})

describe('MissionDetailPage — statut mission', () => {
  it('propose Suspendre/Terminer/Annuler pour une mission en cours et termine la mission', async () => {
    mockUpdate.mockResolvedValue({ data: makeMission({ statut: 'terminee' }) })
    const { wrapper } = await mountDetail()

    expect(findButton(wrapper, 'Suspendre')).toBeTruthy()
    expect(findButton(wrapper, 'Terminer')).toBeTruthy()
    expect(findButton(wrapper, 'Annuler')).toBeTruthy()
    expect(findButton(wrapper, 'Reprendre')).toBeUndefined()

    await findButton(wrapper, 'Terminer')!.trigger('click')
    await flushPromises()

    expect(mockUpdate).toHaveBeenCalledWith(5, { statut: 'terminee' })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Statut mis à jour.' }),
    )
    // La mission terminée n'expose plus d'actions de statut
    expect(findButton(wrapper, 'Suspendre')).toBeUndefined()
  })

  it("propose Reprendre pour une mission suspendue et affiche une erreur si l'API échoue", async () => {
    mockGetOne.mockResolvedValue({ data: makeMission({ statut: 'suspendue' }) })
    mockUpdate.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountDetail()

    expect(findButton(wrapper, 'Terminer')).toBeUndefined()
    await findButton(wrapper, 'Reprendre')!.trigger('click')
    await flushPromises()

    expect(mockUpdate).toHaveBeenCalledWith(5, { statut: 'en_cours' })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de mettre à jour le statut.' }),
    )
  })

  it('masque les actions de statut, documents, tranches et factures pour un collaborateur', async () => {
    const { wrapper } = await mountDetail({ role: 'collaborateur' })

    // pas de chargement des utilisateurs assignables
    expect(mockUsersGetAll).not.toHaveBeenCalled()

    const text = wrapper.text()
    expect(findButton(wrapper, 'Suspendre')).toBeUndefined()
    expect(findButton(wrapper, 'Annuler')).toBeUndefined()
    expect(text).not.toContain('Documents')
    expect(text).not.toContain('Tranches de facturation')
    expect(text).not.toContain('Factures liées')
    expect(findButton(wrapper, 'Ajouter une tâche')).toBeUndefined()
    expect(findButton(wrapper, 'Modifier la tâche Collecte des pièces')).toBeUndefined()
    expect(findButton(wrapper, 'Supprimer la tâche Collecte des pièces')).toBeUndefined()
    // il peut quand même consulter la tâche
    expect(findButton(wrapper, 'Voir la tâche Collecte des pièces')).toBeTruthy()
  })
})

describe('MissionDetailPage — documents et portail', () => {
  it('bascule la visibilité portail (visible puis masqué) et gère l\'échec', async () => {
    mockUpdate.mockResolvedValueOnce({ data: makeMission({ visible_portail: true }) })
    const { wrapper } = await mountDetail()

    // visible_portail=false -> bouton "Masqué portail" avec aria-label explicite
    const toggle = findButton(wrapper, 'Rendre les documents visibles dans le portail client')
    expect(toggle).toBeTruthy()
    expect(toggle!.text()).toContain('Masqué portail')

    await toggle!.trigger('click')
    await flushPromises()
    expect(mockUpdate).toHaveBeenCalledWith(5, { visible_portail: true })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Documents visible dans le portail.' }),
    )

    // repasse en masqué
    mockUpdate.mockResolvedValueOnce({ data: makeMission({ visible_portail: false }) })
    await (wrapper.vm as any).toggleVisiblePortail()
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ detail: 'Documents masqué dans le portail.' }),
    )

    // échec API
    mockUpdate.mockRejectedValueOnce(new Error('500'))
    await (wrapper.vm as any).toggleVisiblePortail()
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de modifier la visibilité.' }),
    )
  })

  it('ouvre les PDF rapport, convention et mandat dans un nouvel onglet', async () => {
    const { wrapper } = await mountDetail()

    // convention déjà générée -> "Imprimer" ; mandat non généré -> "Générer"
    expect(wrapper.text()).toContain('CV2026-001')
    expect(wrapper.text()).toContain('Non généré')

    await findButton(wrapper, 'Télécharger le rapport de fin de mission en PDF')!.trigger('click')
    expect(openSpy).toHaveBeenCalledWith('/api/v1/missions/5/rapport/pdf', '_blank')

    await findButton(wrapper, 'Télécharger la convention de prestation')!.trigger('click')
    await flushPromises()
    expect(openSpy).toHaveBeenCalledWith('/api/v1/missions/5/convention/pdf', '_blank')
    // recharge la mission pour récupérer le numéro généré
    expect(mockGetOne).toHaveBeenCalledTimes(2)

    await findButton(wrapper, "Télécharger le mandat d'acceptation")!.trigger('click')
    await flushPromises()
    expect(openSpy).toHaveBeenCalledWith('/api/v1/missions/5/mandat/pdf', '_blank')
    expect(mockGetOne).toHaveBeenCalledTimes(3)
  })

  it('affiche les documents sans bouton portail pour une secrétaire et les libellés de repli', async () => {
    mockGetOne.mockResolvedValue({
      data: makeMission({
        prestation: undefined,
        entreprise: undefined,
        collaborateurs: [],
        notes: null,
        factures: [],
        convention_numero: null,
        mandat_numero: null,
      }),
    })
    const { wrapper } = await mountDetail({ role: 'secretaire' })

    const text = wrapper.text()
    expect(text).toContain('Documents')
    // bouton de visibilité portail réservé à l'admin
    expect(findButton(wrapper, 'portail client')).toBeUndefined()
    // fallbacks
    expect(text).toContain('Mission sans prestation')
    expect(text).toContain('—')
    expect(text).toContain('Non générée')
    expect(text).toContain('Non généré')
    expect(text).not.toContain('Factures liées')
    expect(text).not.toContain('Collaborateurs :')
    expect(text).not.toContain('Notes :')
  })
})

describe('MissionDetailPage — tranches et factures liées', () => {
  it('calcule les tranches 30/30/40 avec solde exact et liste les factures liées', async () => {
    const { wrapper, router } = await mountDetail()
    const vm = wrapper.vm as any

    expect(vm.tranches).toEqual([
      { label: 'Tranche 1 (30%)', montant: 94500 },
      { label: 'Tranche 2 (30%)', montant: 94500 },
      { label: 'Tranche 3 — solde (40%)', montant: 126000 },
    ])
    const text = wrapper.text()
    expect(text).toContain('Tranches de facturation suggérées')
    expect(text).toContain('Tranche 3 — solde (40%)')

    // Factures liées avec leurs statuts de paiement
    expect(text).toContain('Factures liées')
    expect(text).toContain('FF2026-001')
    expect(text).toContain('FF2026-002')
    expect(text).toContain('solde')
    expect(text).toContain('partiel')
    expect(text).toContain('en_attente')

    // Bouton "Créer une facture" -> navigation vers la liste des factures
    await findButton(wrapper, 'Créer une facture')!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('factures')
  })

  it('couvre les utilitaires de statut, les formats et les retours anticipés sans mission', async () => {
    const { wrapper } = await mountDetail()
    const vm = wrapper.vm as any

    expect(vm.statutMissionSeverity('suspendue')).toBe('warn')
    expect(vm.statutMissionSeverity('annulee')).toBe('danger')
    expect(vm.statutMissionSeverity('inconnu')).toBe('secondary')
    expect(vm.statutTacheSeverity('bloquee')).toBe('danger')
    expect(vm.statutTacheSeverity('inconnu')).toBe('secondary')
    expect(vm.statutTacheLabel('bloquee')).toBe('Bloquée')
    expect(vm.statutTacheLabel('inconnu')).toBe('inconnu')
    expect(vm.formatDate(null)).toBe('—')
    // formatMontant local remplace par le formateur partage utils/currency
    expect(formatDA(1000)).toContain('DA')

    // Sans mission chargée : aucun appel API ni ouverture de PDF
    vm.mission = null
    expect(vm.tranches).toEqual([])
    await vm.changerStatutMission('terminee')
    await vm.toggleVisiblePortail()
    await vm.telechargerConvention()
    await vm.telechargerMandat()
    expect(mockUpdate).not.toHaveBeenCalled()
    expect(openSpy).not.toHaveBeenCalled()

    // Soumissions à vide : create sans titre, edit sans tâche courante
    await vm.onSubmitCreate()
    expect(mockTacheCreate).not.toHaveBeenCalled()
    await vm.onSubmitEdit()
    expect(mockTacheUpdate).not.toHaveBeenCalled()
  })
})

describe('MissionDetailPage — création de tâche', () => {
  it('ouvre le dialog, soumet la tâche avec dates ISO puis recharge la liste', async () => {
    mockTacheCreate.mockResolvedValue({ data: { id: 12 } })
    const { wrapper } = await mountDetail()

    await findButton(wrapper, 'Ajouter une tâche')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Nouvelle tâche')

    await wrapper.find('#c-titre').setValue('Déclaration G50')
    const vm = wrapper.vm as any
    vm.createForm.assigned_to = 2
    vm.createDateDebut = new Date(2026, 1, 1)
    vm.createDateEcheance = new Date(2026, 1, 15)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockTacheCreate).toHaveBeenCalledWith(5, expect.objectContaining({
      titre: 'Déclaration G50',
      assigned_to: 2,
      statut: 'a_faire',
      priorite: 1,
      date_debut: '2026-02-01',
      date_echeance: '2026-02-15',
    }))
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Tâche créée.' }),
    )
    // dialog fermé + rechargement des tâches
    expect(wrapper.find('#c-titre').exists()).toBe(false)
    expect(mockTachesGetAll).toHaveBeenCalledTimes(2)
  })

  it("laisse le dialog ouvert avec un toast d'erreur si la création échoue", async () => {
    mockTacheCreate.mockRejectedValue(new Error('422'))
    const { wrapper } = await mountDetail()

    await findButton(wrapper, 'Ajouter une tâche')!.trigger('click')
    await wrapper.find('#c-titre').setValue('Tâche en échec')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockTacheCreate).toHaveBeenCalled()
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de créer la tâche.' }),
    )
    expect(wrapper.find('#c-titre').exists()).toBe(true)
  })

  it("affiche l'avertissement de conflit d'affectation (création et édition)", async () => {
    mockConflits.mockResolvedValue({
      data: [
        {
          id: 99, titre: 'Autre tâche chevauchante',
          date_debut: '2026-02-01', date_echeance: '2026-02-20',
          mission: { id: 7, reference: 'M2026-007' },
        },
        {
          id: 100, titre: 'Tâche sans référence',
          date_debut: null, date_echeance: null,
          mission: { id: null, reference: null },
        },
      ],
    })
    const { wrapper } = await mountDetail()
    const vm = wrapper.vm as any

    // Création : collaborateur + date -> vérification débouncée (400 ms)
    await findButton(wrapper, 'Ajouter une tâche')!.trigger('click')
    vm.createForm.assigned_to = 2
    vm.createDateDebut = new Date(2026, 1, 5)
    await flushPromises()
    await new Promise(r => setTimeout(r, 450))
    await flushPromises()

    expect(mockConflits).toHaveBeenCalledWith(expect.objectContaining({
      collaborateur_id: 2,
      date_debut: '2026-02-05',
    }))
    const text = wrapper.text()
    expect(text).toContain('chevauchent cette période')
    expect(text).toContain('Autre tâche chevauchante')
    expect(text).toContain('M2026-007')
    expect(wrapper.find('[role="status"]').exists()).toBe(true)

    // Édition : même avertissement, en excluant la tâche éditée
    vm.createDialogVisible = false
    vm.openEditDialog(makeTaches()[1])
    await flushPromises()
    vm.editForm.assigned_to = 2
    vm.editDateDebut = new Date(2026, 1, 10)
    await flushPromises()
    await new Promise(r => setTimeout(r, 450))
    await flushPromises()

    expect(mockConflits).toHaveBeenCalledWith(expect.objectContaining({
      collaborateur_id: 2,
      date_debut: '2026-02-10',
      exclude_tache_id: 11,
    }))
    expect(wrapper.text()).toContain('chevauchent cette période')
  })
})
