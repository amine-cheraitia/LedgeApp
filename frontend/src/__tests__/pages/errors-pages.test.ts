import { describe, it, expect, vi } from 'vitest'

// Le store auth importe @/api/client (axios) : on le mocke pour rester hors reseau.
vi.mock('@/api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
  resetCsrf: vi.fn(),
}))

import NotFoundPage from '@/pages/errors/NotFoundPage.vue'
import AccesRefusePage from '@/pages/errors/AccesRefusePage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ─────────────────────────────────────────────────────────────────────────────
// NotFoundPage — 404 accessible dans toutes les zones
// ─────────────────────────────────────────────────────────────────────────────

describe('NotFoundPage — contenu et accessibilite', () => {
  it('affiche le code 404, le titre et le message explicatif', async () => {
    const { wrapper } = await mountPage(NotFoundPage)

    expect(wrapper.text()).toContain('404')
    expect(wrapper.find('h1').text()).toBe('Page introuvable')
    expect(wrapper.text()).toContain("n'existe pas ou a été déplacée")
  })

  it('respecte le RGAA : skip link, role alert et aria-live', async () => {
    const { wrapper } = await mountPage(NotFoundPage)

    const skipLink = wrapper.find('a.skip-link')
    expect(skipLink.exists()).toBe(true)
    expect(skipLink.attributes('href')).toBe('#contenu-introuvable')
    expect(skipLink.text()).toBe('Aller au contenu principal')

    const alerte = wrapper.find('[role="alert"]')
    expect(alerte.exists()).toBe(true)
    expect(alerte.attributes('aria-live')).toBe('polite')
    expect(wrapper.find('main#contenu-introuvable').exists()).toBe(true)
  })
})

describe('NotFoundPage — destination du bouton selon la session', () => {
  it("visiteur non connecte : propose 'Aller à la connexion' et redirige vers /login", async () => {
    const { wrapper, router, auth } = await mountPage(NotFoundPage, { role: null })
    expect(auth.isAuthenticated).toBe(false)

    const bouton = findButton(wrapper, 'Aller à la connexion')
    expect(bouton).toBeDefined()

    const push = vi.spyOn(router, 'push')
    await bouton!.trigger('click')
    expect(push).toHaveBeenCalledWith('/login')
  })

  it("client connecte : propose 'Retour à l'accueil' et redirige vers /portail", async () => {
    const { wrapper, router } = await mountPage(NotFoundPage, { role: 'client' })

    const bouton = findButton(wrapper, "Retour à l'accueil")
    expect(bouton).toBeDefined()

    const push = vi.spyOn(router, 'push')
    await bouton!.trigger('click')
    expect(push).toHaveBeenCalledWith('/portail')
  })

  it('staff connecte (admin) : redirige vers le dashboard /', async () => {
    const { wrapper, router } = await mountPage(NotFoundPage, { role: 'admin' })

    const push = vi.spyOn(router, 'push')
    await findButton(wrapper, "Retour à l'accueil")!.trigger('click')
    expect(push).toHaveBeenCalledWith('/')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// AccesRefusePage — 403
// ─────────────────────────────────────────────────────────────────────────────

describe('AccesRefusePage', () => {
  it("affiche le titre 'Accès refusé' et le message d'aide", async () => {
    const { wrapper } = await mountPage(AccesRefusePage, { role: 'collaborateur' })

    expect(wrapper.find('h1').text()).toBe('Accès refusé')
    expect(wrapper.text()).toContain("Vous n'avez pas les droits nécessaires")
    expect(wrapper.text()).toContain('Contactez votre administrateur')
  })

  it('respecte le RGAA : skip link, role alert et aria-live', async () => {
    const { wrapper } = await mountPage(AccesRefusePage)

    const skipLink = wrapper.find('a.skip-link')
    expect(skipLink.exists()).toBe(true)
    expect(skipLink.attributes('href')).toBe('#contenu-acces-refuse')

    const alerte = wrapper.find('[role="alert"]')
    expect(alerte.exists()).toBe(true)
    expect(alerte.attributes('aria-live')).toBe('polite')
  })

  it('renvoie au tableau de bord au clic sur le bouton', async () => {
    const { wrapper, router } = await mountPage(AccesRefusePage)

    const bouton = findButton(wrapper, 'Retourner au tableau de bord')
    expect(bouton).toBeDefined()
    expect(bouton!.text()).toContain('Retour au tableau de bord')

    const push = vi.spyOn(router, 'push')
    await bouton!.trigger('click')
    expect(push).toHaveBeenCalledWith('/')
  })
})
