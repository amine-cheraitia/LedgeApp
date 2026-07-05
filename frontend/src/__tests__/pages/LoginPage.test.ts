import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { AxiosError } from 'axios'
import type { ApiError } from '@/types/api-error'

// ── Mock du client axios (le store auth appelle api.post('/login')) ─────────
const { mockPost, mockGet } = vi.hoisted(() => ({
  mockPost: vi.fn(),
  mockGet: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: {
    get: mockGet,
    post: mockPost,
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
  resetCsrf: vi.fn(),
}))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import LoginPage from '@/pages/auth/LoginPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Helpers ──────────────────────────────────────────────────────────────────
function makeAxiosApiError(overrides: Partial<ApiError>): AxiosError {
  const err = new AxiosError(overrides.message ?? 'Erreur API')
  ;(err as AxiosError & { apiError: ApiError }).apiError = {
    kind: 'unknown',
    status: null,
    message: 'Erreur API',
    ...overrides,
  }
  return err
}

async function remplirEtSoumettre(wrapper: Awaited<ReturnType<typeof mountPage>>['wrapper']) {
  await wrapper.find('#email').setValue('staff@cabinet.dz')
  await wrapper.find('input[type="password"]').setValue('Secret123!')
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('LoginPage — rendu et accessibilite', () => {
  it('affiche le formulaire de connexion avec labels et skip link', async () => {
    const { wrapper } = await mountPage(LoginPage, { role: null, path: '/login' })

    expect(wrapper.find('h1').text()).toBe('Connexion')
    expect(wrapper.text()).toContain('Espace cabinet')
    // Skip link RGAA vers la zone principale
    const skip = wrapper.find('a.skip-link')
    expect(skip.attributes('href')).toBe('#login-main')
    expect(skip.text()).toContain('Aller au formulaire de connexion')
    // Labels associes aux champs
    expect(wrapper.find('label[for="email"]').text()).toBe('Adresse e-mail')
    expect(wrapper.find('label[for="password"]').text()).toBe('Mot de passe')
    // Bouton de soumission avec aria-label explicite
    expect(findButton(wrapper, 'Se connecter à Ledge')).toBeDefined()
    // Lien mot de passe oublie explicite (pas de "cliquez ici")
    const forgot = wrapper.find('a[href="/mot-de-passe-oublie"]')
    expect(forgot.text()).toContain('Mot de passe oublié')
  })

  it("n'affiche aucun message d'erreur au chargement", async () => {
    const { wrapper } = await mountPage(LoginPage, { role: null, path: '/login' })
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })
})

describe('LoginPage — connexion', () => {
  it('connecte un membre du staff et redirige vers le back-office (/)', async () => {
    mockPost.mockResolvedValue({
      data: { user: { id: 1, name: 'Admin', email: 'staff@cabinet.dz', roles: ['admin'] } },
    })
    const { wrapper, router, auth } = await mountPage(LoginPage, { role: null, path: '/login' })

    await remplirEtSoumettre(wrapper)

    expect(mockPost).toHaveBeenCalledWith('/login', {
      email: 'staff@cabinet.dz',
      password: 'Secret123!',
    })
    expect(auth.user?.roles).toContain('admin')
    expect(router.currentRoute.value.path).toBe('/')
  })

  it('redirige un client vers /portail', async () => {
    mockPost.mockResolvedValue({
      data: { user: { id: 7, name: 'Client', email: 'client@sarl.dz', roles: ['client'] } },
    })
    const { wrapper, router } = await mountPage(LoginPage, { role: null, path: '/login' })

    await remplirEtSoumettre(wrapper)

    expect(router.currentRoute.value.path).toBe('/portail')
  })

  it('affiche "Identifiants incorrects." sur une erreur de validation 422', async () => {
    mockPost.mockRejectedValue(
      makeAxiosApiError({ kind: 'validation', status: 422, message: 'Donnees invalides.' }),
    )
    const { wrapper, router } = await mountPage(LoginPage, { role: null, path: '/login' })

    await remplirEtSoumettre(wrapper)

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toContain('Identifiants incorrects.')
    expect(alert.attributes('aria-live')).toBe('assertive')
    // Le champ email est marque invalide pour les lecteurs d'ecran
    expect(wrapper.find('#email').attributes('aria-invalid')).toBe('true')
    // Pas de redirection
    expect(router.currentRoute.value.path).toBe('/login')
  })

  it("affiche le message de l'API sur une erreur serveur non-422", async () => {
    mockPost.mockRejectedValue(
      makeAxiosApiError({ kind: 'server', status: 500, message: 'Service momentanement indisponible.' }),
    )
    const { wrapper } = await mountPage(LoginPage, { role: null, path: '/login' })

    await remplirEtSoumettre(wrapper)

    expect(wrapper.find('[role="alert"]').text()).toContain('Service momentanement indisponible.')
  })

  it('affiche le message generique sur une erreur inconnue (non Axios)', async () => {
    mockPost.mockRejectedValue(new Error('boom'))
    const { wrapper } = await mountPage(LoginPage, { role: null, path: '/login' })

    await remplirEtSoumettre(wrapper)

    expect(wrapper.find('[role="alert"]').text()).toContain('Une erreur est survenue.')
  })
})

describe("LoginPage — dialog d'aide", () => {
  it("ouvre le dialog d'aide via le FAB puis le ferme", async () => {
    const { wrapper } = await mountPage(LoginPage, { role: null, path: '/login' })

    expect(wrapper.text()).not.toContain('Aide à la connexion')

    const fab = findButton(wrapper, 'aide à la connexion')
    expect(fab).toBeDefined()
    await fab!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Aide à la connexion')
    expect(wrapper.text()).toContain('portail client')

    await findButton(wrapper, 'Fermer')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).not.toContain('Aide à la connexion')
  })
})
