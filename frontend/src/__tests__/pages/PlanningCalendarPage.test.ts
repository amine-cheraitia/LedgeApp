import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { CalendarMission, CalendarTache } from '@/api/modules/planning'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetCalendar, mockMissionUpdate, mockTacheUpdate, mockUsersGetAll, fakeApi } = vi.hoisted(() => ({
  mockGetCalendar: vi.fn(),
  mockMissionUpdate: vi.fn(),
  mockTacheUpdate: vi.fn(),
  mockUsersGetAll: vi.fn(),
  // Faux moteur FullCalendar accessible via calendarRef.getApi()
  fakeApi: {
    removeAllEvents: vi.fn(),
    addEventSource: vi.fn(),
    view: {
      activeStart: new Date('2026-07-01T00:00:00Z'),
      activeEnd: new Date('2026-08-01T00:00:00Z'),
    },
  },
}))

vi.mock('@/api/modules/planning', () => ({ planningApi: { getCalendar: mockGetCalendar } }))
vi.mock('@/api/modules/missions', () => ({ missionsApi: { update: mockMissionUpdate } }))
vi.mock('@/api/modules/taches', () => ({ tachesApi: { update: mockTacheUpdate } }))
vi.mock('@/api/modules/users', () => ({ usersApi: { getAll: mockUsersGetAll } }))

// Composant FullCalendar remplace par un stub anonyme exposant getApi()
// (anonyme pour ne pas etre re-stubbe par le harnais de montage).
vi.mock('@fullcalendar/vue3', () => ({
  default: {
    props: ['options'],
    methods: { getApi: () => fakeApi },
    render: () => null,
  },
}))

// ── Mock PrimeVue toast ──────────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import PlanningCalendarPage from '@/pages/planning/PlanningCalendarPage.vue'
import { mountPage, makeUser, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
function makeMission(over: Partial<CalendarMission> = {}): CalendarMission {
  return {
    id: 1, type: 'mission', reference: 'M2026-001', titre: 'Mission compta',
    date_debut: '2026-07-02', date_fin: '2026-07-10', statut: 'en_cours',
    prix_ht: 315000, prestation_id: 2, prestation_code: 'ACMPT',
    entreprise: 'SARL Test', prestation: 'Assistance comptable',
    ...over,
  }
}

function makeTache(over: Partial<CalendarTache> = {}): CalendarTache {
  return {
    id: 11, type: 'tache', titre: 'Saisie factures', date_debut: '2026-07-07',
    date_echeance: '2026-07-09', statut: 'en_cours', priorite: 3,
    mission_id: 1, mission_ref: 'M2026-001', entreprise: 'SARL Test',
    assigned_to: 5, assignee: 'Karim Benali',
    ...over,
  }
}

const missions = [
  makeMission(),
  makeMission({ id: 2, statut: 'suspendue', prestation_id: null, prestation_code: null, prestation: null, date_fin: null }),
  makeMission({ id: 3, statut: 'terminee' }),
]

beforeEach(() => {
  vi.clearAllMocks()
  mockGetCalendar.mockResolvedValue({ data: { missions, taches: [makeTache()] } })
  mockUsersGetAll.mockResolvedValue({
    data: [
      makeUser('collaborateur', { id: 5, name: 'Karim Benali' }),
      makeUser('admin', { id: 2, name: 'Amine Admin' }),
      makeUser('client', { id: 9, name: 'Client X' }),
    ],
  })
})

// Simule le cycle datesSet de FullCalendar (le stub ne l'emet jamais lui-meme)
async function triggerDatesSet(wrapper: any) {
  await (wrapper.vm as any).calendarOptions.datesSet({ startStr: '2026-07-01T00:00:00', endStr: '2026-08-01T00:00:00' })
  await flushPromises()
}

describe('PlanningCalendarPage — admin', () => {
  it('affiche le titre, les onglets et charge la liste des collaborateurs', async () => {
    const { wrapper } = await mountPage(PlanningCalendarPage)

    expect(wrapper.find('main[role="main"]').exists()).toBe(true)
    expect(wrapper.find('#planning-title').text()).toBe('Planning')
    expect(wrapper.text()).toContain('Missions')
    expect(wrapper.text()).toContain('Équipe')
    expect(mockUsersGetAll).toHaveBeenCalledWith({ per_page: 100 })

    const options = (wrapper.vm as any).calendarOptions
    expect(options.initialView).toBe('dayGridMonth')
    expect(options.editable).toBe(true) // admin : glisser-deposer autorise
  })

  it('datesSet charge le planning : stats, legende et synchronisation du calendrier', async () => {
    const { wrapper } = await mountPage(PlanningCalendarPage)
    await triggerDatesSet(wrapper)

    expect(mockGetCalendar).toHaveBeenCalledWith({ from: '2026-07-01', to: '2026-08-01' })
    // stats header (1 en_cours + 1 suspendue, la terminee ne compte pas dans les chips)
    expect(wrapper.text()).toContain('1 en cours')
    expect(wrapper.text()).toContain('1 suspendue')
    // legende des prestations vues + statuts
    expect(wrapper.text()).toContain('Assistance comptable')
    expect(wrapper.text()).toContain('Suspendue')
    expect(wrapper.text()).toContain('Annulée')
    // le watch pousse les events filtres dans FullCalendar
    expect(fakeApi.removeAllEvents).toHaveBeenCalled()
    const events = fakeApi.addEventSource.mock.calls.at(-1)![0]
    expect(events).toHaveLength(2) // terminee filtree par defaut
    expect(events.map((e: any) => e.id)).toEqual(['mission-1', 'mission-2'])
  })

  it('le clic sur une chip statut retire les missions correspondantes du calendrier', async () => {
    const { wrapper } = await mountPage(PlanningCalendarPage)
    await triggerDatesSet(wrapper)

    const chip = findButton(wrapper, 'Masquer les missions en cours')!
    expect(chip.attributes('aria-pressed')).toBe('true')
    await chip.trigger('click')
    await flushPromises()

    const events = fakeApi.addEventSource.mock.calls.at(-1)![0]
    expect(events.map((e: any) => e.id)).toEqual(['mission-2'])
    expect(findButton(wrapper, 'Afficher les missions en cours')).toBeTruthy()
  })

  it('le changement du filtre collaborateur recharge la periode visible', async () => {
    const { wrapper } = await mountPage(PlanningCalendarPage)
    mockGetCalendar.mockClear()

    ;(wrapper.vm as any).collaborateurFilter = 7
    await flushPromises()

    expect(mockGetCalendar).toHaveBeenCalledWith({
      from: '2026-07-01', to: '2026-08-01', collaborateur_id: 7,
    })
  })

  it('affiche un toast erreur quand le chargement du planning echoue', async () => {
    const { wrapper } = await mountPage(PlanningCalendarPage)
    mockGetCalendar.mockRejectedValue(new Error('500'))

    await triggerDatesSet(wrapper)

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger le planning.' }),
    )
  })
})

describe('PlanningCalendarPage — modal mission', () => {
  it('affiche le detail de la mission et enregistre un changement de statut', async () => {
    const { wrapper } = await mountPage(PlanningCalendarPage)
    await triggerDatesSet(wrapper)
    mockMissionUpdate.mockResolvedValue({ data: {} })

    ;(wrapper.vm as any).openDetail({ event: { extendedProps: makeMission() } })
    await flushPromises()

    expect((wrapper.vm as any).dialogVisible).toBe(true)
    expect(wrapper.text()).toContain('M2026-001')
    expect(wrapper.text()).toContain('SARL Test')
    expect(wrapper.text()).toContain('ACMPT')
    expect(wrapper.text()).toContain('DA') // prix HT en dinars
    expect(wrapper.text()).toContain('/2026') // periode formatee fr-FR

    // pas de changement -> pas de bouton Enregistrer
    expect(findButton(wrapper, 'Enregistrer le nouveau statut')).toBeUndefined()

    ;(wrapper.vm as any).pendingStatut = 'terminee'
    await flushPromises()
    const save = findButton(wrapper, 'Enregistrer le nouveau statut')!
    await save.trigger('click')
    await flushPromises()

    expect(mockMissionUpdate).toHaveBeenCalledWith(1, { statut: 'terminee' })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', summary: 'Statut mis à jour' }),
    )
    expect((wrapper.vm as any).dialogVisible).toBe(false)
  })

  it("garde le modal ouvert et affiche un toast erreur si l'API refuse", async () => {
    const { wrapper } = await mountPage(PlanningCalendarPage)
    mockMissionUpdate.mockRejectedValue(new Error('422'))

    ;(wrapper.vm as any).openDetail({ event: { extendedProps: makeMission() } })
    await flushPromises()
    ;(wrapper.vm as any).pendingStatut = 'annulee'
    await (wrapper.vm as any).enregistrerStatut()
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de modifier le statut.' }),
    )
    expect((wrapper.vm as any).dialogVisible).toBe(true)
  })

  it('« Voir la fiche » ferme le modal et navigue vers la mission', async () => {
    const { wrapper, router } = await mountPage(PlanningCalendarPage)

    ;(wrapper.vm as any).openDetail({ event: { extendedProps: makeMission() } })
    await flushPromises()
    await findButton(wrapper, 'Voir la fiche')!.trigger('click')
    await flushPromises()

    expect((wrapper.vm as any).dialogVisible).toBe(false)
    expect(router.currentRoute.value.fullPath).toBe('/missions/1')
  })
})

describe('PlanningCalendarPage — modal tâche', () => {
  it('affiche les informations de la tache et navigue vers sa mission', async () => {
    const { wrapper, router } = await mountPage(PlanningCalendarPage)

    ;(wrapper.vm as any).openDetail({ event: { extendedProps: makeTache() } })
    await flushPromises()

    expect(wrapper.text()).toContain('Saisie factures')
    expect(wrapper.text()).toContain('M2026-001')
    expect(wrapper.text()).toContain('En cours')
    expect(wrapper.text()).toContain('Haute') // priorite 3
    expect(wrapper.text()).toContain('Karim Benali')

    await findButton(wrapper, 'Voir la mission')!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.fullPath).toBe('/missions/1')
  })

  it('affiche des tirets pour les champs absents', async () => {
    const { wrapper } = await mountPage(PlanningCalendarPage)

    ;(wrapper.vm as any).openDetail({
      event: {
        extendedProps: makeTache({ mission_ref: null, assignee: null, date_debut: null, date_echeance: null }),
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Tâche') // header de repli
    expect(wrapper.text()).toContain('—')
  })
})

describe('PlanningCalendarPage — onglet Équipe (admin)', () => {
  it('initialise la semaine au premier clic puis affiche la grille de disponibilite', async () => {
    const { wrapper } = await mountPage(PlanningCalendarPage)

    await findButton(wrapper, 'Équipe')!.trigger('click')
    await flushPromises()

    // 1re activation -> initWeek + chargement automatique
    expect((wrapper.vm as any).teamWeekStart).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(mockGetCalendar).toHaveBeenCalled()

    // Semaine deterministe pour verifier la grille (tache du 07 au 09/07)
    ;(wrapper.vm as any).teamWeekStart = '2026-07-06'
    await flushPromises()

    expect(wrapper.text()).toContain('Semaine du')
    expect(wrapper.find('[role="table"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Collaborateur')
    expect(wrapper.text()).toContain('Lun')
    expect(wrapper.text()).toContain('06/07')
    expect(wrapper.text()).toContain('Karim Benali')
    expect(wrapper.text()).toContain('Amine Admin')
    expect(wrapper.text()).toContain('Saisie factures') // chip de la tache assignee
    expect(wrapper.text()).toContain('Dispo') // cellules libres

    // Le clic sur une chip ouvre le meme modal que le calendrier
    await findButton(wrapper, 'Voir la tâche Saisie factures')!.trigger('click')
    await flushPromises()
    expect((wrapper.vm as any).dialogVisible).toBe(true)
    expect(wrapper.text()).toContain('Échéance')
  })

  it('navigation semaine precedente/suivante + rechargement a la reactivation', async () => {
    const { wrapper } = await mountPage(PlanningCalendarPage)
    await findButton(wrapper, 'Équipe')!.trigger('click')
    await flushPromises()

    ;(wrapper.vm as any).teamWeekStart = '2026-07-06'
    await flushPromises()

    await findButton(wrapper, 'Semaine précédente')!.trigger('click')
    await flushPromises()
    expect((wrapper.vm as any).teamWeekStart).toBe('2026-06-29')

    await findButton(wrapper, 'Semaine suivante')!.trigger('click')
    await flushPromises()
    expect((wrapper.vm as any).teamWeekStart).toBe('2026-07-06')

    // 2e activation : la semaine existe deja -> simple rechargement
    mockGetCalendar.mockClear()
    await findButton(wrapper, 'Équipe')!.trigger('click')
    await flushPromises()
    expect(mockGetCalendar).toHaveBeenCalledWith({ from: '2026-07-06', to: '2026-07-12' })
  })

  it('affiche un message quand aucun collaborateur n\'est trouve', async () => {
    mockUsersGetAll.mockResolvedValue({ data: [makeUser('client', { id: 9 })] })
    const { wrapper } = await mountPage(PlanningCalendarPage)

    expect(wrapper.text()).toContain('Aucun collaborateur trouvé.')
  })
})
