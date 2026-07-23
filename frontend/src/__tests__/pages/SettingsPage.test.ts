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
import SettingsPage from '@/pages/settings/SettingsPage.vue'
import { mountPage, findButton } from '../helpers/mount'

const settings: Setting[] = [
  { id: 1, key: 'facture_prefixe', value: 'FF', group: 'facturation', label: 'Prefixe facture' },
  { id: 2, key: 'devis_prefixe', value: 'DV', group: 'facturation', label: null },
]

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: settings.map(s => ({ ...s })) })
})

describe('SettingsPage — chargement', () => {
  it('affiche l indicateur de chargement pendant le fetch', async () => {
    mockGetAll.mockReturnValue(new Promise(() => {})) // jamais resolue
    const { wrapper } = await mountPage(SettingsPage, { skipFlush: true })
    await Promise.resolve()

    expect(wrapper.text()).toContain('Chargement...')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('affiche chaque parametre avec son label (ou sa cle en repli) et sa valeur', async () => {
    const { wrapper } = await mountPage(SettingsPage)

    expect(mockGetAll).toHaveBeenCalledOnce()
    // Label present
    expect(wrapper.text()).toContain('Prefixe facture')
    // Label null -> repli sur la cle
    expect(wrapper.text()).toContain('devis_prefixe')
    // Chaque input est associe a un label (RGAA)
    const input = wrapper.find('#setting-facture_prefixe')
    expect((input.element as HTMLInputElement).value).toBe('FF')
    expect(wrapper.find('label[for="setting-facture_prefixe"]').exists()).toBe(true)
  })
})

describe('SettingsPage — enregistrement', () => {
  it('soumet toutes les paires cle/valeur (avec la valeur modifiee) et affiche un toast succes', async () => {
    mockUpdate.mockResolvedValue(undefined)
    const { wrapper } = await mountPage(SettingsPage)

    await wrapper.find('#setting-facture_prefixe').setValue('FX')
    await findButton(wrapper, 'Enregistrer')!.trigger('click')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockUpdate).toHaveBeenCalledWith([
      { key: 'facture_prefixe', value: 'FX' },
      { key: 'devis_prefixe', value: 'DV' },
    ])
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }))
  })

  it('affiche un toast erreur si la sauvegarde echoue', async () => {
    mockUpdate.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountPage(SettingsPage)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }))
  })
})
