import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { KpiObjectifType, KpiObjectifValeur } from '@/api/modules/stats'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockUpsert, mockDeleteKpi } = vi.hoisted(() => ({
  mockUpsert: vi.fn(),
  mockDeleteKpi: vi.fn(),
}))

vi.mock('@/api/modules/stats', () => ({
  statsApi: {
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
import ObjectifsEditor from '@/pages/statistiques/ObjectifsEditor.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const user = { id: 10, name: 'ali benali' }

function baseProps(objectifs: Partial<Record<KpiObjectifType, KpiObjectifValeur>> = {}, exerciceId: number | null = 1) {
  return { user, objectifs, exerciceId }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('ObjectifsEditor — pré-remplissage', () => {
  it('pré-remplit les champs depuis les objectifs existants, laisse les autres vides', async () => {
    const { wrapper } = await mountPage(ObjectifsEditor, {
      props: baseProps({ ca_ht: { id: 501, valeur: 1000000 } }),
    })
    const vm = wrapper.vm as any

    expect(vm.editing.ca_ht).toBe(1000000)
    expect(vm.editing.missions_cloturees).toBeNull()
    expect(vm.editing.taches_terminees).toBeNull()
  })

  it('affiche le texte d\'aide sur la suppression et le suffixe DA', async () => {
    const { wrapper } = await mountPage(ObjectifsEditor, { props: baseProps() })

    expect(wrapper.text()).toContain("Vider un champ puis sauvegarder supprime l'objectif.")
    const input = wrapper.find(`#obj-10-ca_ht`)
    expect(input.exists()).toBe(true)
  })

  it('désactive le bouton de sauvegarde quand aucun exercice n\'est sélectionné', async () => {
    const { wrapper } = await mountPage(ObjectifsEditor, { props: baseProps({}, null) })
    const bouton = findButton(wrapper, 'Sauvegarder les objectifs de ali benali')!
    expect(bouton.attributes('disabled')).toBeDefined()
  })
})

describe('ObjectifsEditor — sauvegarde', () => {
  it("n'effectue aucun appel réseau et affiche un toast info quand rien n'a changé", async () => {
    const { wrapper } = await mountPage(ObjectifsEditor, { props: baseProps() })
    const vm = wrapper.vm as any

    vm.sauvegarder()
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'info', detail: 'Aucune modification à enregistrer.' }),
    )
    expect(confirmRequire).not.toHaveBeenCalled()
    expect(mockUpsert).not.toHaveBeenCalled()
    expect(mockDeleteKpi).not.toHaveBeenCalled()
    expect(wrapper.emitted('saved')).toBeFalsy()
  })

  it('demande confirmation puis supprime l\'objectif quand le champ est vidé', async () => {
    mockDeleteKpi.mockResolvedValue(undefined)
    const { wrapper } = await mountPage(ObjectifsEditor, {
      props: baseProps({ ca_ht: { id: 501, valeur: 1000000 } }),
    })
    const vm = wrapper.vm as any

    vm.editing.ca_ht = null
    vm.sauvegarder()

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('supprimé')
    expect(config.message).toContain('ali benali')

    await config.accept()
    await flushPromises()

    expect(mockDeleteKpi).toHaveBeenCalledWith(501)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }))
    expect(wrapper.emitted('saved')).toBeTruthy()
  })

  it('demande confirmation avant d\'écraser une valeur existante puis upsert', async () => {
    mockUpsert.mockResolvedValue({ data: { id: 501, type: 'ca_ht', valeur: 2000000 } })
    const { wrapper } = await mountPage(ObjectifsEditor, {
      props: baseProps({ ca_ht: { id: 501, valeur: 1000000 } }),
    })
    const vm = wrapper.vm as any

    vm.editing.ca_ht = 2000000
    vm.sauvegarder()

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('mis à jour')

    await config.accept()
    await flushPromises()

    expect(mockUpsert).toHaveBeenCalledWith(
      expect.objectContaining({ user_id: 10, exercice_id: 1, type: 'ca_ht', valeur: 2000000 }),
    )
    expect(wrapper.emitted('saved')).toBeTruthy()
  })

  it('crée un nouvel objectif sans confirmation quand aucune valeur n\'existait', async () => {
    mockUpsert.mockResolvedValue({ data: { id: 999, type: 'missions_cloturees', valeur: 10 } })
    const { wrapper } = await mountPage(ObjectifsEditor, { props: baseProps() })
    const vm = wrapper.vm as any

    vm.editing.missions_cloturees = 10
    vm.sauvegarder()
    await flushPromises()

    expect(confirmRequire).not.toHaveBeenCalled()
    expect(mockUpsert).toHaveBeenCalledWith(
      expect.objectContaining({ user_id: 10, exercice_id: 1, type: 'missions_cloturees', valeur: 10 }),
    )
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }))
    expect(wrapper.emitted('saved')).toBeTruthy()
  })

  it('affiche un toast erreur nommant le(s) type(s) en échec en cas d\'échec partiel', async () => {
    mockUpsert.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountPage(ObjectifsEditor, { props: baseProps() })
    const vm = wrapper.vm as any

    vm.editing.missions_cloturees = 10
    vm.sauvegarder()
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: expect.stringContaining('Missions clôturées') }),
    )
    expect(wrapper.emitted('saved')).toBeTruthy()
  })
})

describe('ObjectifsEditor — changement de collaborateur', () => {
  it('réinitialise la saisie quand les props (utilisateur/objectifs) changent', async () => {
    const { wrapper } = await mountPage(ObjectifsEditor, {
      props: baseProps({ ca_ht: { id: 501, valeur: 1000000 } }),
    })
    const vm = wrapper.vm as any
    expect(vm.editing.ca_ht).toBe(1000000)

    await wrapper.setProps({
      user: { id: 11, name: 'Sara Khelifi' },
      objectifs: {},
    })

    expect(vm.editing.ca_ht).toBeNull()
  })
})
