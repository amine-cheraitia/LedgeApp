import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { User } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetAll, mockCreate, mockUpdate, mockDelete, mockRenvoyer } = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
  mockCreate: vi.fn(),
  mockUpdate: vi.fn(),
  mockDelete: vi.fn(),
  mockRenvoyer: vi.fn(),
}))

vi.mock('@/api/modules/users', () => ({
  usersApi: {
    getAll: mockGetAll,
    create: mockCreate,
    update: mockUpdate,
    delete: mockDelete,
    renvoyerInvitation: mockRenvoyer,
  },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

const confirmRequire = vi.fn()
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import UserListPage from '@/pages/users/UserListPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
function makeStaff(overrides: Partial<User> = {}): User {
  return {
    id: 1,
    name: 'Amine Admin',
    email: 'amine@ledge.dz',
    entreprise_id: null,
    portail_actif: false,
    email_verified_at: null,
    roles: ['admin'],
    created_at: '2026-01-15T00:00:00.000000Z',
    updated_at: '2026-01-15T00:00:00.000000Z',
    ...overrides,
  }
}

const admin = makeStaff()
const sansRole = makeStaff({ id: 2, name: 'Nadia Nouvelle', email: 'nadia@ledge.dz', roles: [] })
const secretaire = makeStaff({ id: 3, name: 'Sarah Secretaire', email: 'sarah@ledge.dz', roles: ['secretaire'] })

const clipboardWriteText = vi.fn()

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: [admin, sansRole, secretaire], meta: { total: 3 } })
  Object.defineProperty(navigator, 'clipboard', {
    value: { writeText: clipboardWriteText },
    configurable: true,
  })
})

function boutonsAria(wrapper: any, label: string) {
  return wrapper.findAll('button').filter(
    (b: any) => b.attributes('aria-label')?.includes(label),
  )
}

describe('UserListPage — liste', () => {
  it('charge et affiche les utilisateurs avec leurs roles et date de creation', async () => {
    const { wrapper } = await mountPage(UserListPage)

    expect(mockGetAll).toHaveBeenCalledOnce()
    expect(wrapper.text()).toContain('Utilisateurs')
    expect(wrapper.text()).toContain('Amine Admin')
    expect(wrapper.text()).toContain('amine@ledge.dz')
    expect(wrapper.text()).toContain('admin')
    expect(wrapper.text()).toContain('secretaire')
    expect(wrapper.text()).toContain('2026') // created_at formate fr-FR
  })

  it('lance la recherche avec le terme saisi et revient page 1', async () => {
    const { wrapper } = await mountPage(UserListPage)

    await wrapper.find('#search-users').setValue('sarah')
    await wrapper.find('form[role="search"]').trigger('submit')
    await flushPromises()

    expect(mockGetAll).toHaveBeenLastCalledWith(
      expect.objectContaining({ search: 'sarah', page: 1 }),
    )
  })
})

describe('UserListPage — creation et invitation', () => {
  it('cree un utilisateur puis affiche le dialog invitation avec le lien de repli', async () => {
    mockCreate.mockResolvedValue({
      data: makeStaff({ id: 9, name: 'Yacine' }),
      invitation_url: 'https://ledge.dz/definir-mot-de-passe?token=abc123',
    })
    const { wrapper } = await mountPage(UserListPage)

    await findButton(wrapper, 'Nouvel utilisateur')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Nouvel utilisateur')
    expect(wrapper.text()).toContain("Un e-mail d'invitation sera envoye")

    await wrapper.find('#user-name').setValue('Yacine Collab')
    await wrapper.find('#user-email').setValue('yacine@ledge.dz')
    await wrapper.find('form.dialog-form').trigger('submit')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalledWith(
      expect.objectContaining({
        name: 'Yacine Collab',
        email: 'yacine@ledge.dz',
        role: 'collaborateur', // role par defaut du formulaire
      }),
    )
    // Refetch apres creation (composable)
    expect(mockGetAll).toHaveBeenCalledTimes(2)
    // Dialog invitation : email + lien copiable, jamais de mot de passe
    expect(wrapper.text()).toContain('Invitation envoyee')
    expect(wrapper.text()).toContain('yacine@ledge.dz')
    expect(wrapper.text()).toContain('https://ledge.dz/definir-mot-de-passe?token=abc123')
    expect(wrapper.find('[role="status"][aria-live="polite"]').exists()).toBe(true)
  })

  it('copie le lien d invitation dans le presse-papier', async () => {
    mockCreate.mockResolvedValue({
      data: makeStaff({ id: 9 }),
      invitation_url: 'https://ledge.dz/definir-mot-de-passe?token=abc123',
    })
    const { wrapper } = await mountPage(UserListPage)

    await findButton(wrapper, 'Nouvel utilisateur')!.trigger('click')
    await wrapper.find('#user-name').setValue('X')
    await wrapper.find('#user-email').setValue('x@ledge.dz')
    await wrapper.find('form.dialog-form').trigger('submit')
    await flushPromises()

    await findButton(wrapper, 'Copier le lien')!.trigger('click')

    expect(clipboardWriteText).toHaveBeenCalledWith('https://ledge.dz/definir-mot-de-passe?token=abc123')
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'info', summary: 'Copie' }),
    )
  })

  it('laisse le dialog ouvert si la creation echoue', async () => {
    mockCreate.mockRejectedValue({ response: { data: { message: 'Email deja pris' } } })
    const { wrapper } = await mountPage(UserListPage)

    await findButton(wrapper, 'Nouvel utilisateur')!.trigger('click')
    await wrapper.find('#user-name').setValue('X')
    await wrapper.find('#user-email').setValue('x@ledge.dz')
    await wrapper.find('form.dialog-form').trigger('submit')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalled()
    // Toujours en mode creation, pas de dialog invitation
    expect(wrapper.find('#user-name').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Invitation envoyee')
  })
})

describe('UserListPage — modification', () => {
  it('pre-remplit le formulaire et envoie la mise a jour', async () => {
    mockUpdate.mockResolvedValue({ data: admin })
    const { wrapper } = await mountPage(UserListPage)

    await boutonsAria(wrapper, 'Modifier')[0].trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain("Modifier l'utilisateur")
    const nameInput = wrapper.find('#user-name')
    expect((nameInput.element as HTMLInputElement).value).toBe('Amine Admin')

    await nameInput.setValue('Amine A.')
    await wrapper.find('form.dialog-form').trigger('submit')
    await flushPromises()

    expect(mockUpdate).toHaveBeenCalledWith(1, expect.objectContaining({
      name: 'Amine A.',
      email: 'amine@ledge.dz',
      role: 'admin',
    }))
    expect((wrapper.vm as any).dialogVisible).toBe(false)
  })

  it("retombe sur 'collaborateur' quand l utilisateur edite n a aucun role", async () => {
    const { wrapper } = await mountPage(UserListPage)

    // 2e ligne = utilisateur sans role
    await boutonsAria(wrapper, 'Modifier')[1].trigger('click')
    await flushPromises()

    expect((wrapper.vm as any).form.role).toBe('collaborateur')
  })
})

describe('UserListPage — renvoi d invitation', () => {
  it('demande confirmation puis renvoie l invitation et affiche le nouveau lien', async () => {
    mockRenvoyer.mockResolvedValue({ message: 'ok', invitation_url: 'https://ledge.dz/definir-mot-de-passe?token=neuf' })
    const { wrapper } = await mountPage(UserListPage)

    await boutonsAria(wrapper, "Renvoyer l'invitation a Sarah Secretaire")[0].trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('Sarah Secretaire')
    expect(config.message).toContain('sarah@ledge.dz')

    await config.accept()
    await flushPromises()

    expect(mockRenvoyer).toHaveBeenCalledWith(3)
    expect(wrapper.text()).toContain('Invitation envoyee')
    expect(wrapper.text()).toContain('sarah@ledge.dz')
    expect(wrapper.text()).toContain('token=neuf')
  })

  it("n affiche pas le dialog invitation si le renvoi echoue", async () => {
    mockRenvoyer.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountPage(UserListPage)

    await boutonsAria(wrapper, "Renvoyer l'invitation a Amine Admin")[0].trigger('click')
    await confirmRequire.mock.calls[0][0].accept()
    await flushPromises()

    expect(mockRenvoyer).toHaveBeenCalledWith(1)
    expect(wrapper.text()).not.toContain('Invitation envoyee')
  })
})

describe('UserListPage — suppression', () => {
  it('demande confirmation puis supprime et refetch', async () => {
    mockDelete.mockResolvedValue(undefined)
    const { wrapper } = await mountPage(UserListPage)

    await boutonsAria(wrapper, 'Supprimer')[0].trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toContain('Amine Admin')

    await config.accept()
    await flushPromises()

    expect(mockDelete).toHaveBeenCalledWith(1)
    expect(mockGetAll).toHaveBeenCalledTimes(2)
  })
})
