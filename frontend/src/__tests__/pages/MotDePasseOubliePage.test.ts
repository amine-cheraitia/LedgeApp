import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { AxiosError } from 'axios'
import type { ApiError } from '@/types/api-error'

// ── Mock du module API auth ──────────────────────────────────────────────────
const { mockForgotPassword, mockResetPassword } = vi.hoisted(() => ({
  mockForgotPassword: vi.fn(),
  mockResetPassword: vi.fn(),
}))

vi.mock('@/api/modules/auth', () => ({
  authApi: {
    forgotPassword: mockForgotPassword,
    resetPassword: mockResetPassword,
  },
}))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import MotDePasseOubliePage from '@/pages/auth/MotDePasseOubliePage.vue'
import { mountPage } from '../helpers/mount'

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

async function monterLaPage() {
  return mountPage(MotDePasseOubliePage, { role: null, path: '/mot-de-passe-oublie' })
}

async function soumettre(wrapper: Awaited<ReturnType<typeof mountPage>>['wrapper'], email: string) {
  await wrapper.find('#forgot-email').setValue(email)
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('MotDePasseOubliePage — rendu', () => {
  it("affiche le formulaire avec label, champ e-mail et lien retour", async () => {
    const { wrapper } = await monterLaPage()

    expect(wrapper.find('h1').text()).toBe('Mot de passe oublie')
    expect(wrapper.text()).toContain('vous recevrez un lien')
    expect(wrapper.find('label[for="forgot-email"]').text()).toBe('Adresse e-mail')
    expect(wrapper.find('#forgot-email').attributes('aria-required')).toBe('true')
    expect(wrapper.find('a[href="/login"]').text()).toContain('Retour a la connexion')
    // Aucun message affiche au chargement
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })
})

describe('MotDePasseOubliePage — soumission', () => {
  it("envoie l'email saisi et affiche la confirmation generique (anti-enumeration)", async () => {
    mockForgotPassword.mockResolvedValue({ message: 'ok' })
    const { wrapper } = await monterLaPage()

    await soumettre(wrapper, 'oubli@cabinet.dz')

    expect(mockForgotPassword).toHaveBeenCalledWith('oubli@cabinet.dz')
    const status = wrapper.find('[role="status"]')
    expect(status.exists()).toBe(true)
    expect(status.text()).toContain('Si un compte existe pour cette adresse')
    expect(status.attributes('aria-live')).toBe('polite')
    // Le formulaire disparait apres confirmation
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('affiche un message dedie quand la demande est throttlee (429)', async () => {
    mockForgotPassword.mockRejectedValue(
      makeAxiosApiError({ kind: 'throttle', status: 429, message: 'Too many requests.' }),
    )
    const { wrapper } = await monterLaPage()

    await soumettre(wrapper, 'oubli@cabinet.dz')

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toContain('Trop de demandes')
    expect(alert.attributes('aria-live')).toBe('assertive')
    // Pas de confirmation, le formulaire reste utilisable
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it("affiche le message de l'API pour les autres erreurs", async () => {
    mockForgotPassword.mockRejectedValue(
      makeAxiosApiError({ kind: 'server', status: 500, message: 'Erreur interne du serveur.' }),
    )
    const { wrapper } = await monterLaPage()

    await soumettre(wrapper, 'oubli@cabinet.dz')

    expect(wrapper.find('[role="alert"]').text()).toContain('Erreur interne du serveur.')
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('affiche le message generique sur une erreur inconnue (non Axios)', async () => {
    mockForgotPassword.mockRejectedValue(new Error('boom'))
    const { wrapper } = await monterLaPage()

    await soumettre(wrapper, 'oubli@cabinet.dz')

    expect(wrapper.find('[role="alert"]').text()).toContain('Une erreur est survenue.')
  })
})
