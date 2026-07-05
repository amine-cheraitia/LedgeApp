import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Contact, Entreprise } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const {
  mockGetAll, mockCreate, mockUpdate, mockDelete,
  mockWilayas, mockExportCsv, mockActiverPortail, mockRenvoyerInvitation, mockTogglePortail,
  mockContactsGetAll, mockContactCreate, mockContactUpdate, mockContactDelete,
} = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
  mockCreate: vi.fn(),
  mockUpdate: vi.fn(),
  mockDelete: vi.fn(),
  mockWilayas: vi.fn(),
  mockExportCsv: vi.fn(),
  mockActiverPortail: vi.fn(),
  mockRenvoyerInvitation: vi.fn(),
  mockTogglePortail: vi.fn(),
  mockContactsGetAll: vi.fn(),
  mockContactCreate: vi.fn(),
  mockContactUpdate: vi.fn(),
  mockContactDelete: vi.fn(),
}))

vi.mock('@/api/modules/entreprises', () => ({
  entreprisesApi: {
    getAll: mockGetAll,
    create: mockCreate,
    update: mockUpdate,
    delete: mockDelete,
    wilayas: mockWilayas,
    exportCsv: mockExportCsv,
    activerPortail: mockActiverPortail,
    renvoyerInvitation: mockRenvoyerInvitation,
    togglePortail: mockTogglePortail,
  },
}))

vi.mock('@/api/modules/contacts', () => ({
  contactsApi: {
    getAll: mockContactsGetAll,
    create: mockContactCreate,
    update: mockContactUpdate,
    delete: mockContactDelete,
  },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

const confirmRequire = vi.fn()
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import EntrepriseListPage from '@/pages/entreprises/EntrepriseListPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
function makeEntreprise(overrides: Partial<Entreprise> = {}): Entreprise {
  return {
    id: 1,
    raison_sociale: 'Alpha SARL',
    nif: '00123456789',
    nis: null,
    num_rc: null,
    article_imposition: null,
    regime_fiscal: 'forfait',
    categorie: 'TPE',
    secteur_activite: null,
    adresse: null,
    ville: null,
    wilaya: 'Alger',
    telephone: null,
    email: 'alpha@ledge.dz',
    contact_principal: 'Ali Alpha',
    statut: 'prospect',
    notes: null,
    portail_user: null,
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

const entreprises: Entreprise[] = [
  makeEntreprise(),
  makeEntreprise({
    id: 2, raison_sociale: 'Beta SPA', statut: 'client', email: 'beta@ledge.dz',
    portail_user: { id: 10, name: 'Beta User', email: 'beta@ledge.dz', portail_actif: true },
  }),
  makeEntreprise({
    id: 3, raison_sociale: 'Gamma EURL', statut: 'client', email: null,
    portail_user: { id: 11, name: 'Gamma User', email: 'gamma@ledge.dz', portail_actif: false },
  }),
  makeEntreprise({
    id: 4, raison_sociale: 'Delta SNC', statut: 'client', email: 'delta@ledge.dz',
    contact_principal: 'Dalia Delta', portail_user: null,
  }),
  makeEntreprise({ id: 5, raison_sociale: 'Omega SARL', statut: 'ancien_client' }),
]

const contacts: Contact[] = [
  {
    id: 1, entreprise_id: 1, nom: 'Karim', prenom: 'Bensaid', email: 'karim@ledge.dz',
    telephone: '0550 11 22 33', poste: 'DG', est_principal: true, created_at: '', updated_at: '',
  },
  {
    id: 2, entreprise_id: 1, nom: 'Sara', prenom: null, email: null,
    telephone: null, poste: null, est_principal: false, created_at: '', updated_at: '',
  },
]

const clipboardWrite = vi.fn()

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: entreprises, meta: { total: entreprises.length } })
  mockWilayas.mockResolvedValue({ data: ['Alger', 'Oran'] })
  mockContactsGetAll.mockResolvedValue({ data: contacts })
  Object.defineProperty(globalThis.navigator, 'clipboard', {
    value: { writeText: clipboardWrite },
    configurable: true,
  })
})

// ── Liste ────────────────────────────────────────────────────────────────────
describe('EntrepriseListPage — liste', () => {
  it('charge les entreprises et les wilayas au montage', async () => {
    const { wrapper } = await mountPage(EntrepriseListPage)

    expect(mockGetAll).toHaveBeenCalledOnce()
    expect(mockWilayas).toHaveBeenCalledOnce()
    expect(wrapper.text()).toContain('Alpha SARL')
    expect(wrapper.text()).toContain('Beta SPA')
    expect(wrapper.text()).toContain('Omega SARL')
    expect(wrapper.text()).toContain('prospect')
    expect(wrapper.text()).toContain('ancien_client')
  })

  it('affiche la colonne Portail pour l admin : actif, desactive, activer, — pour prospect', async () => {
    const { wrapper } = await mountPage(EntrepriseListPage)

    expect(wrapper.text()).toContain('Portail')
    expect(wrapper.text()).toContain('Actif')       // Beta : portail actif
    expect(wrapper.text()).toContain('Desactive')   // Gamma : portail coupe
    expect(findButton(wrapper, 'Activer')).toBeDefined() // Delta : pas encore de compte
    expect(wrapper.text()).toContain('—')           // Alpha : prospect, pas de portail
  })

  it('statutColor retombe sur secondary pour un statut inconnu', async () => {
    const { wrapper } = await mountPage(EntrepriseListPage)

    expect((wrapper.vm as any).statutColor('inconnu')).toBe('secondary')
    expect((wrapper.vm as any).statutColor('client')).toBe('success')
    expect((wrapper.vm as any).statutColor('prospect')).toBe('warn')
  })

  it('reste silencieux si le chargement des wilayas echoue', async () => {
    mockWilayas.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountPage(EntrepriseListPage)

    expect(wrapper.text()).toContain('Entreprises')
    expect((wrapper.vm as any).wilayas).toEqual([])
  })
})

// ── Filtres ──────────────────────────────────────────────────────────────────
describe('EntrepriseListPage — filtres', () => {
  it('relance la recherche quand on tape dans le champ', async () => {
    const { wrapper } = await mountPage(EntrepriseListPage)

    await wrapper.find('#search-entreprises').setValue('alpha')
    await flushPromises()

    expect(mockGetAll).toHaveBeenLastCalledWith(expect.objectContaining({ search: 'alpha', page: 1 }))
  })

  it('filtre par statut et wilaya puis reinitialise', async () => {
    const { wrapper } = await mountPage(EntrepriseListPage)
    const vm = wrapper.vm as any

    vm.filtreStatut = 'client'
    await flushPromises()
    expect(mockGetAll).toHaveBeenLastCalledWith(expect.objectContaining({ statut: 'client', page: 1 }))

    vm.filtreWilaya = 'Alger'
    await flushPromises()
    expect(mockGetAll).toHaveBeenLastCalledWith(expect.objectContaining({ wilaya: 'Alger', page: 1 }))

    // Le bouton Reinitialiser apparait quand un filtre est actif
    const resetBtn = findButton(wrapper, 'Reinitialiser')
    expect(resetBtn).toBeDefined()
    await resetBtn!.trigger('click')
    await flushPromises()

    expect(vm.filtreStatut).toBeNull()
    expect(vm.filtreWilaya).toBeNull()
    expect(vm.search).toBe('')
    expect(mockGetAll).toHaveBeenLastCalledWith(expect.objectContaining({ page: 1, per_page: 15 }))
  })
})

// ── Export CSV ───────────────────────────────────────────────────────────────
describe('EntrepriseListPage — export CSV', () => {
  it('telecharge le CSV avec les filtres courants', async () => {
    mockExportCsv.mockResolvedValue(new Blob(['csv'], { type: 'text/csv' }))
    const createObjectURL = vi.fn(() => 'blob:mock-url')
    const revokeObjectURL = vi.fn()
    URL.createObjectURL = createObjectURL as any
    URL.revokeObjectURL = revokeObjectURL as any
    const anchorClick = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

    const { wrapper } = await mountPage(EntrepriseListPage)
    await findButton(wrapper, 'Export CSV')!.trigger('click')
    await flushPromises()

    expect(mockExportCsv).toHaveBeenCalledWith({ search: undefined, statut: undefined, wilaya: undefined })
    expect(createObjectURL).toHaveBeenCalledOnce()
    expect(anchorClick).toHaveBeenCalledOnce()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:mock-url')
    expect((wrapper.vm as any).exportLoading).toBe(false)
    anchorClick.mockRestore()
  })

  it("affiche un toast d'erreur si l'export echoue", async () => {
    mockExportCsv.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, 'Export CSV')!.trigger('click')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
      severity: 'error',
      detail: "Impossible d'exporter.",
    }))
    expect((wrapper.vm as any).exportLoading).toBe(false)
  })
})

// ── Creation / edition ───────────────────────────────────────────────────────
describe('EntrepriseListPage — creation / edition', () => {
  it('ouvre le dialog de creation et soumet le formulaire', async () => {
    mockCreate.mockResolvedValue({ data: { id: 6 } })
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, 'Nouvelle entreprise')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Nouvelle entreprise')

    await wrapper.find('#ent-raison').setValue('Nouvelle SARL')
    await wrapper.find('#ent-email').setValue('new@ledge.dz')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalledWith(expect.objectContaining({
      raison_sociale: 'Nouvelle SARL',
      email: 'new@ledge.dz',
      statut: 'prospect',
      regime_fiscal: 'forfait',
    }))
    expect(mockGetAll).toHaveBeenCalledTimes(2) // refetch apres creation
    expect(wrapper.find('#ent-raison').exists()).toBe(false) // dialog ferme
  })

  it("laisse le dialog ouvert si l'API rejette la creation", async () => {
    mockCreate.mockRejectedValue(new Error('422'))
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, 'Nouvelle entreprise')!.trigger('click')
    await wrapper.find('#ent-raison').setValue('X')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalled()
    expect(wrapper.find('#ent-raison').exists()).toBe(true)
    expect((wrapper.vm as any).saving).toBe(false)
  })

  it('ouvre le dialog pre-rempli en edition et enregistre', async () => {
    mockUpdate.mockResolvedValue({ data: entreprises[0] })
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, "Modifier l'entreprise")!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain("Modifier l'entreprise")

    const raisonInput = wrapper.find('#ent-raison')
    expect((raisonInput.element as HTMLInputElement).value).toBe('Alpha SARL')

    await raisonInput.setValue('Alpha SARL Renommee')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockUpdate).toHaveBeenCalledWith(1, expect.objectContaining({ raison_sociale: 'Alpha SARL Renommee' }))
    expect(mockGetAll).toHaveBeenCalledTimes(2)
  })
})

// ── Suppression ──────────────────────────────────────────────────────────────
describe('EntrepriseListPage — suppression', () => {
  it('demande confirmation puis supprime au accept (admin)', async () => {
    mockDelete.mockResolvedValue(undefined)
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, "Supprimer l'entreprise")!.trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('Alpha SARL')

    await config.accept()
    await flushPromises()

    expect(mockDelete).toHaveBeenCalledWith(1)
    expect(mockGetAll).toHaveBeenCalledTimes(2)
  })
})

// ── Roles (secretaire) ───────────────────────────────────────────────────────
describe('EntrepriseListPage — role secretaire', () => {
  it('masque la suppression et la colonne portail pour la secretaire', async () => {
    const { wrapper } = await mountPage(EntrepriseListPage, { role: 'secretaire' })

    expect(findButton(wrapper, "Supprimer l'entreprise")).toBeUndefined()
    expect(wrapper.text()).not.toContain('Portail')
    expect(findButton(wrapper, 'Activer')).toBeUndefined()
    // Mais la secretaire garde le CRUD sans suppression
    expect(findButton(wrapper, 'Nouvelle entreprise')).toBeDefined()
    expect(findButton(wrapper, "Modifier l'entreprise")).toBeDefined()
  })
})

// ── Portail client ───────────────────────────────────────────────────────────
describe('EntrepriseListPage — activation portail', () => {
  it('active le portail : formulaire pre-rempli, toast, invitation et copie du lien', async () => {
    mockActiverPortail.mockResolvedValue({
      message: 'Invitation envoyee.',
      invitation_url: 'http://ledge.test/invitation/abc123',
    })
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, 'Activer')!.trigger('click') // Delta SNC
    await flushPromises()

    expect(wrapper.text()).toContain("Activer l'acces portail")
    expect(wrapper.text()).toContain('Delta SNC')
    expect((wrapper.find('#portail-name').element as HTMLInputElement).value).toBe('Dalia Delta')
    expect((wrapper.find('#portail-email').element as HTMLInputElement).value).toBe('delta@ledge.dz')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockActiverPortail).toHaveBeenCalledWith(4, { name: 'Dalia Delta', email: 'delta@ledge.dz' })
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Invitation envoyee.' }))
    expect(mockGetAll).toHaveBeenCalledTimes(2)

    // Dialog invitation : lien de repli affiche puis copiable
    expect(wrapper.text()).toContain('http://ledge.test/invitation/abc123')
    expect(wrapper.text()).toContain('delta@ledge.dz')
    await findButton(wrapper, 'Copier le lien')!.trigger('click')
    expect(clipboardWrite).toHaveBeenCalledWith('http://ledge.test/invitation/abc123')
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'info' }))
  })

  it('pre-remplit a vide sans contact principal ni email, et garde le retour anticipe', async () => {
    const { wrapper } = await mountPage(EntrepriseListPage)
    const vm = wrapper.vm as any

    // Garde : pas d'entreprise selectionnee -> aucune requete
    await vm.onActiverPortail()
    expect(mockActiverPortail).not.toHaveBeenCalled()

    vm.openPortailActivation(makeEntreprise({ id: 9, contact_principal: null, email: null }))
    expect(vm.portailForm).toEqual({ name: '', email: '' })
  })

  it("affiche le message d'erreur API ou un message generique en cas d'echec", async () => {
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, 'Activer')!.trigger('click')
    await flushPromises()

    mockActiverPortail.mockRejectedValueOnce({ response: { data: { message: 'Email deja utilise.' } } })
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error', detail: 'Email deja utilise.' }))
    expect(wrapper.find('#portail-name').exists()).toBe(true) // dialog reste ouvert

    mockActiverPortail.mockRejectedValueOnce(new Error('reseau'))
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error', detail: "Erreur lors de l'activation." }))
  })
})
