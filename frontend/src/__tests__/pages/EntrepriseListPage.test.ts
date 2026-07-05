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

describe('EntrepriseListPage — verrouillage / reactivation portail', () => {
  it('verrouiller un acces actif demande confirmation puis appelle le toggle', async () => {
    mockTogglePortail.mockResolvedValue({ message: 'Acces verrouille.' })
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, "Verrouiller l'acces de Beta SPA")!.trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('Beta SPA')

    await config.accept()
    await flushPromises()

    expect(mockTogglePortail).toHaveBeenCalledWith(2)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Acces verrouille.' }))
    expect(mockGetAll).toHaveBeenCalledTimes(2)
  })

  it('reactiver un acces coupe ne demande pas de confirmation', async () => {
    mockTogglePortail.mockResolvedValue({ message: 'Acces reactive.' })
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, "Reactiver l'acces de Gamma EURL")!.trigger('click')
    await flushPromises()

    expect(confirmRequire).not.toHaveBeenCalled()
    expect(mockTogglePortail).toHaveBeenCalledWith(3)
  })

  it("affiche un toast d'erreur si le toggle echoue", async () => {
    mockTogglePortail.mockRejectedValue({ response: { data: { message: 'Action interdite.' } } })
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, "Reactiver l'acces de Gamma EURL")!.trigger('click')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error', detail: 'Action interdite.' }))
  })
})

describe('EntrepriseListPage — renvoi invitation', () => {
  it("renvoie l'invitation apres confirmation et affiche le lien de repli", async () => {
    mockRenvoyerInvitation.mockResolvedValue({
      message: 'Invitation renvoyee.',
      invitation_url: 'http://ledge.test/invitation/xyz789',
    })
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, "Renvoyer l'invitation a Beta SPA")!.trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('beta@ledge.dz')

    await config.accept()
    await flushPromises()

    expect(mockRenvoyerInvitation).toHaveBeenCalledWith(2)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Invitation renvoyee.' }))
    expect(wrapper.text()).toContain('http://ledge.test/invitation/xyz789')
  })

  it("utilise le message de repli sans email et gere l'erreur d'envoi", async () => {
    mockRenvoyerInvitation.mockRejectedValue(new Error('boom'))
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, "Renvoyer l'invitation a Gamma EURL")!.trigger('click')

    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain("l'adresse du client") // Gamma n'a pas d'email

    await config.accept()
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
      severity: 'error',
      detail: "Erreur lors de l'envoi de l'invitation.",
    }))
  })
})

// ── Contacts ─────────────────────────────────────────────────────────────────
describe('EntrepriseListPage — contacts', () => {
  it('ouvre le dialog contacts et liste les contacts de l entreprise', async () => {
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, 'Gerer les contacts')!.trigger('click')
    await flushPromises()

    expect(mockContactsGetAll).toHaveBeenCalledWith(1)
    expect(wrapper.text()).toContain('Contacts — Alpha SARL')
    expect(wrapper.text()).toContain('2 contacts')
    expect(wrapper.text()).toContain('Karim Bensaid')
    expect(wrapper.text()).toContain('Principal') // tag contact principal
    expect(wrapper.text()).toContain('Sara')
  })

  it("affiche l'etat vide quand il n'y a aucun contact", async () => {
    mockContactsGetAll.mockResolvedValue({ data: [] })
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, 'Gerer les contacts')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('0 contact')
    expect(wrapper.text()).toContain('Aucun contact')
  })

  it('cree un contact via le formulaire', async () => {
    mockContactCreate.mockResolvedValue({ data: { id: 3 } })
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, 'Gerer les contacts')!.trigger('click')
    await flushPromises()
    await findButton(wrapper, 'Ajouter un contact')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Nouveau contact')

    await wrapper.find('#c-nom').setValue('Nadia')
    await wrapper.find('#c-email').setValue('nadia@ledge.dz')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockContactCreate).toHaveBeenCalledWith(1, expect.objectContaining({ nom: 'Nadia', email: 'nadia@ledge.dz' }))
    expect(mockContactsGetAll).toHaveBeenCalledTimes(2) // refetch apres creation
    expect(wrapper.find('#c-nom').exists()).toBe(false) // formulaire ferme
  })

  it('modifie un contact avec le formulaire pre-rempli', async () => {
    mockContactUpdate.mockResolvedValue({ data: contacts[0] })
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, 'Gerer les contacts')!.trigger('click')
    await flushPromises()
    await findButton(wrapper, 'Modifier le contact')!.trigger('click')
    await flushPromises()

    const nomInput = wrapper.find('#c-nom')
    expect((nomInput.element as HTMLInputElement).value).toBe('Karim')

    await nomInput.setValue('Karim Modifie')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockContactUpdate).toHaveBeenCalledWith(1, 1, expect.objectContaining({ nom: 'Karim Modifie' }))
  })

  it('supprime un contact apres confirmation (avec et sans prenom)', async () => {
    mockContactDelete.mockResolvedValue(undefined)
    const { wrapper } = await mountPage(EntrepriseListPage)

    await findButton(wrapper, 'Gerer les contacts')!.trigger('click')
    await flushPromises()

    const deleteButtons = () => wrapper.findAll('button').filter(
      b => b.attributes('aria-label') === 'Supprimer le contact',
    )

    await deleteButtons()[0].trigger('click')
    let config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('Karim Bensaid') // nom + prenom

    await config.accept()
    await flushPromises()
    expect(mockContactDelete).toHaveBeenCalledWith(1, 1)

    await deleteButtons()[1].trigger('click')
    config = confirmRequire.mock.calls[1][0]
    expect(config.message).toContain('"Sara"') // sans prenom
  })

  it("gere les gardes sans entreprise selectionnee et l'erreur de sauvegarde", async () => {
    const { wrapper } = await mountPage(EntrepriseListPage)
    const vm = wrapper.vm as any

    // Gardes : aucun dialog contacts ouvert
    await vm.onSubmitContact()
    expect(mockContactCreate).not.toHaveBeenCalled()
    vm.confirmDeleteContact(contacts[0])
    expect(confirmRequire).not.toHaveBeenCalled()

    // Erreur API : le formulaire reste ouvert
    mockContactCreate.mockRejectedValue(new Error('422'))
    await findButton(wrapper, 'Gerer les contacts')!.trigger('click')
    await flushPromises()
    await findButton(wrapper, 'Ajouter un contact')!.trigger('click')
    await wrapper.find('#c-nom').setValue('Echec')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockContactCreate).toHaveBeenCalled()
    expect(wrapper.find('#c-nom').exists()).toBe(true)
    expect(vm.contactSaving).toBe(false)
  })
})
