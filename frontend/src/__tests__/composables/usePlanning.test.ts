import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { CalendarMission, CalendarTache } from '@/api/modules/planning'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetCalendar, mockMissionUpdate, mockTacheUpdate, mockUsersGetAll } = vi.hoisted(() => ({
  mockGetCalendar: vi.fn(),
  mockMissionUpdate: vi.fn(),
  mockTacheUpdate: vi.fn(),
  mockUsersGetAll: vi.fn(),
}))

vi.mock('@/api/modules/planning', () => ({ planningApi: { getCalendar: mockGetCalendar } }))
vi.mock('@/api/modules/missions', () => ({ missionsApi: { update: mockMissionUpdate } }))
vi.mock('@/api/modules/taches', () => ({ tachesApi: { update: mockTacheUpdate } }))
vi.mock('@/api/modules/users', () => ({ usersApi: { getAll: mockUsersGetAll } }))

// ── Mock PrimeVue toast ──────────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Imports APRES les mocks ──────────────────────────────────────────────────
import { usePlanning, prestationColor } from '@/composables/usePlanning'
import { useAuthStore } from '@/stores/auth'
import { makeUser } from '../helpers/mount'

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
    id: 11, type: 'tache', titre: 'Saisie factures', date_debut: '2026-07-06',
    date_echeance: '2026-07-08', statut: 'en_cours', priorite: 3,
    mission_id: 1, mission_ref: 'M2026-001', entreprise: 'SARL Test',
    assigned_to: 5, assignee: 'Karim Benali',
    ...over,
  }
}

function setup(role = 'admin') {
  const auth = useAuthStore()
  auth.user = makeUser(role)
  return usePlanning()
}

beforeEach(() => {
  vi.clearAllMocks()
  setActivePinia(createPinia())
  mockGetCalendar.mockResolvedValue({ data: { missions: [], taches: [] } })
})

// ── Couleurs & utilitaires ───────────────────────────────────────────────────
describe('usePlanning — couleurs et utilitaires', () => {
  it('prestationColor : gris pour null, palette cyclique sinon', () => {
    expect(prestationColor(null)).toBe('#94A3B8')
    expect(prestationColor(2)).toBe('#14B8A6')
    // 13 % 10 = 3 -> orange
    expect(prestationColor(13)).toBe('#F97316')
  })

  it('chargeColor / chargeLabel : disponible, modere, charge', () => {
    const p = setup()
    expect(p.chargeColor(0)).toBe('var(--p-green-500)')
    expect(p.chargeColor(2)).toBe('var(--p-orange-400)')
    expect(p.chargeColor(3)).toBe('var(--p-red-500)')
    expect(p.chargeLabel(0)).toBe('Disponible')
    expect(p.chargeLabel(2)).toBe('Modéré')
    expect(p.chargeLabel(5)).toBe('Chargé')
  })
})

// ── fetchCollaborateurs ──────────────────────────────────────────────────────
describe('usePlanning — fetchCollaborateurs', () => {
  it('ne conserve que les admins et collaborateurs', async () => {
    mockUsersGetAll.mockResolvedValue({
      data: [
        makeUser('admin', { id: 2, name: 'Amine' }),
        makeUser('collaborateur', { id: 5, name: 'Karim' }),
        makeUser('client', { id: 9, name: 'Client X' }),
        makeUser('secretaire', { id: 12, name: 'Sonia' }),
      ],
    })
    const p = setup()

    await p.fetchCollaborateurs()

    expect(mockUsersGetAll).toHaveBeenCalledWith({ per_page: 100 })
    expect(p.collaborateurs.value.map(u => u.id)).toEqual([2, 5])
    expect(p.loadingCollab.value).toBe(false)
  })

  it("reste silencieux en cas d'erreur (filtre optionnel)", async () => {
    mockUsersGetAll.mockRejectedValue(new Error('500'))
    const p = setup()

    await p.fetchCollaborateurs()

    expect(p.collaborateurs.value).toEqual([])
    expect(p.loadingCollab.value).toBe(false)
    expect(toastAdd).not.toHaveBeenCalled()
  })
})

// ── loadEvents + filteredEvents (admin) ──────────────────────────────────────
describe('usePlanning — loadEvents et filteredEvents (admin)', () => {
  it('charge le calendrier et mappe les missions actives (couleurs, dates, fin exclusive)', async () => {
    const p = setup()
    mockGetCalendar.mockResolvedValue({
      data: {
        missions: [
          makeMission(),
          makeMission({ id: 2, statut: 'suspendue', prestation_id: null, prestation_code: null, prestation: null, date_fin: null }),
          makeMission({ id: 3, statut: 'terminee' }),
        ],
        taches: [],
      },
    })

    await p.loadEvents('2026-07-01', '2026-07-31')

    expect(mockGetCalendar).toHaveBeenCalledWith({ from: '2026-07-01', to: '2026-07-31' })
    const evts = p.filteredEvents.value
    // terminee exclue par defaut (statuts actifs : en_cours + suspendue)
    expect(evts).toHaveLength(2)

    const e1 = evts.find(e => e.id === 'mission-1')!
    expect(e1.title).toBe('Mission compta')
    expect(e1.start).toBe('2026-07-02')
    expect(e1.end).toBe('2026-07-11') // date_fin + 1 jour (fin exclusive FullCalendar)
    expect(e1.backgroundColor).toBe('#14B8A6CC') // palette[2] + opacite
    expect(e1.borderColor).toBe('#3B82F6') // bordure statut en_cours
    expect(e1.allDay).toBe(true)
    expect(e1.classNames).toEqual(['fc-mission-statut-en_cours'])

    const e2 = evts.find(e => e.id === 'mission-2')!
    expect(e2.backgroundColor).toBe('#94A3B8CC') // prestation null -> gris
    expect(e2.borderColor).toBe('#F59E0B') // suspendue
    expect(e2.end).toBeUndefined() // pas de date_fin
  })

  it('transmet collaborateur_id quand le filtre est actif', async () => {
    const p = setup()
    p.collaborateurFilter.value = 9

    await p.loadEvents('2026-07-01', '2026-07-31')

    expect(mockGetCalendar).toHaveBeenCalledWith({ from: '2026-07-01', to: '2026-07-31', collaborateur_id: 9 })
  })

  it('toggleStatut ajoute puis retire un statut du filtre', async () => {
    const p = setup()
    mockGetCalendar.mockResolvedValue({
      data: {
        missions: [
          makeMission(),
          makeMission({ id: 3, statut: 'terminee' }),
        ],
        taches: [],
      },
    })
    await p.loadEvents('2026-07-01', '2026-07-31')
    expect(p.filteredEvents.value).toHaveLength(1)

    p.toggleStatut('terminee')
    expect(p.activeStatuts.value.has('terminee')).toBe(true)
    expect(p.filteredEvents.value).toHaveLength(2)

    p.toggleStatut('en_cours')
    expect(p.activeStatuts.value.has('en_cours')).toBe(false)
    expect(p.filteredEvents.value.map(e => e.id)).toEqual(['mission-3'])
  })

  it('missionCountByStatut compte les missions par statut', async () => {
    const p = setup()
    mockGetCalendar.mockResolvedValue({
      data: {
        missions: [
          makeMission(),
          makeMission({ id: 4 }),
          makeMission({ id: 2, statut: 'suspendue' }),
        ],
        taches: [],
      },
    })
    await p.loadEvents('2026-07-01', '2026-07-31')

    expect(p.missionCountByStatut.value).toEqual({ en_cours: 2, suspendue: 1 })
  })

  it('prestationsVues dedoublonne et applique les replis code/designation', async () => {
    const p = setup()
    mockGetCalendar.mockResolvedValue({
      data: {
        missions: [
          makeMission(),
          makeMission({ id: 4 }), // meme prestation -> dedoublonnee
          makeMission({ id: 5, prestation_id: 3, prestation_code: 'AUDIT', prestation: null }),
          makeMission({ id: 6, prestation_id: null }), // ignoree
        ],
        taches: [],
      },
    })
    await p.loadEvents('2026-07-01', '2026-07-31')

    expect(p.prestationsVues.value).toEqual([
      { id: 2, code: 'ACMPT', designation: 'Assistance comptable' },
      { id: 3, code: 'AUDIT', designation: 'AUDIT' },
    ])
  })

  it('affiche un toast erreur si le chargement echoue', async () => {
    const p = setup()
    mockGetCalendar.mockRejectedValue(new Error('500'))

    await p.loadEvents('2026-07-01', '2026-07-31')

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger le planning.' }),
    )
    expect(p.loadingEvents.value).toBe(false)
  })
})

// ── filteredEvents (collaborateur) ───────────────────────────────────────────
describe('usePlanning — filteredEvents (collaborateur)', () => {
  it('mappe SES taches colorees par priorite (dates avec repli)', async () => {
    const p = setup('collaborateur')
    mockGetCalendar.mockResolvedValue({
      data: {
        missions: [],
        taches: [
          makeTache(), // priorite 3 (haute)
          makeTache({ id: 12, date_debut: null, date_echeance: '2026-07-15', priorite: 4 }),
          makeTache({ id: 13, date_debut: null, date_echeance: null, priorite: 9 }),
        ],
      },
    })

    await p.loadEvents('2026-07-01', '2026-07-31')
    const evts = p.filteredEvents.value
    expect(evts).toHaveLength(3)

    const e1 = evts.find(e => e.id === 'tache-11')!
    expect(e1.start).toBe('2026-07-06')
    expect(e1.end).toBe('2026-07-09') // echeance + 1 jour
    expect(e1.backgroundColor).toBe('#F59E0BCC') // priorite 3
    expect(e1.classNames).toEqual(['fc-tache-prio-3'])

    // sans date_debut -> demarre a l'echeance
    const e2 = evts.find(e => e.id === 'tache-12')!
    expect(e2.start).toBe('2026-07-15')
    expect(e2.end).toBe('2026-07-16')
    expect(e2.borderColor).toBe('#EF4444') // priorite 4 (urgente)

    // aucune date -> evenement sans start/end
    const e3 = evts.find(e => e.id === 'tache-13')!
    expect(e3.start).toBeUndefined()
    expect(e3.end).toBeUndefined()
  })
})

// ── changerStatutMission ─────────────────────────────────────────────────────
describe('usePlanning — changerStatutMission', () => {
  it('appelle l\'API et met a jour le statut localement', async () => {
    const p = setup()
    mockGetCalendar.mockResolvedValue({ data: { missions: [makeMission()], taches: [] } })
    await p.loadEvents('2026-07-01', '2026-07-31')
    mockMissionUpdate.mockResolvedValue({ data: {} })

    await p.changerStatutMission(1, 'suspendue')

    expect(mockMissionUpdate).toHaveBeenCalledWith(1, { statut: 'suspendue' })
    expect(p.missionCountByStatut.value).toEqual({ suspendue: 1 })
  })

  it('ne plante pas si la mission est absente du cache local', async () => {
    const p = setup()
    mockMissionUpdate.mockResolvedValue({ data: {} })

    await expect(p.changerStatutMission(999, 'terminee')).resolves.toBeUndefined()
    expect(mockMissionUpdate).toHaveBeenCalledWith(999, { statut: 'terminee' })
  })
})

// ── onEventDrop ──────────────────────────────────────────────────────────────
describe('usePlanning — onEventDrop', () => {
  function dropInfo(props: CalendarMission | CalendarTache, days: number) {
    const revert = vi.fn()
    return { info: { event: { extendedProps: props }, delta: { days }, revert } as any, revert }
  }

  it('decale les deux dates d\'une mission du delta et affiche un toast succes', async () => {
    const p = setup()
    mockMissionUpdate.mockResolvedValue({ data: {} })
    const { info, revert } = dropInfo(makeMission(), 3)

    await p.onEventDrop(info)

    expect(mockMissionUpdate).toHaveBeenCalledWith(1, { date_debut: '2026-07-05', date_fin: '2026-07-13' })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', summary: 'Mission déplacée', detail: 'M2026-001' }),
    )
    expect(revert).not.toHaveBeenCalled()
  })

  it('omet date_fin quand la mission n\'en a pas', async () => {
    const p = setup()
    mockMissionUpdate.mockResolvedValue({ data: {} })
    const { info } = dropInfo(makeMission({ date_fin: null }), 2)

    await p.onEventDrop(info)

    expect(mockMissionUpdate).toHaveBeenCalledWith(1, { date_debut: '2026-07-04' })
  })

  it('decale une tache (debut + echeance) via l\'API taches', async () => {
    const p = setup()
    mockTacheUpdate.mockResolvedValue({ data: {} })
    const { info } = dropInfo(makeTache(), -1)

    await p.onEventDrop(info)

    expect(mockTacheUpdate).toHaveBeenCalledWith(1, 11, { date_debut: '2026-07-05', date_echeance: '2026-07-07' })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', summary: 'Tâche déplacée', detail: 'Saisie factures' }),
    )
  })

  it('revert + message backend en cas d\'echec', async () => {
    const p = setup()
    mockMissionUpdate.mockRejectedValue({ response: { data: { message: 'Chevauchement de mission.' } } })
    const { info, revert } = dropInfo(makeMission(), 1)

    await p.onEventDrop(info)

    expect(revert).toHaveBeenCalledOnce()
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Chevauchement de mission.' }),
    )
  })

  it('remonte la premiere erreur de validation (422 sans message global)', async () => {
    const p = setup()
    mockTacheUpdate.mockRejectedValue({
      response: { data: { errors: { date_debut: ['La date de début est invalide.'] } } },
    })
    const { info, revert } = dropInfo(makeTache(), 1)

    await p.onEventDrop(info)

    expect(revert).toHaveBeenCalledOnce()
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'La date de début est invalide.' }),
    )
  })
})

// ── onEventResize ────────────────────────────────────────────────────────────
describe('usePlanning — onEventResize', () => {
  it('mission : nouvelle date_fin = fin exclusive - 1 jour', async () => {
    const p = setup()
    mockMissionUpdate.mockResolvedValue({ data: {} })
    const revert = vi.fn()

    await p.onEventResize({
      event: { extendedProps: makeMission(), endStr: '2026-07-16', startStr: '2026-07-02' },
      revert,
    } as any)

    expect(mockMissionUpdate).toHaveBeenCalledWith(1, { date_fin: '2026-07-15' })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', summary: 'Mission redimensionnée' }),
    )
    expect(revert).not.toHaveBeenCalled()
  })

  it('tache sans endStr : l\'echeance retombe sur startStr', async () => {
    const p = setup()
    mockTacheUpdate.mockResolvedValue({ data: {} })

    await p.onEventResize({
      event: { extendedProps: makeTache(), endStr: '', startStr: '2026-07-06T00:00:00+01:00' },
      revert: vi.fn(),
    } as any)

    expect(mockTacheUpdate).toHaveBeenCalledWith(1, 11, { date_echeance: '2026-07-06' })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', summary: 'Tâche redimensionnée' }),
    )
  })

  it('revert + message de repli en cas d\'echec sans reponse backend', async () => {
    const p = setup()
    mockMissionUpdate.mockRejectedValue(new Error('Network Error'))
    const revert = vi.fn()

    await p.onEventResize({
      event: { extendedProps: makeMission(), endStr: '2026-07-16', startStr: '2026-07-02' },
      revert,
    } as any)

    expect(revert).toHaveBeenCalledOnce()
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: "Impossible de redimensionner l'événement." }),
    )
  })
})

// ── Vue Équipe ───────────────────────────────────────────────────────────────
describe('usePlanning — vue équipe', () => {
  it('initWeek initialise la semaine (7 jours) et charge automatiquement', async () => {
    const p = setup()
    // avant init : grille et jours vides
    expect(p.teamWeekDays.value).toEqual([])
    expect(p.teamGridData.value).toEqual({})

    p.initWeek()
    expect(p.teamWeekStart.value).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(p.teamWeekDays.value).toHaveLength(7)
    expect(p.teamWeekDays.value[0]).toBe(p.teamWeekStart.value)

    await flushPromises()
    expect(mockGetCalendar).toHaveBeenCalledWith({
      from: p.teamWeekStart.value,
      to: p.teamWeekDays.value[6],
    })
  })

  it('prevWeek / nextWeek decalent la semaine de 7 jours et rechargent', async () => {
    const p = setup()
    p.teamWeekStart.value = '2026-07-06'
    await flushPromises()
    mockGetCalendar.mockClear()

    p.prevWeek()
    expect(p.teamWeekStart.value).toBe('2026-06-29')
    await flushPromises()
    expect(mockGetCalendar).toHaveBeenCalledWith({ from: '2026-06-29', to: '2026-07-05' })

    p.nextWeek()
    expect(p.teamWeekStart.value).toBe('2026-07-06')
  })

  it('teamGridData repartit les taches par collaborateur et par jour (intersection semaine)', async () => {
    const p = setup()
    mockGetCalendar.mockResolvedValue({
      data: {
        missions: [],
        taches: [
          makeTache({ id: 21, assigned_to: 5, date_debut: '2026-07-07', date_echeance: '2026-07-09' }),
          makeTache({ id: 22, assigned_to: null }), // non assignee -> ignoree
          makeTache({ id: 23, assigned_to: 5, date_debut: null, date_echeance: null }), // sans date -> ignoree
          makeTache({ id: 24, assigned_to: 6, date_debut: '2026-06-20', date_echeance: '2026-07-06' }), // tronquee au debut de semaine
          makeTache({ id: 25, assigned_to: 6, date_debut: '2026-07-20', date_echeance: '2026-07-21' }), // hors semaine
          makeTache({ id: 26, assigned_to: 7, date_debut: null, date_echeance: '2026-07-10' }), // 1 seul jour via echeance
        ],
      },
    })

    p.teamWeekStart.value = '2026-07-06' // lundi
    await flushPromises()

    const grid = p.teamGridData.value
    expect(Object.keys(grid[5])).toEqual(['2026-07-07', '2026-07-08', '2026-07-09'])
    expect(grid[5]['2026-07-08'][0].id).toBe(21)
    expect(Object.keys(grid[6])).toEqual(['2026-07-06'])
    expect(grid[6]['2026-07-06'][0].id).toBe(24)
    expect(Object.keys(grid[7])).toEqual(['2026-07-10'])
    expect(grid[7]['2026-07-10'][0].id).toBe(26)

    const allIds = Object.values(grid).flatMap(byDay => Object.values(byDay).flat()).map(t => t.id)
    expect(allIds).not.toContain(22)
    expect(allIds).not.toContain(23)
    expect(allIds).not.toContain(25)
  })

  it('affiche un toast erreur si le chargement de la vue equipe echoue', async () => {
    const p = setup()
    mockGetCalendar.mockRejectedValue(new Error('500'))

    p.teamWeekStart.value = '2026-07-06'
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger la vue équipe.' }),
    )
    expect(p.loadingEquipe.value).toBe(false)
  })

  it('loadEquipeWeek ne fait rien tant que la semaine n\'est pas initialisee', async () => {
    const p = setup()

    await p.loadEquipeWeek()

    expect(mockGetCalendar).not.toHaveBeenCalled()
  })
})
