import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Prestation } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetAll, mockCreate, mockUpdate, mockDelete } = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
  mockCreate: vi.fn(),
  mockUpdate: vi.fn(),
  mockDelete: vi.fn(),
}))

vi.mock('@/api/modules/prestations', () => ({
  prestationsApi: {
    getAll: mockGetAll,
    create: mockCreate,
    update: mockUpdate,
    delete: mockDelete,
  },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

const confirmRequire = vi.fn()
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import PrestationListPage from '@/pages/prestations/PrestationListPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const prestations: Prestation[] = [
  {
    id: 1, code: 'ACMPT', designation: 'Assistance comptable',
    tarif_initial: 120000, duree_mois: 12, description: null, actif: true,
    created_at: '', updated_at: '',
  },
  {
    id: 2, code: 'AUDIT', designation: 'Audit legal',
    tarif_initial: 200000, duree_mois: 6, description: null, actif: false,
    created_at: '', updated_at: '',
  },
]

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: prestations })
})

describe('PrestationListPage — liste', () => {
  it('charge et affiche les prestations au montage', async () => {
    const { wrapper } = await mountPage(PrestationListPage)

    expect(mockGetAll).toHaveBeenCalledOnce()
    expect(wrapper.text()).toContain('ACMPT')
    expect(wrapper.text()).toContain('Assistance comptable')
    expect(wrapper.text()).toContain('AUDIT')
  })

  it('formate le tarif en DA et affiche le statut actif/inactif', async () => {
    const { wrapper } = await mountPage(PrestationListPage)

    expect(wrapper.text()).toContain('DA')
    expect(wrapper.text()).toContain('Actif')
    expect(wrapper.text()).toContain('Inactif')
  })
})

describe('PrestationListPage — creation', () => {
  it('ouvre le dialog de creation et soumet le formulaire', async () => {
    mockCreate.mockResolvedValue({ data: { id: 3 } })
    const { wrapper } = await mountPage(PrestationListPage)

    await findButton(wrapper, 'Nouvelle prestation')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Nouvelle prestation')

    // Remplit le formulaire via les inputs du dialog
    await wrapper.find('#p-code').setValue('BILAN')
    await wrapper.find('#p-designation').setValue('Bilan annuel')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalledWith(expect.objectContaining({ code: 'BILAN', designation: 'Bilan annuel' }))
    // Refetch apres creation
    expect(mockGetAll).toHaveBeenCalledTimes(2)
  })

  it("reste ouvert si l'API renvoie une erreur", async () => {
    mockCreate.mockRejectedValue(new Error('422'))
    const { wrapper } = await mountPage(PrestationListPage)

    await findButton(wrapper, 'Nouvelle prestation')!.trigger('click')
    await wrapper.find('#p-code').setValue('X')
    await wrapper.find('#p-designation').setValue('Y')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalled()
    // Le dialog de creation est toujours affiche
    expect(wrapper.find('#p-code').exists()).toBe(true)
  })
})

describe('PrestationListPage — modification', () => {
  it('ouvre le dialog pre-rempli et enregistre', async () => {
    mockUpdate.mockResolvedValue({ data: prestations[0] })
    const { wrapper } = await mountPage(PrestationListPage)

    await findButton(wrapper, 'Modifier la prestation')!.trigger('click')
    await flushPromises()

    const codeInput = wrapper.find('#e-code')
    expect((codeInput.element as HTMLInputElement).value).toBe('ACMPT')

    await codeInput.setValue('ACMPT2')
    await wrapper.find('#e-code').trigger('change')
    const forms = wrapper.findAll('form')
    await forms[forms.length - 1].trigger('submit')
    await flushPromises()

    expect(mockUpdate).toHaveBeenCalledWith(1, expect.objectContaining({ code: 'ACMPT2' }))
  })
})

describe('PrestationListPage — suppression', () => {
  it('demande confirmation puis supprime au accept', async () => {
    mockDelete.mockResolvedValue(undefined)
    const { wrapper } = await mountPage(PrestationListPage)

    await findButton(wrapper, 'Supprimer la prestation')!.trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('Assistance comptable')

    // Simule le clic "Supprimer" du ConfirmDialog
    await config.accept()
    await flushPromises()

    expect(mockDelete).toHaveBeenCalledWith(1)
    expect(mockGetAll).toHaveBeenCalledTimes(2)
  })
})
