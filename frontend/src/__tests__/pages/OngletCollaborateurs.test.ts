import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { CollaborateurKpiStats } from '@/api/modules/stats'
import type { User } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockUsersGetAll, mockGetCollabStats, mockUpsert, mockDeleteKpi } = vi.hoisted(() => ({
  mockUsersGetAll: vi.fn(),
  mockGetCollabStats: vi.fn(),
  mockUpsert: vi.fn(),
  mockDeleteKpi: vi.fn(),
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

vi.mock('@/api/modules/stats', () => ({
  statsApi: {
    getCollaborateurKpiStats: mockGetCollabStats,
    upsertKpiObjectif: mockUpsert,
    deleteKpiObjectif: mockDeleteKpi,
  },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

const confirmRequire = vi.fn()
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))

// ── Import du composant APRES les mocks ──────────────────────────────────────
import OngletCollaborateurs from '@/pages/statistiques/OngletCollaborateurs.vue'
import ObjectifsEditor from '@/pages/statistiques/ObjectifsEditor.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
function makeUser(overrides: Partial<User>): User {
  return {
    id: 0,
    name: '',
    email: '',
    entreprise_id: null,
    portail_actif: false,
    email_verified_at: null,
    roles: [],
    created_at: '2026-01-01T00:00:00.000000Z',
    updated_at: '2026-01-01T00:00:00.000000Z',
    ...overrides,
  }
}

const usersFixture: User[] = [
  makeUser({ id: 1, name: 'Admin One', email: 'admin@ledge.dz', roles: ['admin'] }),
  makeUser({ id: 2, name: 'Collab Two', email: 'collab@ledge.dz', roles: ['collaborateur'] }),
  makeUser({ id: 3, name: 'Secretaire Three', email: 'secr@ledge.dz', roles: ['secretaire'] }),
  makeUser({ id: 4, name: 'Client Four', email: 'client@ledge.dz', roles: ['client'], entreprise_id: 9 }),
]

function makeStats(overrides: Partial<CollaborateurKpiStats> = {}): CollaborateurKpiStats {
  return {
    user: { id: 2, name: 'Collab Two', email: 'collab@ledge.dz' },
    objectifs: {
      ca_ht: { id: 501, valeur: 1000000 },
      missions_cloturees: { id: 502, valeur: 4 },
    },
    realise: { ca_ht: 500000, missions_cloturees: 6, taches_terminees: 12, taches_en_retard: 2, delai_moyen_tache: 3.46 },
    realise_mensuel: { annee: 2026, data: [100000, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 200000] },
    taches_par_statut: { a_faire: 1, en_cours: 2, terminee: 12, bloquee: 1 },
    missions_par_prestation: [
      { prestation_id: 1, designation: 'Audit légal', total: 5 },
      { prestation_id: 2, designation: 'Assistance comptable', total: 2 },
    ],
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  mockUsersGetAll.mockResolvedValue({
    data: usersFixture,
    meta: { current_page: 1, last_page: 1, per_page: 100, total: usersFixture.length },
  })
  mockGetCollabStats.mockResolvedValue({ data: makeStats() })
})

describe('OngletCollaborateurs — dropdown collaborateur', () => {
  it('filtre le dropdown : exclut la secrétaire et le client, garde admin/collaborateur', async () => {
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    const labels = vm.collaborateurOptions.map((o: { label: string }) => o.label)
    expect(labels).toEqual(['Admin One', 'Collab Two'])
    expect(labels).not.toContain('Secretaire Three')
    expect(labels).not.toContain('Client Four')
  })

  it('invite à sélectionner un collaborateur avant tout fetch', async () => {
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })

    expect(wrapper.text()).toContain('Sélectionnez un collaborateur')
    expect(mockGetCollabStats).not.toHaveBeenCalled()
  })
})

describe('OngletCollaborateurs — chargement des stats', () => {
  it('sélectionne un collaborateur, fetch les stats et affiche les cartes KPI formatées', async () => {
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()

    expect(mockGetCollabStats).toHaveBeenCalledWith(2, 1)
    expect(wrapper.text()).toContain('Réalisé sur missions clôturées uniquement')
    expect(wrapper.text()).toMatch(/500\s*000,00\s*DA/) // titre exact du CA
    expect(wrapper.text()).toContain('12') // taches terminees
    expect(wrapper.text()).toContain('2') // taches en retard
    expect(wrapper.text()).toContain('3.5 j') // delai moyen arrondi
  })

  it('affiche le role="status" pendant le chargement', async () => {
    mockGetCollabStats.mockReturnValue(new Promise(() => {}))
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()

    expect(wrapper.find('[role="status"]').exists()).toBe(true)
  })

  it('affiche un état d\'erreur avec bouton Réessayer', async () => {
    mockGetCollabStats.mockRejectedValueOnce(new Error('500'))
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)

    mockGetCollabStats.mockResolvedValueOnce({ data: makeStats() })
    await findButton(wrapper, 'Réessayer')!.trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Réalisé sur missions clôturées uniquement')
  })

  it('refetch quand exerciceId change alors qu\'un collaborateur est sélectionné', async () => {
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()
    expect(mockGetCollabStats).toHaveBeenCalledTimes(1)

    await wrapper.setProps({ exerciceId: 2 })
    await flushPromises()

    expect(mockGetCollabStats).toHaveBeenCalledTimes(2)
    expect(mockGetCollabStats).toHaveBeenLastCalledWith(2, 2)
  })

  it('ne fetch pas quand exerciceId change sans collaborateur sélectionné', async () => {
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })

    await wrapper.setProps({ exerciceId: 2 })
    await flushPromises()

    expect(mockGetCollabStats).not.toHaveBeenCalled()
  })
})

describe('OngletCollaborateurs — graphiques (RGAA)', () => {
  it('affiche les tables sr-only des trois graphiques avec caption', async () => {
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()

    const captions = wrapper.findAll('caption').map((c) => c.text())
    expect(captions.some((c) => c.includes('mois'))).toBe(true)
    expect(captions.some((c) => c.includes('statut'))).toBe(true)
    expect(captions.some((c) => c.includes('prestation'))).toBe(true)

    // 3 graphiques rendus avec role="img" (fallthrough sur le stub Chart)
    expect(wrapper.findAll('[role="img"]').length).toBe(3)
  })

  it('missions par prestation : table sr-only avec designations et totaux', async () => {
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()

    const text = wrapper.text()
    expect(text).toContain('Missions par prestation')
    expect(text).toContain('Audit légal')
    expect(text).toContain('Assistance comptable')
    // Totaux mockés : 5 et 2
    expect(vm.missionsPrestation.map((p: { total: number }) => p.total)).toEqual([5, 2])
  })

  it('missions par prestation : état vide quand aucune mission', async () => {
    mockGetCollabStats.mockResolvedValue({ data: makeStats({ missions_par_prestation: [] }) })
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()

    expect(wrapper.text()).toContain('Aucune mission sur cet exercice.')
  })
})

describe('OngletCollaborateurs — jauges réalisé vs cible', () => {
  it('affiche le pourcentage NON plafonné (150%) avec une ProgressBar bornée', async () => {
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()

    // missions_cloturees : realise 6 / objectif 4 = 150 %
    expect(wrapper.text()).toContain('150%')
    expect(vm.pourcentage(6, 4)).toBe(150)
    expect(vm.progressSeverity(150)).toBe('success')
  })

  it('affiche "Pas d\'objectif fixé" pour un type sans objectif existant', async () => {
    mockGetCollabStats.mockResolvedValue({
      data: makeStats({ objectifs: { ca_ht: { id: 501, valeur: 1000000 } } }),
    })
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()

    expect(wrapper.text()).toContain("Pas d'objectif fixé")
  })
})

describe('OngletCollaborateurs — intégration ObjectifsEditor', () => {
  it('passe le bon user/objectifs/exerciceId à ObjectifsEditor et refetch quand il émet "saved"', async () => {
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()
    expect(mockGetCollabStats).toHaveBeenCalledTimes(1)

    const editor = wrapper.findComponent(ObjectifsEditor)
    expect(editor.exists()).toBe(true)
    expect(editor.props('user')).toEqual({ id: 2, name: 'Collab Two', email: 'collab@ledge.dz' })
    expect(editor.props('exerciceId')).toBe(1)

    // ObjectifsEditor emet 'saved' apres une sauvegarde -> l'onglet doit refetch.
    editor.vm.$emit('saved')
    await flushPromises()

    expect(mockGetCollabStats).toHaveBeenCalledTimes(2)
  })

  it("n'effectue aucun appel réseau et affiche un toast info si on sauvegarde sans modification", async () => {
    const { wrapper } = await mountPage(OngletCollaborateurs, { props: { exerciceId: 1 } })
    const vm = wrapper.vm as any

    vm.selectedUserId = 2
    await flushPromises()

    await findButton(wrapper, 'Sauvegarder les objectifs de Collab Two')!.trigger('click')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'info', detail: 'Aucune modification à enregistrer.' }),
    )
    expect(mockUpsert).not.toHaveBeenCalled()
    expect(mockDeleteKpi).not.toHaveBeenCalled()
  })
})
