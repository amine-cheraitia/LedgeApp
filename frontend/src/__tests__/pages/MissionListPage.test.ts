import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Mission, Exercice } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const {
  missionsGetAll, missionsCreate, missionsUpdate, missionsDelete,
  entreprisesGetAll, prestationsGetAll, usersGetAll,
  exercicesGetAll, exercicesGetCurrent,
} = vi.hoisted(() => ({
  missionsGetAll: vi.fn(),
  missionsCreate: vi.fn(),
  missionsUpdate: vi.fn(),
  missionsDelete: vi.fn(),
  entreprisesGetAll: vi.fn(),
  prestationsGetAll: vi.fn(),
  usersGetAll: vi.fn(),
  exercicesGetAll: vi.fn(),
  exercicesGetCurrent: vi.fn(),
}))

vi.mock('@/api/modules/missions', () => ({
  missionsApi: {
    getAll: missionsGetAll,
    create: missionsCreate,
    update: missionsUpdate,
    delete: missionsDelete,
  },
}))
vi.mock('@/api/modules/entreprises', () => ({ entreprisesApi: { getAll: entreprisesGetAll } }))
vi.mock('@/api/modules/prestations', () => ({ prestationsApi: { getAll: prestationsGetAll } }))
vi.mock('@/api/modules/users', () => ({ usersApi: { getAll: usersGetAll } }))
vi.mock('@/api/modules/exercices', () => ({
  exercicesApi: { getAll: exercicesGetAll, getCurrent: exercicesGetCurrent },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

const confirmRequire = vi.fn()
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import MissionListPage from '@/pages/missions/MissionListPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const exercice2026: Exercice = {
  id: 10, annee: 2026, date_ouverture: '2026-01-01', date_cloture: '2026-12-31',
  statut: 'ouvert', created_at: '', updated_at: '',
}
const exercice2025: Exercice = {
  id: 9, annee: 2025, date_ouverture: '2025-01-01', date_cloture: '2025-12-31',
  statut: 'cloture', created_at: '', updated_at: '',
}

const entrepriseAtlas = {
  id: 1, raison_sociale: 'SARL Atlas', regime_fiscal: 'reel', categorie: 'pme', statut: 'client',
} as any

const prestationCompta = { id: 3, designation: 'Assistance comptable', code: 'ACMPT' } as any

const missions: Mission[] = [
  {
    id: 1, entreprise_id: 1, exercice_id: 10, prestation_id: 3,
    reference: 'M2026-001', convention_numero: null, mandat_numero: null,
    visible_portail: true, prix_ht: 315000,
    date_debut: '2026-01-15', date_fin: '2026-06-30',
    statut: 'en_cours', notes: 'RAS',
    entreprise: entrepriseAtlas, prestation: prestationCompta,
    collaborateurs: [{ id: 7, name: 'Karim' } as any],
    created_at: '', updated_at: '',
  },
  {
    id: 2, entreprise_id: 2, exercice_id: 10, prestation_id: 3,
    reference: 'M2026-002', convention_numero: null, mandat_numero: null,
    visible_portail: false, prix_ht: 120000,
    date_debut: '2026-02-01', date_fin: null,
    statut: 'terminee', notes: null,
    created_at: '', updated_at: '',
  },
  {
    id: 3, entreprise_id: 1, exercice_id: 10, prestation_id: 3,
    reference: 'M2026-003', convention_numero: null, mandat_numero: null,
    visible_portail: false, prix_ht: 90000,
    date_debut: '2026-03-01', date_fin: '2026-09-30',
    statut: 'suspendue', notes: null,
    entreprise: entrepriseAtlas, prestation: prestationCompta,
    created_at: '', updated_at: '',
  },
]

beforeEach(() => {
  vi.clearAllMocks()
  missionsGetAll.mockResolvedValue({ data: missions, meta: { total: 3 } })
  entreprisesGetAll.mockResolvedValue({ data: [entrepriseAtlas], meta: { total: 1 } })
  prestationsGetAll.mockResolvedValue({ data: [prestationCompta] })
  usersGetAll.mockResolvedValue({ data: [{ id: 7, name: 'Karim' }], meta: { total: 1 } })
  exercicesGetAll.mockResolvedValue({ data: [exercice2026, exercice2025] })
  exercicesGetCurrent.mockResolvedValue({ data: exercice2026 })
})

describe('MissionListPage — liste (admin)', () => {
  it('charge les referentiels, filtre par exercice courant et affiche les missions', async () => {
    const { wrapper } = await mountPage(MissionListPage)

    // Referentiels charges pour l'admin
    expect(exercicesGetCurrent).toHaveBeenCalledOnce()
    expect(exercicesGetAll).toHaveBeenCalledOnce()
    expect(entreprisesGetAll).toHaveBeenCalledOnce()
    expect(prestationsGetAll).toHaveBeenCalledOnce()
    expect(usersGetAll).toHaveBeenCalledOnce()

    // Le filtre exercice courant est applique au fetch des missions
    expect(missionsGetAll).toHaveBeenLastCalledWith(expect.objectContaining({ exercice_id: 10 }))

    // Contenu de la table
    expect(wrapper.text()).toContain('M2026-001')
    expect(wrapper.text()).toContain('SARL Atlas')
    expect(wrapper.text()).toContain('Assistance comptable')
    expect(wrapper.text()).toContain('En cours')
    expect(wrapper.text()).toContain('Terminée')
    expect(wrapper.text()).toContain('Suspendue')
    // Entreprise / prestation / date fin manquantes -> tiret
    expect(wrapper.text()).toContain('—')
  })

  it('affiche un toast erreur si le chargement des missions echoue', async () => {
    missionsGetAll.mockRejectedValue(new Error('500'))
    await mountPage(MissionListPage)

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les missions.' }),
    )
  })

  it('RGAA : la recherche et le filtre exercice ont un label sr-only', async () => {
    const { wrapper } = await mountPage(MissionListPage)

    const searchLabel = wrapper.find('label[for="m-search"]')
    expect(searchLabel.exists()).toBe(true)
    expect(searchLabel.classes()).toContain('sr-only')
    expect(wrapper.find('label[for="m-exercice"]').exists()).toBe(true)
  })
})

describe('MissionListPage — role collaborateur', () => {
  it('ne charge pas les referentiels et masque creation/edition/suppression', async () => {
    const { wrapper } = await mountPage(MissionListPage, { role: 'collaborateur' })

    expect(exercicesGetCurrent).not.toHaveBeenCalled()
    expect(exercicesGetAll).not.toHaveBeenCalled()
    expect(entreprisesGetAll).not.toHaveBeenCalled()
    expect(prestationsGetAll).not.toHaveBeenCalled()
    expect(usersGetAll).not.toHaveBeenCalled()
    expect(missionsGetAll).toHaveBeenCalledOnce()

    expect(findButton(wrapper, 'Nouvelle mission')).toBeUndefined()
    expect(findButton(wrapper, 'Modifier la mission')).toBeUndefined()
    expect(findButton(wrapper, 'Supprimer la mission')).toBeUndefined()
    // La consultation du detail reste possible
    expect(findButton(wrapper, 'Voir le détail de la mission')).toBeDefined()
    // Pas de filtre exercice pour le collaborateur
    expect(wrapper.find('#m-exercice').exists()).toBe(false)
  })
})

describe('MissionListPage — creation', () => {
  it("ouvre le dialog pre-rempli avec l'exercice courant et cree la mission", async () => {
    missionsCreate.mockResolvedValue({ data: { id: 99 } })
    const { wrapper } = await mountPage(MissionListPage)
    missionsGetAll.mockClear()

    await findButton(wrapper, 'Créer une nouvelle mission')!.trigger('click')
    await flushPromises()

    // Dialog visible avec la section exercice reservee a l'admin
    expect(wrapper.text()).toContain('Exercice *')
    expect(wrapper.text()).toContain('Entreprise *')

    const vm = wrapper.vm as any
    expect(vm.form.exercice_id).toBe(10)

    vm.form.entreprise_id = 1
    vm.form.prestation_id = 3
    vm.dateDebut = new Date(2026, 0, 15)
    // dateFin laisse a null -> toIsoDate(null) => ''
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(missionsCreate).toHaveBeenCalledOnce()
    const payload = missionsCreate.mock.calls[0][0]
    expect(payload).toEqual(expect.objectContaining({
      entreprise_id: 1, prestation_id: 3, exercice_id: 10, date_fin: '',
    }))
    // Regression J-1 : minuit LOCAL (emis par le DatePicker) doit rester le meme
    // jour — l'ancien toISOString (UTC) donnait 2026-01-14 en UTC+1.
    expect(payload.date_debut).toBe('2026-01-15')

    // Toast succes + fermeture du dialog + refetch de la liste
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Mission creee.' }))
    expect(vm.dialogVisible).toBe(false)
    expect(missionsGetAll).toHaveBeenCalledOnce()
  })

  it("laisse le dialog ouvert si l'API refuse la creation", async () => {
    missionsCreate.mockRejectedValue(new Error('422'))
    const { wrapper } = await mountPage(MissionListPage)

    await findButton(wrapper, 'Nouvelle mission')!.trigger('click')
    const vm = wrapper.vm as any
    vm.form.entreprise_id = 1
    vm.form.prestation_id = 3
    vm.dateDebut = new Date(2026, 0, 15)
    vm.dateFin = new Date(2026, 5, 30)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(missionsCreate).toHaveBeenCalledOnce()
    expect(vm.dialogVisible).toBe(true)
    expect(vm.saving).toBe(false)
  })
})

describe('MissionListPage — edition', () => {
  it("pre-remplit le formulaire d'edition puis enregistre les modifications", async () => {
    missionsUpdate.mockResolvedValue({ data: missions[0] })
    const { wrapper } = await mountPage(MissionListPage)

    const editButtons = wrapper.findAll('button').filter(b => b.attributes('aria-label') === 'Modifier la mission')
    await editButtons[0].trigger('click')
    await flushPromises()

    const vm = wrapper.vm as any
    expect(vm.editDialogVisible).toBe(true)
    expect(vm.editForm.collaborateur_ids).toEqual([7])
    expect(vm.editForm.notes).toBe('RAS')
    expect(vm.editDateFin).toBeInstanceOf(Date)

    await wrapper.find('#e-notes').setValue('Dossier mis a jour')
    const forms = wrapper.findAll('form')
    await forms[forms.length - 1].trigger('submit')
    await flushPromises()

    expect(missionsUpdate).toHaveBeenCalledWith(1, expect.objectContaining({
      date_debut: '2026-01-15',
      date_fin: '2026-06-30',
      collaborateur_ids: [7],
      notes: 'Dossier mis a jour',
    }))
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Mission mise a jour.' }))
    expect(vm.editDialogVisible).toBe(false)
  })

  it('gere une mission sans date de fin, sans collaborateurs et sans notes', async () => {
    const { wrapper } = await mountPage(MissionListPage)

    const editButtons = wrapper.findAll('button').filter(b => b.attributes('aria-label') === 'Modifier la mission')
    await editButtons[1].trigger('click') // M2026-002

    const vm = wrapper.vm as any
    expect(vm.editDateFin).toBeNull()
    expect(vm.editForm.collaborateur_ids).toEqual([])
    expect(vm.editForm.notes).toBe('')
  })

  it("reste ouvert si l'enregistrement echoue et ignore la soumission sans mission", async () => {
    missionsUpdate.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountPage(MissionListPage)
    const vm = wrapper.vm as any

    // Garde : aucune mission en edition -> retour immediat sans appel API
    await vm.onSubmitEdit()
    expect(missionsUpdate).not.toHaveBeenCalled()

    const editButtons = wrapper.findAll('button').filter(b => b.attributes('aria-label') === 'Modifier la mission')
    await editButtons[0].trigger('click')
    const forms = wrapper.findAll('form')
    await forms[forms.length - 1].trigger('submit')
    await flushPromises()

    expect(missionsUpdate).toHaveBeenCalledOnce()
    expect(vm.editDialogVisible).toBe(true)
    expect(vm.editSaving).toBe(false)
  })
})

describe('MissionListPage — suppression', () => {
  it('demande confirmation avec la reference puis supprime au accept', async () => {
    missionsDelete.mockResolvedValue(undefined)
    const { wrapper } = await mountPage(MissionListPage)

    const delButtons = wrapper.findAll('button').filter(b => b.attributes('aria-label') === 'Supprimer la mission')
    await delButtons[0].trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('M2026-001')

    await config.accept()
    await flushPromises()

    expect(missionsDelete).toHaveBeenCalledWith(1)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Mission supprimee.' }))
  })

  it('remonte le message metier du backend si la suppression echoue', async () => {
    missionsDelete.mockRejectedValue({ response: { data: { message: 'Mission avec factures associees.' } } })
    const { wrapper } = await mountPage(MissionListPage)

    const delButtons = wrapper.findAll('button').filter(b => b.attributes('aria-label') === 'Supprimer la mission')
    await delButtons[0].trigger('click')
    await confirmRequire.mock.calls[0][0].accept()
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Mission avec factures associees.' }),
    )
  })
})
