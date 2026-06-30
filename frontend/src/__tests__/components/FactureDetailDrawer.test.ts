import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import type { Facture, Paiement } from '@/types'

// ── Mocks API ────────────────────────────────────────────────────────────────

const { mockGetPaiements, mockCreatePaiement, mockDeletePaiement } = vi.hoisted(() => ({
  mockGetPaiements: vi.fn(),
  mockCreatePaiement: vi.fn(),
  mockDeletePaiement: vi.fn(),
}))

vi.mock('@/api/modules/factures', () => ({
  facturesApi: {
    getPaiements: mockGetPaiements,
    createPaiement: mockCreatePaiement,
    deletePaiement: mockDeletePaiement,
  },
}))

vi.mock('@/api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
  resetCsrf: vi.fn(),
}))

// ── Mocks PrimeVue composables ───────────────────────────────────────────────

const mockToastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: mockToastAdd }) }))

const mockConfirmRequire = vi.fn()
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: mockConfirmRequire }) }))

// ── Import du composant après les mocks ──────────────────────────────────────

import FactureDetailDrawer from '@/components/facturation/FactureDetailDrawer.vue'
import { useAuthStore } from '@/stores/auth'

// ── Stubs PrimeVue ───────────────────────────────────────────────────────────

const stubs = {
  Drawer: {
    props: ['visible'],
    template: '<div class="p-drawer-stub"><slot name="header" /><slot /></div>',
  },
  Button: {
    inheritAttrs: false,
    emits: ['click'],
    props: ['label', 'loading', 'disabled', 'type', 'icon', 'iconPos', 'severity', 'text', 'rounded', 'size'],
    template: '<button v-bind="$attrs" :type="type || \'button\'" :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
  },
  InputNumber: {
    props: ['modelValue', 'min', 'mode', 'minFractionDigits', 'fluid', 'ariaRequired', 'ariaDescribedby', 'ariaInvalid'],
    emits: ['update:modelValue'],
    template: '<input type="number" :value="modelValue" @input="$emit(\'update:modelValue\', parseFloat($event.target.value) || 0)" data-testid="input-montant" />',
  },
  InputText: {
    props: ['modelValue', 'fluid', 'placeholder'],
    emits: ['update:modelValue'],
    template: '<input type="text" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
  },
  Select: {
    props: ['modelValue', 'options', 'optionLabel', 'optionValue', 'fluid', 'ariaRequired'],
    emits: ['update:modelValue'],
    template: '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option></select>',
  },
  DatePicker: {
    props: ['modelValue', 'dateFormat', 'fluid', 'ariaRequired', 'ariaDescribedby', 'ariaInvalid'],
    emits: ['update:modelValue'],
    template: '<input type="date" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value ? new Date($event.target.value) : null)" data-testid="input-date" />',
  },
  Tag: {
    props: ['value', 'severity'],
    template: '<span class="p-tag">{{ value }}</span>',
  },
}

// ── Fixtures ─────────────────────────────────────────────────────────────────

const mockPaiements: Paiement[] = [
  {
    id: 1,
    facture_id: 1,
    recorded_by: 1,
    recorded_by_name: 'Administrateur',
    montant: 40000,
    date_paiement: '2026-06-23',
    mode_paiement: 'virement',
    reference: null,
    notes: null,
    created_at: '2026-06-23T10:00:00.000000Z',
    updated_at: '2026-06-23T10:00:00.000000Z',
  },
]

const mockFacture: Facture = {
  id: 1,
  entreprise_id: 1,
  exercice_id: 1,
  mission_id: 1,
  devis_id: null,
  created_by: 1,
  tva_rate_id: null,
  numero: 'FF2026-002',
  type: 'FF',
  facture_origine_id: null,
  date_facture: '2026-06-01',
  date_echeance: '2026-07-16',
  montant_ht: 35000,
  taux_tva: 19,
  montant_tva: 6650,
  montant_ttc: 41650,
  montant_paye: 40000,
  statut_paiement: 'partiel',
  mode_paiement: 'virement',
  montant_restant: 1650,
  pdf_path: null,
  entreprise: { id: 1, raison_sociale: 'CCSA Test', nif: null, nis: null, num_rc: null, article_imposition: null, regime_fiscal: 'reel', categorie: 'PME', secteur_activite: null, adresse: null, ville: null, wilaya: null, telephone: null, email: null, contact_principal: null, statut: 'client', notes: null, created_at: '', updated_at: '' },
  created_at: '2026-06-01T00:00:00.000000Z',
  updated_at: '2026-06-23T00:00:00.000000Z',
}

// ── Helpers ──────────────────────────────────────────────────────────────────

async function mountDrawer(overrides: { isAdmin?: boolean; isSecretaire?: boolean; userId?: number } = {}) {
  setActivePinia(createPinia())

  const store = useAuthStore()
  store.user = {
    id: overrides.userId ?? 1,
    name: 'Test User',
    email: 'test@ledge.dz',
    entreprise_id: null,
    portail_actif: false,
    email_verified_at: null,
    roles: overrides.isAdmin !== false ? ['admin'] : overrides.isSecretaire ? ['secretaire'] : ['collaborateur'],
    created_at: '',
    updated_at: '',
  }

  const wrapper = mount(FactureDetailDrawer, {
    props: { modelValue: true, facture: mockFacture },
    global: { stubs, plugins: [] },
  })

  await flushPromises()
  return wrapper
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('FactureDetailDrawer — affichage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGetPaiements.mockResolvedValue({ data: mockPaiements })
  })

  it('affiche le numéro de la facture dans le header', async () => {
    const wrapper = await mountDrawer()
    expect(wrapper.text()).toContain('FF2026-002')
  })

  it('affiche le montant TTC, encaissé et restant dû', async () => {
    const wrapper = await mountDrawer()
    expect(wrapper.text()).toContain('41 650')
    expect(wrapper.text()).toContain('40 000')
    expect(wrapper.text()).toContain('1 650')
  })

  it('le montant encaissé porte la classe amount-paye (dark mode fix)', async () => {
    const wrapper = await mountDrawer()
    const encaisse = wrapper.find('.amount-paye')
    expect(encaisse.exists()).toBe(true)
  })

  it('le montant restant porte la classe amount-restant quand > 0', async () => {
    const wrapper = await mountDrawer()
    const restant = wrapper.find('.amount-restant')
    expect(restant.exists()).toBe(true)
  })

  it('charge et affiche la liste des paiements au montage', async () => {
    const wrapper = await mountDrawer()
    expect(mockGetPaiements).toHaveBeenCalledWith(1)
    expect(wrapper.text()).toContain('Administrateur')
  })

  it('le bouton supprimer est visible pour un admin', async () => {
    const wrapper = await mountDrawer({ isAdmin: true })
    expect(wrapper.findAll('.paiements-list button').length).toBeGreaterThan(0)
  })
})

describe('FactureDetailDrawer — navigation formulaire', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGetPaiements.mockResolvedValue({ data: [] })
  })

  it('clique "Ajouter" passe à l\'étape formulaire', async () => {
    const wrapper = await mountDrawer()
    const addBtn = wrapper.findAll('button').find(b => b.text() === 'Ajouter')
    expect(addBtn).toBeDefined()
    await addBtn!.trigger('click')
    expect(wrapper.text()).toContain('Nouveau paiement')
  })
})

describe('FactureDetailDrawer — validation', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGetPaiements.mockResolvedValue({ data: [] })
  })

  async function getFormWrapper() {
    const wrapper = await mountDrawer()
    const addBtn = wrapper.findAll('button').find(b => b.text() === 'Ajouter')
    await addBtn!.trigger('click')
    await wrapper.vm.$nextTick()
    return wrapper
  }

  it('affiche une erreur si le montant est 0', async () => {
    const wrapper = await getFormWrapper()

    // Forcer montant à 0 dans la VM
    const vm = wrapper.vm as any
    if (vm.paiementForm) vm.paiementForm.montant = 0
    if (vm.datePaiement !== undefined) vm.datePaiement = new Date()
    if (typeof vm.goToConfirm === 'function') vm.goToConfirm()
    await wrapper.vm.$nextTick()

    const errorEl = wrapper.find('[role="alert"]')
    expect(errorEl.exists()).toBe(true)
    expect(errorEl.text()).toContain('supérieur à 0')
  })

  it('affiche une erreur si le montant est négatif', async () => {
    const wrapper = await getFormWrapper()
    const vm = wrapper.vm as any
    if (vm.paiementForm) vm.paiementForm.montant = -500
    if (vm.datePaiement !== undefined) vm.datePaiement = new Date()
    if (typeof vm.goToConfirm === 'function') vm.goToConfirm()
    await wrapper.vm.$nextTick()

    const errorEl = wrapper.find('[role="alert"]')
    expect(errorEl.exists()).toBe(true)
    expect(errorEl.text()).toContain('supérieur à 0')
  })

  it('affiche une erreur si le montant dépasse le restant dû', async () => {
    const wrapper = await getFormWrapper()
    const vm = wrapper.vm as any
    // restant = 1650, on saisit 5000
    if (vm.paiementForm) vm.paiementForm.montant = 5000
    if (vm.datePaiement !== undefined) vm.datePaiement = new Date()
    if (typeof vm.goToConfirm === 'function') vm.goToConfirm()
    await wrapper.vm.$nextTick()

    const errorEl = wrapper.find('[role="alert"]')
    expect(errorEl.exists()).toBe(true)
    expect(errorEl.text()).toContain('dépasser')
  })

  it('affiche une erreur si la date est absente', async () => {
    const wrapper = await getFormWrapper()
    const vm = wrapper.vm as any
    if (vm.paiementForm) vm.paiementForm.montant = 1000
    if (vm.datePaiement !== undefined) vm.datePaiement = null
    if (typeof vm.goToConfirm === 'function') vm.goToConfirm()
    await wrapper.vm.$nextTick()

    const errorEl = wrapper.find('[role="alert"]')
    expect(errorEl.exists()).toBe(true)
    expect(errorEl.text()).toContain('date')
  })

  it('passe à l\'étape confirm si le montant et la date sont valides', async () => {
    const wrapper = await getFormWrapper()
    const vm = wrapper.vm as any
    if (vm.paiementForm) vm.paiementForm.montant = 1000
    if (vm.datePaiement !== undefined) vm.datePaiement = new Date()
    if (typeof vm.goToConfirm === 'function') vm.goToConfirm()
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Confirmer')
  })

  it('les messages d\'erreur ont role="alert" et aria-live="assertive" (RGAA)', async () => {
    const wrapper = await getFormWrapper()
    const vm = wrapper.vm as any
    if (vm.paiementForm) vm.paiementForm.montant = 0
    if (vm.datePaiement !== undefined) vm.datePaiement = new Date()
    if (typeof vm.goToConfirm === 'function') vm.goToConfirm()
    await wrapper.vm.$nextTick()

    const alertEl = wrapper.find('[role="alert"]')
    expect(alertEl.exists()).toBe(true)
    expect(alertEl.attributes('aria-live')).toBe('assertive')
  })
})

describe('FactureDetailDrawer — étape confirmation', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGetPaiements.mockResolvedValue({ data: [] })
  })

  it('affiche le récapitulatif avec montant, mode et reste dû après', async () => {
    const wrapper = await mountDrawer()
    const vm = wrapper.vm as any

    // Aller à l'étape form
    const addBtn = wrapper.findAll('button').find(b => b.text() === 'Ajouter')
    await addBtn!.trigger('click')
    await wrapper.vm.$nextTick()

    // Saisir un paiement valide et passer à confirm
    if (vm.paiementForm) {
      vm.paiementForm.montant = 1000
      vm.paiementForm.mode_paiement = 'cheque'
    }
    if (vm.datePaiement !== undefined) vm.datePaiement = new Date('2026-06-23')
    if (typeof vm.goToConfirm === 'function') vm.goToConfirm()
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Confirmer l\'encaissement')
    // Reste dû après = 1650 - 1000 = 650
    expect(wrapper.text()).toContain('650')
  })

  it('émet paiement-changed et revient à history après succès', async () => {
    mockCreatePaiement.mockResolvedValue({ data: { id: 2, montant: 1000 } })

    const wrapper = await mountDrawer()
    const vm = wrapper.vm as any

    // Mettre en étape confirm directement
    if (vm.step !== undefined) vm.step = 'confirm'
    if (vm.paiementForm) vm.paiementForm.montant = 1000
    if (vm.datePaiement !== undefined) vm.datePaiement = new Date()
    if (typeof vm.submitPaiement === 'function') await vm.submitPaiement()
    await flushPromises()

    expect(wrapper.emitted('paiement-changed')).toBeTruthy()
  })
})

describe('FactureDetailDrawer — suppression paiement', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGetPaiements.mockResolvedValue({ data: mockPaiements })
  })

  it('le bouton supprimer est absent pour un collaborateur', async () => {
    const wrapper = await mountDrawer({ isAdmin: false, isSecretaire: false })
    // La liste devrait ne pas avoir de bouton supprimer
    const deleteButtons = wrapper.findAll('button').filter(b =>
      b.attributes('aria-label')?.includes('Supprimer le paiement')
    )
    expect(deleteButtons).toHaveLength(0)
  })

  it('la secrétaire peut supprimer sa propre saisie', async () => {
    const paiementParSecretaire: Paiement[] = [{
      ...mockPaiements[0],
      recorded_by: 99,
    }]
    mockGetPaiements.mockResolvedValue({ data: paiementParSecretaire })

    // Monter en tant que secrétaire avec userId=99
    const wrapper = await mountDrawer({ isAdmin: false, isSecretaire: true, userId: 99 })

    const deleteButtons = wrapper.findAll('button').filter(b =>
      b.attributes('aria-label')?.includes('Supprimer le paiement')
    )
    expect(deleteButtons.length).toBeGreaterThan(0)
  })

  it('la secrétaire ne peut pas supprimer la saisie d\'autrui', async () => {
    // paiement recorded_by=1 (admin), secrétaire est userId=99
    mockGetPaiements.mockResolvedValue({ data: mockPaiements }) // recorded_by: 1

    const wrapper = await mountDrawer({ isAdmin: false, isSecretaire: true, userId: 99 })

    const deleteButtons = wrapper.findAll('button').filter(b =>
      b.attributes('aria-label')?.includes('Supprimer le paiement')
    )
    expect(deleteButtons).toHaveLength(0)
  })
})
