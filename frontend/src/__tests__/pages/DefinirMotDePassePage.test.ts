import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { AxiosError } from 'axios'
import type { ApiError } from '@/types/api-error'

// ── Mock du module API auth ──────────────────────────────────────────────────
const { mockResetPassword, mockForgotPassword } = vi.hoisted(() => ({
  mockResetPassword: vi.fn(),
  mockForgotPassword: vi.fn(),
}))

vi.mock('@/api/modules/auth', () => ({
  authApi: {
    resetPassword: mockResetPassword,
    forgotPassword: mockForgotPassword,
  },
}))

// ── Mock PrimeVue toast ──────────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import DefinirMotDePassePage from '@/pages/auth/DefinirMotDePassePage.vue'
import { mountPage } from '../helpers/mount'

const LIEN_VALIDE = '/definir-mot-de-passe?token=abc123&email=nouveau@cabinet.dz'
const PATTERN = '/definir-mot-de-passe'

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

async function monterLienValide() {
  return mountPage(DefinirMotDePassePage, { role: null, path: LIEN_VALIDE, routePattern: PATTERN })
}

async function saisirEtSoumettre(
  wrapper: Awaited<ReturnType<typeof mountPage>>['wrapper'],
  password: string,
  confirmation: string,
) {
  await wrapper.find('#reset-password').setValue(password)
  await wrapper.find('#reset-password-confirm').setValue(confirmation)
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('DefinirMotDePassePage — lien invalide', () => {
  it('affiche une alerte et masque le formulaire quand token/email manquent', async () => {
    const { wrapper } = await mountPage(DefinirMotDePassePage, { role: null, path: PATTERN })

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toContain('Lien invalide ou incomplet')
    expect(alert.attributes('aria-live')).toBe('assertive')
    expect(wrapper.find('form').exists()).toBe(false)
    // Le lien de repli vers la connexion reste disponible
    expect(wrapper.find('a[href="/login"]').text()).toContain('Retour a la connexion')
  })
})

describe('DefinirMotDePassePage — lien valide', () => {
  it("pre-remplit l'email depuis la query en lecture seule et affiche le formulaire", async () => {
    const { wrapper } = await monterLienValide()

    expect(wrapper.find('h1').text()).toBe('Definir votre mot de passe')
    const emailInput = wrapper.find('#reset-email')
    expect((emailInput.element as HTMLInputElement).value).toBe('nouveau@cabinet.dz')
    expect(emailInput.attributes('readonly')).toBeDefined()
    // Labels associes aux champs (RGAA)
    expect(wrapper.find('label[for="reset-password"]').text()).toContain('Nouveau mot de passe')
    expect(wrapper.find('label[for="reset-password-confirm"]').text()).toContain('Confirmer')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('refuse la soumission si les deux mots de passe ne correspondent pas', async () => {
    const { wrapper } = await monterLienValide()

    await saisirEtSoumettre(wrapper, 'Abcd1234!', 'Autre9999!')

    expect(mockResetPassword).not.toHaveBeenCalled()
    expect(wrapper.find('[role="alert"]').text()).toContain('ne correspondent pas')
  })

  it('appelle resetPassword avec le token/email de la query puis redirige vers /login', async () => {
    mockResetPassword.mockResolvedValue({ message: 'ok' })
    const { wrapper, router } = await monterLienValide()

    await saisirEtSoumettre(wrapper, 'Abcd1234!', 'Abcd1234!')

    expect(mockResetPassword).toHaveBeenCalledWith({
      token: 'abc123',
      email: 'nouveau@cabinet.dz',
      password: 'Abcd1234!',
      password_confirmation: 'Abcd1234!',
    })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', summary: 'Mot de passe defini' }),
    )
    expect(router.currentRoute.value.path).toBe('/login')
  })

  it("affiche l'erreur de jeton renvoyee par l'API (errors.token)", async () => {
    mockResetPassword.mockRejectedValue(
      makeAxiosApiError({
        kind: 'validation',
        status: 422,
        message: 'Donnees invalides.',
        errors: { token: ['Ce lien a expire.'] },
      }),
    )
    const { wrapper, router } = await monterLienValide()

    await saisirEtSoumettre(wrapper, 'Abcd1234!', 'Abcd1234!')

    expect(wrapper.find('[role="alert"]').text()).toContain('Ce lien a expire.')
    expect(toastAdd).not.toHaveBeenCalled()
    expect(router.currentRoute.value.path).not.toBe('/login')
  })

  it("affiche l'erreur de mot de passe renvoyee par l'API (errors.password)", async () => {
    mockResetPassword.mockRejectedValue(
      makeAxiosApiError({
        kind: 'validation',
        status: 422,
        message: 'Donnees invalides.',
        errors: { password: ['Le mot de passe doit contenir au moins 8 caracteres.'] },
      }),
    )
    const { wrapper } = await monterLienValide()

    await saisirEtSoumettre(wrapper, 'a', 'a')

    expect(wrapper.find('[role="alert"]').text()).toContain('au moins 8 caracteres')
  })

  it("retombe sur le message generique de l'API sans details d'erreurs", async () => {
    mockResetPassword.mockRejectedValue(
      makeAxiosApiError({ kind: 'server', status: 500, message: 'Erreur interne du serveur.' }),
    )
    const { wrapper } = await monterLienValide()

    await saisirEtSoumettre(wrapper, 'Abcd1234!', 'Abcd1234!')

    expect(wrapper.find('[role="alert"]').text()).toContain('Erreur interne du serveur.')
  })
})
