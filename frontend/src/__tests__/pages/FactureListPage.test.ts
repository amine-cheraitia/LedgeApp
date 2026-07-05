import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import type { Facture, Avoir, Exercice, Mission, TvaTaux } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const {
  mockFacturesGetAll, mockFacturesCreate, mockFacturesDelete, mockFacturesTransmettre,
  mockFacturesGetPdf, mockFacturesCreatePaiement,
  mockAvoirsGetAll, mockAvoirsStore, mockAvoirsDelete, mockAvoirsPdf,
  mockMissionsGetAll,
  mockExercicesGetAll, mockExercicesGetCurrent,
  mockTvaGetAll,
} = vi.hoisted(() => ({
  mockFacturesGetAll: vi.fn(),
  mockFacturesCreate: vi.fn(),
  mockFacturesDelete: vi.fn(),
  mockFacturesTransmettre: vi.fn(),
  mockFacturesGetPdf: vi.fn(),
  mockFacturesCreatePaiement: vi.fn(),
  mockAvoirsGetAll: vi.fn(),
  mockAvoirsStore: vi.fn(),
  mockAvoirsDelete: vi.fn(),
  mockAvoirsPdf: vi.fn(),
  mockMissionsGetAll: vi.fn(),
  mockExercicesGetAll: vi.fn(),
  mockExercicesGetCurrent: vi.fn(),
  mockTvaGetAll: vi.fn(),
}))

vi.mock('@/api/modules/factures', () => ({
  facturesApi: {
    getAll: mockFacturesGetAll,
    create: mockFacturesCreate,
    delete: mockFacturesDelete,
    transmettre: mockFacturesTransmettre,
    getPdf: mockFacturesGetPdf,
    createPaiement: mockFacturesCreatePaiement,
  },
}))

vi.mock('@/api/modules/avoirs', () => ({
  avoirsApi: {
    getAll: mockAvoirsGetAll,
    store: mockAvoirsStore,
    delete: mockAvoirsDelete,
    telechargerPdf: mockAvoirsPdf,
  },
}))

vi.mock('@/api/modules/missions', () => ({
  missionsApi: { getAll: mockMissionsGetAll },
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
import FactureListPage from '@/pages/factures/FactureListPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
// Annee dynamique : l'exercice courant doit contenir la date du jour pour que
// la logique de bornes du DatePicker soit deterministe.
const YEAR = new Date().getFullYear()

const exercices: Exercice[] = [
  { id: 1, annee: YEAR, date_ouverture: `${YEAR}-01-01`, date_cloture: `${YEAR}-12-31`, statut: 'ouvert', created_at: '', updated_at: '' },
  { id: 2, annee: YEAR - 1, date_ouverture: `${YEAR - 1}-01-01`, date_cloture: `${YEAR - 1}-12-31`, statut: 'cloture', created_at: '', updated_at: '' },
]

function makeFacture(overrides: Partial<Facture> = {}): Facture {
  return {
    id: 1, entreprise_id: 1, exercice_id: 1, mission_id: 5, devis_id: null,
    created_by: 1, tva_rate_id: 1, numero: 'FF2026-001', type: 'FF',
    facture_origine_id: null, date_facture: '2026-03-15', date_echeance: '2026-04-29',
    montant_ht: 100000, taux_tva: 19, montant_tva: 19000, montant_ttc: 119000,
    montant_paye: 0, statut_paiement: 'en_attente', mode_paiement: 'virement',
    montant_restant: 119000, pdf_path: null,
    entreprise: { raison_sociale: 'ACME SARL' } as never,
    mission: { reference: 'M2026-001' } as never,
    paiements: [],
    created_at: '', updated_at: '',
    ...overrides,
  }
}

// mission 5 : 1 facture -> prochaine tranche T2 ; mission 7 : 2 -> T3 ; mission 8 : 3 -> Complet
const factures: Facture[] = [
  makeFacture(),
  makeFacture({ id: 2, numero: 'FF2026-002', mission_id: 7, statut_paiement: 'solde', mode_paiement: 'non_defini', montant_paye: 119000, paiements: [{ id: 1 } as never] }),
  makeFacture({ id: 3, numero: 'FF2026-003', mission_id: 7, mode_paiement: 'especes' as never, statut_paiement: 'inconnu' as never }),
  makeFacture({ id: 4, numero: 'FF2026-004', mission_id: 8 }),
  makeFacture({ id: 5, numero: 'FF2026-005', mission_id: 8 }),
  makeFacture({ id: 6, numero: 'FF2026-006', mission_id: 8 }),
]

const avoirs: Avoir[] = [
  {
    id: 1, facture_origine_id: 1, exercice_id: 1, created_by: 1, numero: 'FA2026-001',
    date_avoir: '2026-03-01', montant_ht: 10000, taux_tva_snapshot: 19, montant_tva: 1900,
    montant_ttc: 11900, motif: 'Erreur sur montant',
    facture_origine: { id: 1, numero: 'FF2026-001', entreprise: { raison_sociale: 'ACME SARL' } },
    created_at: '',
  },
  {
    id: 2, facture_origine_id: 2, exercice_id: 1, created_by: 1, numero: 'FA2026-002',
    date_avoir: '2026-03-02', montant_ht: 5000, taux_tva_snapshot: 19, montant_tva: 950,
    montant_ttc: 5950, motif: 'Remise commerciale', created_at: '',
  },
]

const missions = [
  { id: 5, reference: 'M2026-001' },
  { id: 6, reference: 'M2026-002' },
] as unknown as Mission[]

const tvaTaux: TvaTaux[] = [
  { id: 1, taux: 19, designation: 'TVA standard', type: 'standard', date_debut: '2020-01-01', date_fin: null, actif: true, created_at: '', updated_at: '' },
  { id: 2, taux: 0, designation: 'Exoneration', type: 'exonere', date_debut: '2020-01-01', date_fin: null, actif: true, created_at: '', updated_at: '' },
]

const drawerStub = { FactureDetailDrawer: true }

const origCreateObjectURL = (URL as any).createObjectURL
const origRevokeObjectURL = (URL as any).revokeObjectURL

async function openAvoirsTab(wrapper: VueWrapper) {
  const tab = wrapper.findAll('[role="tab"]').find(t => t.text().includes('Avoirs'))
  expect(tab).toBeDefined()
  await tab!.trigger('click')
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
  mockFacturesGetAll.mockResolvedValue({ data: factures, meta: { total: factures.length } })
  mockAvoirsGetAll.mockResolvedValue({ data: avoirs, meta: { total: avoirs.length } })
  mockMissionsGetAll.mockResolvedValue({ data: missions, meta: { total: missions.length } })
  mockExercicesGetAll.mockResolvedValue({ data: exercices })
  mockExercicesGetCurrent.mockResolvedValue({ data: exercices[0] })
  mockTvaGetAll.mockResolvedValue({ data: tvaTaux })
})

afterEach(() => {
  vi.useRealTimers()
  ;(URL as any).createObjectURL = origCreateObjectURL
  ;(URL as any).revokeObjectURL = origRevokeObjectURL
})

describe('FactureListPage — liste des factures', () => {
  it("charge les factures filtrees sur l'exercice courant et les affiche", async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    expect(mockExercicesGetCurrent).toHaveBeenCalled()
    // Le watcher sur l'exercice selectionne refetch avec exercice_id = exercice courant
    expect(mockFacturesGetAll).toHaveBeenLastCalledWith(expect.objectContaining({ exercice_id: 1, page: 1 }))

    expect(wrapper.text()).toContain('Factures')
    expect(wrapper.text()).toContain('FF2026-001')
    expect(wrapper.text()).toContain('ACME SARL')
    expect(wrapper.text()).toContain('M2026-001')
    expect(wrapper.text()).toContain('DA')
    // Libelles mode de paiement : mappe + fallback brut
    expect(wrapper.text()).toContain('Virement')
    expect(wrapper.text()).toContain('especes')
  })

  it('RGAA : la recherche et le filtre exercice ont un label sr-only', async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    const searchLabel = wrapper.find('label[for="search-factures"]')
    expect(searchLabel.exists()).toBe(true)
    expect(searchLabel.classes()).toContain('sr-only')
    expect(wrapper.find('label[for="f-exercice"]').exists()).toBe(true)
  })

  it('la recherche refetch apres debounce de 300ms', async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })
    mockFacturesGetAll.mockClear()

    vi.useFakeTimers()
    await wrapper.find('#search-factures').setValue('FF2026')
    expect(mockFacturesGetAll).not.toHaveBeenCalled()
    vi.advanceTimersByTime(300)
    vi.useRealTimers()
    await flushPromises()

    expect(mockFacturesGetAll).toHaveBeenCalledWith(expect.objectContaining({ search: 'FF2026', page: 1 }))
  })

  it("changer d'exercice refetch les factures ET les avoirs", async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })
    mockFacturesGetAll.mockClear()
    mockAvoirsGetAll.mockClear()

    ;(wrapper.vm as any).exerciceSelectionne = 2
    await flushPromises()

    expect(mockFacturesGetAll).toHaveBeenCalledWith(expect.objectContaining({ exercice_id: 2, page: 1 }))
    expect(mockAvoirsGetAll).toHaveBeenCalledWith(expect.objectContaining({ exercice_id: 2, page: 1 }))
  })
})

describe('FactureListPage — roles', () => {
  it('admin : creation, transmission, encaissements, avoir et suppression disponibles', async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    expect(findButton(wrapper, 'Creer une facture')).toBeDefined()
    expect(findButton(wrapper, 'Transmettre la facture par mail au client')).toBeDefined()
    expect(findButton(wrapper, 'Voir les encaissements')).toBeDefined()
    expect(findButton(wrapper, 'Emettre un avoir')).toBeDefined()

    // La suppression n'est proposee que sur les factures SANS paiement (5 sur 6)
    const delButtons = wrapper.findAll('button').filter(b => b.attributes('aria-label') === 'Supprimer')
    expect(delButtons).toHaveLength(5)
  })

  it('secretaire : transmet et encaisse mais ne cree, ne supprime ni n emet d avoir', async () => {
    const { wrapper } = await mountPage(FactureListPage, { role: 'secretaire', stubs: drawerStub })

    expect(findButton(wrapper, 'Transmettre la facture par mail au client')).toBeDefined()
    expect(findButton(wrapper, 'Voir les encaissements')).toBeDefined()
    expect(findButton(wrapper, 'Creer une facture')).toBeUndefined()
    expect(findButton(wrapper, 'Emettre un avoir')).toBeUndefined()
    expect(findButton(wrapper, 'Supprimer')).toBeUndefined()
  })
})

describe('FactureListPage — creation de facture (admin)', () => {
  it('ouvre le dialog pre-rempli, suggere la tranche et cree la facture', async () => {
    mockFacturesCreate.mockResolvedValue({ data: { id: 99 } })
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    await findButton(wrapper, 'Creer une facture')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Nouvelle facture')

    const vm = wrapper.vm as any
    // Exercice courant pre-selectionne, seuls les exercices ouverts sont proposes
    expect(vm.form.exercice_id).toBe(1)
    expect(vm.exercicesOuverts).toHaveLength(1)

    vm.form.mission_id = 5
    await flushPromises()
    // La mission 5 a deja 1 facture -> prochaine tranche = T2 30%
    expect(wrapper.text()).toContain('Prochaine tranche')
    expect(wrapper.text()).toContain('T2 — 30%')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockFacturesCreate).toHaveBeenCalledWith({
      mission_id: 5,
      exercice_id: 1,
      date_facture: expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/),
      type_tva: 'standard',
    })
    expect(vm.dialogVisible).toBe(false)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Facture creee.' }))
  })

  it('trancheLabel suit la regle 30/30/40 selon les factures existantes', async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })
    const vm = wrapper.vm as any

    expect(vm.trancheLabel(null)).toBe('—')
    expect(vm.trancheLabel(6)).toBe('T1 — 30%')
    expect(vm.trancheLabel(5)).toBe('T2 — 30%')
    expect(vm.trancheLabel(7)).toBe('T3 — 40% (solde)')
    expect(vm.trancheLabel(8)).toBe('Complet')
  })

  it("affiche l'erreur backend et garde le dialog ouvert si la creation echoue", async () => {
    mockFacturesCreate.mockRejectedValue({ response: { data: { message: 'Exercice clos.' } } })
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })
    const vm = wrapper.vm as any

    vm.openCreate()
    vm.form.mission_id = 5
    await flushPromises()
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error', detail: 'Exercice clos.' }))
    expect(vm.dialogVisible).toBe(true)
  })

  it('ne soumet pas sans mission selectionnee', async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })
    const vm = wrapper.vm as any

    vm.openCreate()
    await vm.onSubmitFacture()

    expect(mockFacturesCreate).not.toHaveBeenCalled()
  })

  it("tvaOptions refletent le taux en vigueur a la date choisie et l'echeance = date + 45j", async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })
    const vm = wrapper.vm as any

    vm.form.date_facture = new Date(YEAR, 2, 15)
    expect(vm.tvaOptions).toEqual([
      { label: 'Standard (19 %)', value: 'standard' },
      { label: 'Exonere (0 %)', value: 'exonere' },
    ])
    expect(vm.dateEcheancePreview).toBe(new Date(YEAR, 3, 29).toLocaleDateString('fr-FR'))

    // Sans date : pas de taux resolvable, libelles de repli + echeance vide
    vm.form.date_facture = null
    expect(vm.dateEcheancePreview).toBe('')
    expect(vm.tvaOptions).toEqual([
      { label: 'Standard', value: 'standard' },
      { label: 'Exonere (0 %)', value: 'exonere' },
    ])
    // toIsoDate est defensif sur null
    expect(vm.toIsoDate(null)).toBe('')
  })

  it("ramene la date de facture dans les bornes de l'exercice choisi", async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })
    const vm = wrapper.vm as any

    vm.openCreate()
    await flushPromises()

    // Exercice N-1 : la date du jour sort des bornes -> 1er jour de l'exercice
    vm.form.exercice_id = 2
    await flushPromises()
    expect(vm.form.date_facture.getFullYear()).toBe(YEAR - 1)
    expect(vm.dateMin?.getFullYear()).toBe(YEAR - 1)
    expect(vm.dateMax?.getFullYear()).toBe(YEAR - 1)

    // Retour a l'exercice courant : la date du jour est dans la plage
    vm.form.exercice_id = 1
    await flushPromises()
    expect(vm.form.date_facture.getFullYear()).toBe(YEAR)

    // Exercice inconnu : pas de bornes, la date reste inchangee
    const before = vm.form.date_facture
    vm.form.exercice_id = null
    await flushPromises()
    expect(vm.form.date_facture).toBe(before)
    expect(vm.dateMin).toBeUndefined()
  })
})

describe('FactureListPage — transmission et PDF', () => {
  it('secretaire : transmet la facture apres confirmation', async () => {
    mockFacturesTransmettre.mockResolvedValue({ message: 'ok' })
    const { wrapper } = await mountPage(FactureListPage, { role: 'secretaire', stubs: drawerStub })

    await findButton(wrapper, 'Transmettre la facture par mail au client')!.trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('FF2026-001')

    await config.accept()
    await flushPromises()

    expect(mockFacturesTransmettre).toHaveBeenCalledWith(1)
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Facture transmise au client par mail.' }),
    )
  })

  it('telecharge le PDF de la facture sous le nom facture-{numero}.pdf', async () => {
    mockFacturesGetPdf.mockResolvedValue(new Blob(['%PDF']))
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    ;(URL as any).createObjectURL = vi.fn(() => 'blob:x')
    ;(URL as any).revokeObjectURL = vi.fn()
    const anchor = document.createElement('a')
    anchor.click = vi.fn()
    const createSpy = vi.spyOn(document, 'createElement').mockReturnValue(anchor as any)

    await findButton(wrapper, 'Telecharger le PDF')!.trigger('click')
    await flushPromises()
    createSpy.mockRestore()

    expect(mockFacturesGetPdf).toHaveBeenCalledWith(1)
    expect(anchor.download).toBe('facture-FF2026-001.pdf')
    expect(anchor.click).toHaveBeenCalled()
  })
})

describe('FactureListPage — drawer encaissements', () => {
  it('ouvre le drawer sur la facture choisie', async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    await findButton(wrapper, 'Voir les encaissements')!.trigger('click')

    const vm = wrapper.vm as any
    expect(vm.drawerVisible).toBe(true)
    expect(vm.drawerFacture.numero).toBe('FF2026-001')
  })
})

describe('FactureListPage — avoirs', () => {
  it("affiche les avoirs dans l'onglet dedie (avec repli '-' sans facture d'origine)", async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    await openAvoirsTab(wrapper)

    expect(wrapper.text()).toContain('FA2026-001')
    expect(wrapper.text()).toContain('Erreur sur montant')
    expect(wrapper.text()).toContain('FA2026-002')
    expect(wrapper.text()).toContain('Remise commerciale')
  })

  it('pagine et recherche les avoirs (debounce)', async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })
    const vm = wrapper.vm as any

    mockAvoirsGetAll.mockClear()
    vm.onAvoirsPage({ page: 1 })
    expect(mockAvoirsGetAll).toHaveBeenCalledWith(expect.objectContaining({ page: 2, per_page: 15 }))
    await flushPromises()

    vi.useFakeTimers()
    vm.avoirsSearch = 'FA2026'
    await nextTick()
    mockAvoirsGetAll.mockClear()
    vi.advanceTimersByTime(300)
    vi.useRealTimers()
    await flushPromises()

    expect(mockAvoirsGetAll).toHaveBeenCalledWith(expect.objectContaining({ search: 'FA2026', page: 1 }))
  })

  it('toast erreur si le chargement des avoirs echoue', async () => {
    mockAvoirsGetAll.mockRejectedValue(new Error('500'))
    await mountPage(FactureListPage, { stubs: drawerStub })

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les avoirs.' }),
    )
  })

  it("admin : emet un avoir depuis une facture (montant pre-rempli) puis refetch les 2 listes", async () => {
    mockAvoirsStore.mockResolvedValue({ data: { id: 10 } })
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    await findButton(wrapper, 'Emettre un avoir')!.trigger('click')
    await flushPromises()

    const vm = wrapper.vm as any
    expect(wrapper.text()).toContain('FF2026-001')
    expect(vm.avoirForm.montant_ht).toBe(100000)

    await wrapper.find('#a-motif').setValue('Erreur de facturation')
    mockFacturesGetAll.mockClear()
    mockAvoirsGetAll.mockClear()
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockAvoirsStore).toHaveBeenCalledWith(1, {
      montant_ht: 100000,
      date_avoir: expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/),
      motif: 'Erreur de facturation',
    })
    expect(vm.avoirDialogVisible).toBe(false)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', summary: 'Avoir cree' }))
    expect(mockFacturesGetAll).toHaveBeenCalled()
    expect(mockAvoirsGetAll).toHaveBeenCalled()
  })

  it("garde le dialog avoir ouvert et affiche l'erreur backend en cas d'echec", async () => {
    mockAvoirsStore.mockRejectedValue({ response: { data: { message: 'Montant superieur au restant.' } } })
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    const vm = wrapper.vm as any
    vm.openAvoir(factures[0])
    await flushPromises()
    await wrapper.find('#a-motif').setValue('Trop percu')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Montant superieur au restant.' }),
    )
    expect(vm.avoirDialogVisible).toBe(true)
  })

  it('onSubmitAvoir ne fait rien sans facture cible', async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    await (wrapper.vm as any).onSubmitAvoir()

    expect(mockAvoirsStore).not.toHaveBeenCalled()
  })

  it("admin : supprime un avoir apres confirmation puis refetch", async () => {
    mockAvoirsDelete.mockResolvedValue(undefined)
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })
    await openAvoirsTab(wrapper)

    await findButton(wrapper, "Supprimer l'avoir")!.trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('FA2026-001')

    mockAvoirsGetAll.mockClear()
    await config.accept()
    await flushPromises()

    expect(mockAvoirsDelete).toHaveBeenCalledWith(1)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Avoir supprime.' }))
    expect(mockAvoirsGetAll).toHaveBeenCalled()
  })

  it("toast erreur si la suppression de l'avoir echoue", async () => {
    mockAvoirsDelete.mockRejectedValue(new Error('409'))
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    ;(wrapper.vm as any).confirmDeleteAvoir(avoirs[0])
    const config = confirmRequire.mock.calls[0][0]
    await config.accept()
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: "Impossible de supprimer l'avoir." }),
    )
  })

  it("telecharge le PDF d'un avoir avec la facture d'origine", async () => {
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })
    await openAvoirsTab(wrapper)

    await findButton(wrapper, 'Telecharger PDF avoir')!.trigger('click')

    expect(mockAvoirsPdf).toHaveBeenCalledWith(1, 1, 'FA2026-001')
  })
})

describe('FactureListPage — suppression de facture (admin)', () => {
  it('supprime une facture sans paiement apres confirmation', async () => {
    mockFacturesDelete.mockResolvedValue(undefined)
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    await findButton(wrapper, 'Supprimer')!.trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('FF2026-001')

    await config.accept()
    await flushPromises()

    expect(mockFacturesDelete).toHaveBeenCalledWith(1)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Facture supprimee.' }))
  })

  it('affiche la popup dediee si la suppression est refusee (409) et la ferme', async () => {
    mockFacturesDelete.mockRejectedValue({ response: { data: { message: 'Seule la derniere facture de la mission peut etre supprimee.' } } })
    const { wrapper } = await mountPage(FactureListPage, { stubs: drawerStub })

    await findButton(wrapper, 'Supprimer')!.trigger('click')
    const config = confirmRequire.mock.calls[0][0]
    await config.accept()
    await flushPromises()

    const vm = wrapper.vm as any
    expect(vm.deleteErrorVisible).toBe(true)
    expect(wrapper.text()).toContain('Suppression impossible')
    expect(wrapper.text()).toContain('Seule la derniere facture de la mission peut etre supprimee.')

    await findButton(wrapper, 'Fermer')!.trigger('click')
    expect(vm.deleteErrorVisible).toBe(false)
  })
})
