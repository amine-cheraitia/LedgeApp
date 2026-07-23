import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const { mockGet, mockPost, mockResetCsrf } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockResetCsrf: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: {
    get: mockGet,
    post: mockPost,
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
  resetCsrf: mockResetCsrf,
}))

import { useAuthStore } from '@/stores/authStore'

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

async function loggedInWith(roles: string[]) {
  mockPost.mockResolvedValue({ data: { user: { id: 1, name: 'U', roles } } })
  const store = useAuthStore()
  await store.login('u@ledge.dz', 'secret')
  return store
}

describe('auth store — login', () => {
  it('poste les identifiants, normalise et renseigne le state', async () => {
    const store = await loggedInWith(['admin'])

    expect(mockPost).toHaveBeenCalledWith('/login', { email: 'u@ledge.dz', password: 'secret' })
    expect(store.user?.id).toBe(1)
    expect(store.isAuthenticated).toBe(true)
  })

  it("normalise les roles fournis en objets {name} en tableau de strings", async () => {
    mockPost.mockResolvedValue({ data: { user: { id: 2, name: 'Sec', roles: [{ name: 'secretaire' }] } } })
    const store = useAuthStore()

    await store.login('s@ledge.dz', 'x')

    expect(store.user?.roles).toEqual(['secretaire'])
    expect(store.isSecretaire).toBe(true)
  })

  it("remet loading a false meme en cas d'erreur", async () => {
    mockPost.mockRejectedValue(new Error('401'))
    const store = useAuthStore()

    await expect(store.login('x', 'y')).rejects.toThrow()
    expect(store.loading).toBe(false)
    expect(store.isAuthenticated).toBe(false)
  })
})

describe('auth store — logout & fetchUser', () => {
  it('logout vide le user et reinitialise le token CSRF', async () => {
    const store = await loggedInWith(['admin'])
    mockPost.mockResolvedValue({})

    await store.logout()

    expect(mockPost).toHaveBeenCalledWith('/logout')
    expect(store.user).toBeNull()
    expect(mockResetCsrf).toHaveBeenCalled()
  })

  it('fetchUser renseigne le user en cas de succes', async () => {
    mockGet.mockResolvedValue({ data: { user: { id: 3, name: 'Collab', roles: ['collaborateur'] } } })
    const store = useAuthStore()

    await store.fetchUser()

    expect(mockGet).toHaveBeenCalledWith('/me')
    expect(store.user?.id).toBe(3)
    expect(store.isCollaborateur).toBe(true)
  })

  it('fetchUser remet le user a null en cas d echec', async () => {
    mockGet.mockResolvedValueOnce({ data: { user: { id: 1, name: 'A', roles: ['admin'] } } })
    const store = useAuthStore()
    await store.fetchUser()
    expect(store.user).not.toBeNull()

    mockGet.mockRejectedValueOnce(new Error('419'))
    await store.fetchUser()
    expect(store.user).toBeNull()
  })
})

describe('auth store — getters de role', () => {
  it('admin -> isAdmin & isBackoffice, pas isClient', async () => {
    const store = await loggedInWith(['admin'])

    expect(store.isAdmin).toBe(true)
    expect(store.isBackoffice).toBe(true)
    expect(store.isClient).toBe(false)
  })

  it('client -> isClient, pas isBackoffice', async () => {
    const store = await loggedInWith(['client'])

    expect(store.isClient).toBe(true)
    expect(store.isBackoffice).toBe(false)
  })

  it('secretaire et collaborateur -> isBackoffice', async () => {
    expect((await loggedInWith(['secretaire'])).isBackoffice).toBe(true)
    expect((await loggedInWith(['collaborateur'])).isBackoffice).toBe(true)
  })

  it('hasAnyRole vrai si au moins un role commun', async () => {
    const store = await loggedInWith(['collaborateur'])

    expect(store.hasAnyRole(['admin', 'collaborateur'])).toBe(true)
    expect(store.hasAnyRole(['admin', 'secretaire'])).toBe(false)
  })

  it('getters faux sans utilisateur connecte', () => {
    const store = useAuthStore()

    expect(store.isAuthenticated).toBe(false)
    expect(store.isAdmin).toBe(false)
    expect(store.hasAnyRole(['admin'])).toBe(false)
  })
})
