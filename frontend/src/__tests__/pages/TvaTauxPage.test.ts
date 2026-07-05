import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import type { TvaTaux } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetAll, mockCreate, mockUpdate, mockDelete } = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
  mockCreate: vi.fn(),
  mockUpdate: vi.fn(),
  mockDelete: vi.fn(),
}))

vi.mock('@/api/modules/referentiels', () => ({
  referentielsApi: {
    getAllTvaTaux: mockGetAll,
    createTvaTaux: mockCreate,
    updateTvaTaux: mockUpdate,
    deleteTvaTaux: mockDelete,
  },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

const confirmRequire = vi.fn()
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import TvaTauxPage from '@/pages/settings/TvaTauxPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures : un taux par statut possible ───────────────────────────────────
function makeTaux(overrides: Partial<TvaTaux>): TvaTaux {
  return {
    id: 1, taux: 19, designation: 'TVA standard', type: 'standard',
    date_debut: '2020-01-01', date_fin: null, actif: true,
    created_at: '', updated_at: '',
    ...overrides,
  }
}

const fixtures: TvaTaux[] = [
  makeTaux({ id: 1, taux: 19, designation: 'TVA standard 19' }),                                        // En vigueur
  makeTaux({ id: 2, taux: 0, designation: 'Exoneration TVA', type: 'exonere' }),                        // Exonere
  makeTaux({ id: 3, taux: 17, designation: 'Ancien taux 17', date_debut: '2010-01-01', date_fin: '2016-12-31' }), // Cloture
  makeTaux({ id: 4, taux: 21, designation: 'Futur taux 21', date_debut: '2099-01-01' }),                // A venir
  makeTaux({ id: 5, taux: 9, designation: 'Taux desactive', actif: false }),                            // Inactif
]

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: fixtures })
})

describe('TvaTauxPage — liste', () => {
  it('charge les taux au montage et affiche categories, taux et periodes', async () => {
    const { wrapper } = await mountPage(TvaTauxPage)

    expect(mockGetAll).toHaveBeenCalledOnce()
    expect(wrapper.text()).toContain('TVA standard 19')
    expect(wrapper.text()).toContain('Exoneration TVA')
    // Tags de categorie
    expect(wrapper.text()).toContain('Standard')
    expect(wrapper.text()).toContain('Exonere')
    // Taux numerique + periode ouverte
    expect(wrapper.text()).toContain('19 %')
    expect(wrapper.text()).toContain('en cours')
    // Note pedagogique sur l'historisation (RGAA role=note)
    expect(wrapper.find('[role="note"]').text()).toContain('historisee')
  })

  it('calcule le statut reel selon les dates : en vigueur / cloture / a venir / inactif', async () => {
    const { wrapper } = await mountPage(TvaTauxPage)

    expect(wrapper.text()).toContain('En vigueur')
    expect(wrapper.text()).toContain('Cloture')
    expect(wrapper.text()).toContain('A venir')
    expect(wrapper.text()).toContain('Inactif')
  })
})

describe('TvaTauxPage — creation', () => {
  it('ouvre le dialog, soumet le formulaire et ferme apres succes', async () => {
    mockCreate.mockResolvedValue({ data: makeTaux({ id: 6 }) })
    const { wrapper } = await mountPage(TvaTauxPage)

    await findButton(wrapper, 'Creer un taux de TVA')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Nouveau taux de TVA')

    await wrapper.find('#t-designation').setValue('TVA reduite')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalledOnce()
    const payload = mockCreate.mock.calls[0][0]
    expect(payload).toMatchObject({
      designation: 'TVA reduite',
      type: 'standard',
      taux: 19,
      date_fin: null,
      actif: true,
    })
    // date_debut par defaut = aujourd'hui au format ISO local
    expect(payload.date_debut).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    // Refetch puis fermeture du dialog
    expect(mockGetAll).toHaveBeenCalledTimes(2)
    expect(wrapper.find('#t-designation').exists()).toBe(false)
  })

  it("garde le dialog ouvert si l'API rejette", async () => {
    mockCreate.mockRejectedValue(new Error('422'))
    const { wrapper } = await mountPage(TvaTauxPage)

    await findButton(wrapper, 'Creer un taux de TVA')!.trigger('click')
    await wrapper.find('#t-designation').setValue('KO')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalled()
    expect(wrapper.find('#t-designation').exists()).toBe(true)
  })

  it('le libelle de la categorie standard suit le taux saisi (et retombe a 0 si vide)', async () => {
    const { wrapper } = await mountPage(TvaTauxPage)
    await findButton(wrapper, 'Creer un taux de TVA')!.trigger('click')

    const vm = wrapper.vm as any
    expect(vm.typeOptions[0].label).toBe('Standard (19%)')
    expect(vm.typeOptions[1].label).toBe('Exonere (0%)')

    vm.form.taux = null
    await nextTick()
    expect(vm.typeOptions[0].label).toBe('Standard (0%)')
  })

  it('remet la date de fin a null si la date de debut devient posterieure', async () => {
    const { wrapper } = await mountPage(TvaTauxPage)
    await findButton(wrapper, 'Creer un taux de TVA')!.trigger('click')

    const vm = wrapper.vm as any
    vm.form.date_debut = new Date('2026-01-01')
    vm.form.date_fin = new Date('2026-06-30')
    await nextTick()
    // Coherent : rien ne bouge
    expect(vm.form.date_fin).not.toBeNull()

    vm.form.date_debut = new Date('2026-09-01')
    await nextTick()
    expect(vm.form.date_fin).toBeNull()
  })
})

describe('TvaTauxPage — modification', () => {
  it('pre-remplit le formulaire (y compris date de fin) et enregistre', async () => {
    mockUpdate.mockResolvedValue({ data: fixtures[2] })
    const { wrapper } = await mountPage(TvaTauxPage)

    // Bouton "Modifier" de la 3e ligne (taux cloture, avec date_fin)
    const editButtons = wrapper.findAll('button').filter(b => b.attributes('aria-label') === 'Modifier le taux')
    expect(editButtons.length).toBe(fixtures.length)
    await editButtons[2].trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Modifier le taux de TVA')
    const input = wrapper.find('#t-designation')
    expect((input.element as HTMLInputElement).value).toBe('Ancien taux 17')

    await input.setValue('Ancien taux corrige')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockUpdate).toHaveBeenCalledOnce()
    const [id, payload] = mockUpdate.mock.calls[0]
    expect(id).toBe(3)
    expect(payload.designation).toBe('Ancien taux corrige')
    expect(payload.date_fin).toMatch(/^2016-12-3[01]$/)
    expect(mockGetAll).toHaveBeenCalledTimes(2)
  })

  it("le bouton Annuler ferme le dialog sans appeler l'API", async () => {
    const { wrapper } = await mountPage(TvaTauxPage)
    const editButtons = wrapper.findAll('button').filter(b => b.attributes('aria-label') === 'Modifier le taux')
    await editButtons[0].trigger('click')
    expect(wrapper.find('#t-designation').exists()).toBe(true)

    await findButton(wrapper, 'Annuler')!.trigger('click')
    await flushPromises()

    expect(wrapper.find('#t-designation').exists()).toBe(false)
    expect(mockUpdate).not.toHaveBeenCalled()
  })
})

describe('TvaTauxPage — suppression', () => {
  it('demande confirmation avec la designation puis supprime au accept', async () => {
    mockDelete.mockResolvedValue(undefined)
    const { wrapper } = await mountPage(TvaTauxPage)

    await findButton(wrapper, 'Supprimer le taux')!.trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('TVA standard 19')

    await config.accept()
    await flushPromises()

    expect(mockDelete).toHaveBeenCalledWith(1)
    expect(mockGetAll).toHaveBeenCalledTimes(2)
  })
})
