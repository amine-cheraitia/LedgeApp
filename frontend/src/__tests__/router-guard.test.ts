import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// ── Mock du client HTTP (le store auth importe directement @/api/client) ─────
const { mockGet, mockPost } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: {
    get: mockGet,
    post: mockPost,
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
  resetCsrf: vi.fn(),
}))

// Import du VRAI router APRES les mocks
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { makeUser } from './helpers/mount'

/** Prepare un utilisateur connecte avec session deja resolue (pas de fetchUser). */
function connecte(role: string) {
  const auth = useAuthStore()
  auth.user = makeUser(role)
  auth.initialized = true
  return auth
}

/** Prepare un visiteur non connecte avec session deja resolue. */
function visiteur() {
  const auth = useAuthStore()
  auth.initialized = true
  return auth
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  sessionStorage.clear()
})

describe('router — garde beforeEach', () => {
  it('cold-load : resout la session via fetchUser puis redirige le staff connecte hors de /login', async () => {
    // Session non initialisee -> la garde appelle fetchUser (GET /me)
    mockGet.mockResolvedValue({ data: { user: { id: 1, name: 'Admin', email: 'a@ledge.dz', roles: [{ name: 'admin' }] } } })

    await router.push('/login')

    expect(mockGet).toHaveBeenCalledWith('/me')
    expect(router.currentRoute.value.name).toBe('dashboard')
  }, 30000)

  it('visiteur non connecte : une route protegee redirige vers /login', async () => {
    visiteur()

    await router.push('/exercices')

    expect(router.currentRoute.value.name).toBe('login')
    // Pas de nouvel appel /me : la session etait deja resolue
    expect(mockGet).not.toHaveBeenCalled()
  }, 30000)

  it('visiteur non connecte : les routes guest restent accessibles', async () => {
    visiteur()

    await router.push('/mot-de-passe-oublie')
    expect(router.currentRoute.value.name).toBe('mot-de-passe-oublie')

    await router.push('/definir-mot-de-passe')
    expect(router.currentRoute.value.name).toBe('definir-mot-de-passe')
  }, 30000)

  it('client connecte : le back-office est bloque -> redirection /portail', async () => {
    connecte('client')

    await router.push('/entreprises')

    expect(router.currentRoute.value.path).toBe('/portail')
    expect(router.currentRoute.value.name).toBe('portail-dashboard')
  }, 30000)

  it('client connecte : une route guest (/login) redirige vers /portail', async () => {
    connecte('client')

    // On se place d'abord sur une page portail autorisee
    await router.push('/portail/factures')
    expect(router.currentRoute.value.name).toBe('portail-factures')

    await router.push('/login')
    expect(router.currentRoute.value.name).toBe('portail-dashboard')
  }, 30000)

  it('staff connecte : le portail est bloque -> redirection back-office', async () => {
    connecte('admin')

    await router.push('/portail/missions')

    expect(router.currentRoute.value.path).toBe('/')
    expect(router.currentRoute.value.name).toBe('dashboard')
  }, 30000)

  it('meta.roles insuffisant (secretaire sur route admin) -> acces-refuse avec redirect', async () => {
    connecte('secretaire')

    await router.push('/prestations')

    expect(router.currentRoute.value.name).toBe('acces-refuse')
    expect(router.currentRoute.value.query.redirect).toBe('/prestations')
  }, 30000)

  it('meta.roles suffisant (admin) -> acces accorde', async () => {
    connecte('admin')

    await router.push('/exercices')

    expect(router.currentRoute.value.name).toBe('exercices')
  }, 30000)

  it('erreur inattendue dans la garde : route protegee -> /login, route publique -> laissee passer', async () => {
    const auth = useAuthStore()
    auth.initialized = false
    const fetchUserMock = vi.fn().mockRejectedValue(new Error('session irrecuperable'))
    ;(auth as unknown as { fetchUser: () => Promise<void> }).fetchUser = fetchUserMock

    // Route protegee -> repli /login
    await router.push('/factures')
    expect(router.currentRoute.value.name).toBe('login')

    // Route publique -> la navigation n'est pas bloquee malgre l'erreur
    await router.push('/mot-de-passe-oublie')
    expect(router.currentRoute.value.name).toBe('mot-de-passe-oublie')

    // 3 tentatives : /factures, la redirection /login, puis /mot-de-passe-oublie
    // (initialized reste false tant que fetchUser echoue)
    expect(fetchUserMock).toHaveBeenCalledTimes(3)
  }, 30000)
})

describe('router — afterEach (titre + reset des gardes anti-boucle)', () => {
  it('met a jour document.title depuis le nom de la route et purge les cles de rechargement', async () => {
    connecte('admin')
    sessionStorage.setItem('ledge:nav-reload', '/quelque-part')
    sessionStorage.setItem('ledge:preload-reload', '1')

    await router.push('/users')

    expect(document.title).toBe('Users — Ledge')
    expect(sessionStorage.getItem('ledge:nav-reload')).toBeNull()
    expect(sessionStorage.getItem('ledge:preload-reload')).toBeNull()
  }, 30000)

  it('remplace les tirets du nom de route dans le titre', async () => {
    connecte('collaborateur')

    await router.push('/prestations') // adminOnly -> acces-refuse

    expect(router.currentRoute.value.name).toBe('acces-refuse')
    expect(document.title).toBe('Acces refuse — Ledge')
  }, 30000)
})
