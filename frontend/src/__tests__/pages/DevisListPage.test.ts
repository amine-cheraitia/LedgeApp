import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Devis, Exercice, TvaTaux } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const {
  mockDevisGetAll, mockDevisCreate, mockDevisUpdate, mockDevisDelete,
  mockDevisEnvoyer, mockDevisAccepter, mockDevisRefuser, mockDevisConvertir, mockDevisGetPdf,
  mockEntreprisesGetAll, mockPrestationsGetAll, mockUsersGetAll,
  mockExercicesGetAll, mockExercicesGetCurrent, mockTvaGetAll,
} = vi.hoisted(() => ({
  mockDevisGetAll: vi.fn(),
  mockDevisCreate: vi.fn(),
  mockDevisUpdate: vi.fn(),
  mockDevisDelete: vi.fn(),
  mockDevisEnvoyer: vi.fn(),
  mockDevisAccepter: vi.fn(),
  mockDevisRefuser: vi.fn(),
  mockDevisConvertir: vi.fn(),
  mockDevisGetPdf: vi.fn(),
  mockEntreprisesGetAll: vi.fn(),
  mockPrestationsGetAll: vi.fn(),
  mockUsersGetAll: vi.fn(),
  mockExercicesGetAll: vi.fn(),
  mockExercicesGetCurrent: vi.fn(),
  mockTvaGetAll: vi.fn(),
}))

vi.mock('@/api/modules/devis', () => ({
  devisApi: {
    getAll: mockDevisGetAll,
    create: mockDevisCreate,
    update: mockDevisUpdate,
    delete: mockDevisDelete,
    envoyer: mockDevisEnvoyer,
    accepter: mockDevisAccepter,
    refuser: mockDevisRefuser,
    convertirEnMission: mockDevisConvertir,
    getPdf: mockDevisGetPdf,
  },
}))

vi.mock('@/api/modules/entreprises', () => ({
  entreprisesApi: { getAll: mockEntreprisesGetAll },
}))

vi.mock('@/api/modules/prestations', () => ({
  prestationsApi: { getAll: mockPrestationsGetAll },
}))

vi.mock('@/api/modules/users', () => ({
  usersApi: { getAll: mockUsersGetAll },
}))

vi.mock('@/api/modules/exercices', () => ({
  exercicesApi: { getAll: mockExercicesGetAll, getCurrent: mockExercicesGetCurrent },
}))

vi.mock('@/api/modules/referentiels', () => ({
  referentielsApi: { getAllTvaTaux: mockTvaGetAll },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

const confirmRequire = vi.fn()
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import DevisListPage from '@/pages/devis/DevisListPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const YEAR = new Date().getFullYear()

const exercices: Exercice[] = [
  { id: 1, annee: YEAR, date_ouverture: `${YEAR}-01-01`, date_cloture: `${YEAR}-12-31`, statut: 'ouvert', created_at: '', updated_at: '' },
  { id: 2, annee: YEAR - 1, date_ouverture: `${YEAR - 1}-01-01`, date_cloture: `${YEAR - 1}-12-31`, statut: 'cloture', created_at: '', updated_at: '' },
]

const tvaTaux: TvaTaux[] = [
  { id: 1, taux: 19, designation: 'TVA standard', type: 'standard', date_debut: '2020-01-01', date_fin: null, actif: true, created_at: '', updated_at: '' },
  { id: 2, taux: 0, designation: 'Exoneration', type: 'exonere', date_debut: '2020-01-01', date_fin: null, actif: true, created_at: '', updated_at: '' },
]

const entreprises = [
  { id: 1, raison_sociale: 'ACME SARL' },
  { id: 2, raison_sociale: 'BETA EURL' },
]

const prestations = [
  { id: 1, designation: 'Assistance comptable' },
  { id: 2, designation: 'Audit legal' },
]

const users = [
  { id: 4, name: 'Karim Collaborateur' },
  { id: 5, name: 'Sara Collaboratrice' },
]

function makeDevis(overrides: Partial<Devis> = {}): Devis {
  return {
    id: 1, entreprise_id: 1, prestation_id: 1, exercice_id: 1, created_by: 1,
    numero: 'DV2026-001', date_devis: '2026-03-15', date_validite: '2026-05-15',
    prix_ht: 120000, montant_ht: 120000, taux_tva: 19, montant_tva: 22800, montant_ttc: 142800,
    statut: 'brouillon',
    entreprise: { id: 1, raison_sociale: 'ACME SARL' } as never,
    prestation: { id: 1, designation: 'Assistance comptable' } as never,
    created_at: '', updated_at: '',
    ...overrides,
  }
}

const devisList: Devis[] = [
  makeDevis(),
  makeDevis({ id: 2, numero: 'DV2026-002', statut: 'envoye' }),
  makeDevis({ id: 3, numero: 'DV2026-003', statut: 'accepte' }),
  makeDevis({ id: 4, numero: 'DV2026-004', statut: 'refuse' }),
  makeDevis({ id: 5, numero: 'DV2026-005', statut: 'expire', entreprise: undefined, prestation: undefined }),
]

const origCreateObjectURL = (URL as any).createObjectURL
const origRevokeObjectURL = (URL as any).revokeObjectURL

beforeEach(() => {
  vi.clearAllMocks()
  mockDevisGetAll.mockResolvedValue({ data: devisList, meta: { total: devisList.length } })
  mockEntreprisesGetAll.mockResolvedValue({ data: entreprises, meta: { total: entreprises.length } })
  mockPrestationsGetAll.mockResolvedValue({ data: prestations })
  mockUsersGetAll.mockResolvedValue({ data: users, meta: { total: users.length } })
  mockExercicesGetAll.mockResolvedValue({ data: exercices })
  mockExercicesGetCurrent.mockResolvedValue({ data: exercices[0] })
  mockTvaGetAll.mockResolvedValue({ data: tvaTaux })
})

afterEach(() => {
  vi.useRealTimers()
  ;(URL as any).createObjectURL = origCreateObjectURL
  ;(URL as any).revokeObjectURL = origRevokeObjectURL
})

describe('DevisListPage — liste', () => {
  it("charge les devis filtres sur l'exercice courant et les affiche", async () => {
    const { wrapper } = await mountPage(DevisListPage)

    expect(mockExercicesGetCurrent).toHaveBeenCalled()
    expect(mockDevisGetAll).toHaveBeenLastCalledWith(expect.objectContaining({ exercice_id: 1, page: 1 }))

    expect(wrapper.text()).toContain('Devis')
    expect(wrapper.text()).toContain('DV2026-001')
    expect(wrapper.text()).toContain('ACME SARL')
    expect(wrapper.text()).toContain('Assistance comptable')
    // formatMontant : montant en fr-FR (fragments robustes)
    expect(wrapper.text()).toContain('120')
    expect(wrapper.text()).toContain('142')
    // Tags de statut (toutes les branches de statutColor)
    for (const statut of ['brouillon', 'envoye', 'accepte', 'refuse', 'expire']) {
      expect(wrapper.text()).toContain(statut)
    }
    // Devis sans entreprise/prestation -> repli '-'
    expect(wrapper.text()).toContain('-')
  })

  it("statutColor retombe sur 'secondary' pour un statut inconnu", async () => {
    const { wrapper } = await mountPage(DevisListPage)
    const vm = wrapper.vm as any

    expect(vm.statutColor('accepte')).toBe('success')
    expect(vm.statutColor('inconnu')).toBe('secondary')
  })

  it('RGAA : la recherche et le filtre exercice ont un label sr-only', async () => {
    const { wrapper } = await mountPage(DevisListPage)

    const searchLabel = wrapper.find('label[for="search-devis"]')
    expect(searchLabel.exists()).toBe(true)
    expect(searchLabel.classes()).toContain('sr-only')
    expect(wrapper.find('label[for="dv-exercice"]').exists()).toBe(true)
  })

  it('la recherche refetch apres debounce de 300ms', async () => {
    const { wrapper } = await mountPage(DevisListPage)
    mockDevisGetAll.mockClear()

    vi.useFakeTimers()
    await wrapper.find('#search-devis').setValue('DV2026')
    expect(mockDevisGetAll).not.toHaveBeenCalled()
    vi.advanceTimersByTime(300)
    vi.useRealTimers()
    await flushPromises()

    expect(mockDevisGetAll).toHaveBeenCalledWith(expect.objectContaining({ search: 'DV2026', page: 1 }))
  })

  it("changer d'exercice refetch les devis avec le nouveau filtre", async () => {
    const { wrapper } = await mountPage(DevisListPage)
    mockDevisGetAll.mockClear()

    ;(wrapper.vm as any).exerciceSelectionne = 2
    await flushPromises()

    expect(mockDevisGetAll).toHaveBeenCalledWith(expect.objectContaining({ exercice_id: 2, page: 1 }))
  })
})

describe('DevisListPage — roles', () => {
  it('admin : creation, modification, suppression, accepter/refuser et conversion disponibles', async () => {
    const { wrapper } = await mountPage(DevisListPage)

    expect(findButton(wrapper, 'Nouveau devis')).toBeDefined()
    expect(findButton(wrapper, 'Modifier le devis')).toBeDefined()
    expect(findButton(wrapper, 'Envoyer le devis par mail au client')).toBeDefined()
    expect(findButton(wrapper, 'Supprimer')).toBeDefined()
    expect(findButton(wrapper, 'Accepter')).toBeDefined()
    expect(findButton(wrapper, 'Refuser')).toBeDefined()
    expect(findButton(wrapper, 'Convertir en mission')).toBeDefined()
    expect(findButton(wrapper, 'Telecharger le PDF')).toBeDefined()
  })

  it('secretaire : envoie et telecharge le PDF mais ne cree, ne modifie, ne supprime ni ne convertit', async () => {
    const { wrapper } = await mountPage(DevisListPage, { role: 'secretaire' })

    expect(findButton(wrapper, 'Envoyer le devis par mail au client')).toBeDefined()
    expect(findButton(wrapper, 'Telecharger le PDF')).toBeDefined()
    expect(findButton(wrapper, 'Nouveau devis')).toBeUndefined()
    expect(findButton(wrapper, 'Modifier le devis')).toBeUndefined()
    expect(findButton(wrapper, 'Supprimer')).toBeUndefined()
    expect(findButton(wrapper, 'Accepter')).toBeUndefined()
    expect(findButton(wrapper, 'Refuser')).toBeUndefined()
    expect(findButton(wrapper, 'Convertir en mission')).toBeUndefined()
  })

  // Regression : /referentiels/tva-taux (role:admin) renvoyait 403 en secretaire
  // -> toast d'erreur a CHAQUE visite de la page, pour alimenter le select TVA
  // du dialog « Nouveau devis » qu'elle ne voit pas.
  it('secretaire : ne charge pas les taux TVA (aucun toast d erreur)', async () => {
    await mountPage(DevisListPage, { role: 'secretaire' })

    expect(mockTvaGetAll).not.toHaveBeenCalled()
    expect(toastAdd).not.toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }))
  })

  it('admin : precharge les taux TVA pour le dialog de creation', async () => {
    await mountPage(DevisListPage)

    expect(mockTvaGetAll).toHaveBeenCalled()
  })
})

describe('DevisListPage — creation (admin)', () => {
  it("ouvre le dialog pre-rempli (exercice courant, date du jour, validite +2 mois) et cree le devis", async () => {
    mockDevisCreate.mockResolvedValue({ data: { id: 99 } })
    const { wrapper } = await mountPage(DevisListPage)

    await findButton(wrapper, 'Nouveau devis')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Nouveau devis')

    const vm = wrapper.vm as any
    expect(vm.form.exercice_id).toBe(1)
    // Seuls les exercices ouverts sont proposes
    expect(vm.exercicesOuverts).toHaveLength(1)
    expect(vm.exercicesOuverts[0].id).toBe(1)
    // Date du jour par defaut + validite auto-remplie a +2 mois par le watcher
    expect(vm.form.date_devis).toBeInstanceOf(Date)
    const attendu = new Date(vm.form.date_devis)
    attendu.setMonth(attendu.getMonth() + 2)
    expect(vm.form.date_validite.getMonth()).toBe(attendu.getMonth())

    vm.form.entreprise_id = 1
    vm.form.prestation_id = 2
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockDevisCreate).toHaveBeenCalledWith({
      entreprise_id: 1,
      prestation_id: 2,
      exercice_id: 1,
      date_devis: expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/),
      date_validite: expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/),
      type_tva: 'standard',
    })
    expect(vm.dialogVisible).toBe(false)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Devis cree.' }))
  })

  it('tvaOptions refletent le taux en vigueur a la date du devis (et un repli sans date)', async () => {
    const { wrapper } = await mountPage(DevisListPage)
    const vm = wrapper.vm as any

    vm.openCreate()
    expect(vm.tvaOptions).toEqual([
      { label: 'Standard (19 %)', value: 'standard' },
      { label: 'Exonere (0 %)', value: 'exonere' },
    ])

    vm.form.date_devis = null
    expect(vm.tvaOptions).toEqual([
      { label: 'Standard', value: 'standard' },
      { label: 'Exonere (0 %)', value: 'exonere' },
    ])
    // toIsoDate defensif sur null
    expect(vm.toIsoDate(null)).toBe('')
  })

  it('ne soumet pas si des champs obligatoires manquent', async () => {
    const { wrapper } = await mountPage(DevisListPage)
    const vm = wrapper.vm as any

    vm.openCreate()
    await vm.onSubmit() // entreprise_id / prestation_id manquants

    expect(mockDevisCreate).not.toHaveBeenCalled()
  })

  it("garde le dialog ouvert si la creation echoue", async () => {
    mockDevisCreate.mockRejectedValue({ response: { data: { message: 'Exercice clos.' } } })
    const { wrapper } = await mountPage(DevisListPage)
    const vm = wrapper.vm as any

    vm.openCreate()
    vm.form.entreprise_id = 1
    vm.form.prestation_id = 1
    await flushPromises()
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockDevisCreate).toHaveBeenCalled()
    expect(vm.dialogVisible).toBe(true)
    expect(vm.saving).toBe(false)
  })
})

describe('DevisListPage — modification (admin, brouillon)', () => {
  it('ouvre le dialog pre-rempli depuis le devis et enregistre', async () => {
    mockDevisUpdate.mockResolvedValue({ data: devisList[0] })
    const { wrapper } = await mountPage(DevisListPage)

    await findButton(wrapper, 'Modifier le devis')!.trigger('click')
    await flushPromises()

    const vm = wrapper.vm as any
    expect(vm.editDevisVisible).toBe(true)
    expect(wrapper.text()).toContain('Modifier le devis DV2026-001')
    expect(vm.editDevisForm.entreprise_id).toBe(1)
    expect(vm.editDevisForm.prestation_id).toBe(1)
    // Validite auto-recalculee a date_devis + 2 mois par le watcher
    expect(vm.editDevisForm.date_validite).toBeInstanceOf(Date)

    vm.editDevisForm.entreprise_id = 2
    await flushPromises()
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockDevisUpdate).toHaveBeenCalledWith(1, expect.objectContaining({
      entreprise_id: 2,
      prestation_id: 1,
      date_devis: expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/),
      date_validite: expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/),
    }))
    expect(vm.editDevisVisible).toBe(false)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Devis mis a jour.' }))
  })

  it('replis openEditDevis : sans relations ni dates, envoie des champs undefined', async () => {
    mockDevisUpdate.mockResolvedValue({ data: devisList[0] })
    const { wrapper } = await mountPage(DevisListPage)
    const vm = wrapper.vm as any

    vm.openEditDevis({ id: 9, numero: 'DV2026-009', entreprise_id: 2, prestation_id: 2, date_devis: null, date_validite: null })
    await flushPromises()
    expect(vm.editDevisForm.entreprise_id).toBe(2)
    expect(vm.editDevisForm.date_devis).toBeNull()

    vm.editDevisForm.entreprise_id = null
    vm.editDevisForm.prestation_id = null
    await vm.onSubmitEditDevis()

    expect(mockDevisUpdate).toHaveBeenCalledWith(9, {
      entreprise_id: undefined,
      prestation_id: undefined,
      date_devis: undefined,
      date_validite: undefined,
    })
  })

  it('ne soumet pas la modification sans devis cible et reste ouvert en cas d erreur', async () => {
    const { wrapper } = await mountPage(DevisListPage)
    const vm = wrapper.vm as any

    await vm.onSubmitEditDevis() // editDevisId null
    expect(mockDevisUpdate).not.toHaveBeenCalled()

    mockDevisUpdate.mockRejectedValue(new Error('422'))
    vm.openEditDevis(devisList[0])
    await flushPromises()
    await vm.onSubmitEditDevis()

    expect(mockDevisUpdate).toHaveBeenCalled()
    expect(vm.editDevisVisible).toBe(true)
    expect(vm.editDevisSaving).toBe(false)
  })
})
