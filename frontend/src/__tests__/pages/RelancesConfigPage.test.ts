import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Setting } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetAll, mockUpdate } = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
  mockUpdate: vi.fn(),
}))

vi.mock('@/api/modules/settings', () => ({
  settingsApi: { getAll: mockGetAll, update: mockUpdate },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import RelancesConfigPage from '@/pages/relances/RelancesConfigPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
// relance_niveau3_jours et relance_template_n3 volontairement absents
// -> couvre les valeurs par defaut (45) et le setter no-op
function makeSettings(): Setting[] {
  return [
    { id: 1, key: 'relance_niveau1_jours', value: '10', group: 'relances', label: null },
    { id: 2, key: 'relance_niveau2_jours', value: '25', group: 'relances', label: null },
    { id: 3, key: 'relance_template_n1', value: 'Bonjour {{client}}, rappel facture {{numero_facture}}', group: 'relances', label: null },
    { id: 4, key: 'relance_template_n2', value: 'Relance ferme pour {{montant}}', group: 'relances', label: null },
  ]
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: makeSettings() })
  mockUpdate.mockResolvedValue(undefined)
})

describe('RelancesConfigPage — chargement', () => {
  it('affiche "Chargement..." pendant la requete puis le formulaire', async () => {
    const { wrapper } = await mountPage(RelancesConfigPage, { skipFlush: true })

    expect(wrapper.text()).toContain('Chargement...')
    expect(wrapper.find('form').exists()).toBe(false)

    await flushPromises()

    expect(mockGetAll).toHaveBeenCalledOnce()
    expect(wrapper.text()).toContain('Configuration des relances')
    expect(wrapper.text()).toContain('Délais de déclenchement')
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('expose les delais charges et retombe sur 45 jours pour le niveau 3 absent', async () => {
    const { wrapper } = await mountPage(RelancesConfigPage)
    const vm = wrapper.vm as any

    expect(vm.jours1).toBe(10)
    expect(vm.jours2).toBe(25)
    expect(vm.jours3).toBe(45) // valeur par defaut, cle absente
    expect(vm.template1).toContain('{{client}}')
    expect(vm.template2).toContain('{{montant}}')
    expect(vm.template3).toBe('') // cle absente
  })

  it('affiche les variables disponibles et les templates dans les onglets', async () => {
    const { wrapper } = await mountPage(RelancesConfigPage)

    expect(wrapper.text()).toContain('{{client}}, {{montant}}, {{numero_facture}}, {{echeance}}')
    const tpl1 = wrapper.find('#tpl1')
    expect(tpl1.exists()).toBe(true)
    expect((tpl1.element as HTMLTextAreaElement).value).toContain('rappel facture')
  })

  it('affiche un toast erreur si le chargement echoue et garde les delais par defaut', async () => {
    mockGetAll.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountPage(RelancesConfigPage)
    const vm = wrapper.vm as any

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les paramètres' }),
    )
    expect(vm.jours1).toBe(15)
    expect(vm.jours2).toBe(30)
    expect(vm.jours3).toBe(45)
  })
})

describe('RelancesConfigPage — sauvegarde', () => {
  it('modifie delais et templates puis envoie tout le payload au submit', async () => {
    const { wrapper } = await mountPage(RelancesConfigPage)
    const vm = wrapper.vm as any

    vm.jours1 = 20
    vm.jours2 = 35
    vm.template1 = 'Nouveau rappel {{client}}'
    vm.template2 = 'Nouvelle relance ferme'
    // cles absentes -> setters no-op, ne doivent pas planter
    vm.jours3 = 99
    vm.template3 = 'jamais persiste'
    await flushPromises()

    expect(vm.jours1).toBe(20)
    expect(vm.jours3).toBe(45) // toujours la valeur par defaut : cle absente

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockUpdate).toHaveBeenCalledOnce()
    const payload = mockUpdate.mock.calls[0][0]
    expect(payload).toEqual(
      expect.arrayContaining([
        { key: 'relance_niveau1_jours', value: '20' },
        { key: 'relance_niveau2_jours', value: '35' },
        { key: 'relance_template_n1', value: 'Nouveau rappel {{client}}' },
        { key: 'relance_template_n2', value: 'Nouvelle relance ferme' },
      ]),
    )
    expect(payload).toHaveLength(4)
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', detail: 'Paramètres de relance mis à jour' }),
    )
  })

  it('soumet via le bouton Enregistrer accessible (aria-label)', async () => {
    const { wrapper } = await mountPage(RelancesConfigPage)

    const bouton = findButton(wrapper, 'Enregistrer la configuration des relances')
    expect(bouton).toBeTruthy()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockUpdate).toHaveBeenCalledOnce()
  })

  it('affiche un toast erreur si la sauvegarde echoue', async () => {
    mockUpdate.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountPage(RelancesConfigPage)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de sauvegarder' }),
    )
    expect((wrapper.vm as any).saving).toBe(false)
  })
})
