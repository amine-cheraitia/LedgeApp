import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, enableAutoUnmount } from '@vue/test-utils'
import { nextTick } from 'vue'

// ── Mocks (hoistes) ──────────────────────────────────────────────────────────

// Le store auth (utilise par AppSidebar/AppMenu) importe @/api/client (axios).
const { mockApiPost } = vi.hoisted(() => ({ mockApiPost: vi.fn() }))
vi.mock('@/api/client', () => ({
  default: {
    get: vi.fn(),
    post: mockApiPost,
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
  resetCsrf: vi.fn(),
}))

// AppConfigurator manipule le theme PrimeVue global : on mocke les utilitaires.
const { mockUpdatePreset, mockUpdateSurfacePalette, mockChain, mock$t } = vi.hoisted(() => {
  const chain: Record<string, ReturnType<typeof vi.fn>> = {}
  chain.preset = vi.fn(() => chain)
  chain.surfacePalette = vi.fn(() => chain)
  chain.use = vi.fn(() => chain)
  return {
    mockUpdatePreset: vi.fn(),
    mockUpdateSurfacePalette: vi.fn(),
    mockChain: chain,
    mock$t: vi.fn(() => chain),
  }
})
vi.mock('@primeuix/themes', () => ({
  updatePreset: mockUpdatePreset,
  updateSurfacePalette: mockUpdateSurfacePalette,
  $t: mock$t,
}))
vi.mock('@primeuix/themes/aura', () => ({ default: { name: 'AuraMock' } }))
vi.mock('@primeuix/themes/lara', () => ({ default: { name: 'LaraMock' } }))
vi.mock('@primeuix/themes/nora', () => ({ default: { name: 'NoraMock' } }))

// ── Imports APRES les mocks ──────────────────────────────────────────────────
import { useLayout, applyStoredTheme } from '@/layout/composables/layout'
import AppLayout from '@/layout/AppLayout.vue'
import AppTopbar from '@/layout/AppTopbar.vue'
import AppSidebar from '@/layout/AppSidebar.vue'
import AppMenu from '@/layout/AppMenu.vue'
import AppMenuItem from '@/layout/AppMenuItem.vue'
import AppFooter from '@/layout/AppFooter.vue'
import AppConfigurator from '@/layout/AppConfigurator.vue'
import FloatingConfigurator from '@/components/FloatingConfigurator.vue'
import LedgeLogo from '@/components/LedgeLogo.vue'
import { mountPage, findButton } from '../helpers/mount'

enableAutoUnmount(afterEach)

// ── Etat module partage (layout.ts) : reset complet entre chaque test ───────
const { layoutConfig, layoutState } = useLayout()

function setInnerWidth(width: number) {
  Object.defineProperty(window, 'innerWidth', { value: width, writable: true, configurable: true })
}

beforeEach(() => {
  vi.clearAllMocks()
  localStorage.clear()
  document.documentElement.classList.remove('app-dark')
  delete (document as any).startViewTransition
  setInnerWidth(1200) // desktop par defaut (> 991)
  layoutConfig.preset = 'Aura'
  layoutConfig.primary = 'emerald'
  layoutConfig.surface = null
  layoutConfig.darkTheme = false
  layoutConfig.menuMode = 'static'
  Object.assign(layoutState, {
    staticMenuInactive: false,
    overlayMenuActive: false,
    configSidebarVisible: false,
    sidebarExpanded: false,
    menuHoverActive: false,
    activeMenuItem: null,
    activePath: null,
    mobileMenuActive: false,
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// composables/layout.ts
// ─────────────────────────────────────────────────────────────────────────────

describe('useLayout — theme sombre', () => {
  it("applyStoredTheme applique la preference stockee dans localStorage ('ledge-dark-mode')", () => {
    localStorage.setItem('ledge-dark-mode', 'true')
    expect(applyStoredTheme()).toBe(true)
    expect(document.documentElement.classList.contains('app-dark')).toBe(true)

    localStorage.setItem('ledge-dark-mode', 'false')
    expect(applyStoredTheme()).toBe(false)
    expect(document.documentElement.classList.contains('app-dark')).toBe(false)
  })

  it('applyStoredTheme retombe sur prefers-color-scheme quand rien nest stocke', () => {
    // happy-dom : matchMedia('(prefers-color-scheme: dark)').matches === false
    expect(applyStoredTheme()).toBe(false)
    expect(document.documentElement.classList.contains('app-dark')).toBe(false)
  })

  it('toggleDarkMode bascule la classe app-dark et persiste dans localStorage', () => {
    const { toggleDarkMode, isDarkTheme } = useLayout()

    toggleDarkMode()
    expect(isDarkTheme.value).toBe(true)
    expect(document.documentElement.classList.contains('app-dark')).toBe(true)
    expect(localStorage.getItem('ledge-dark-mode')).toBe('true')

    toggleDarkMode()
    expect(isDarkTheme.value).toBe(false)
    expect(localStorage.getItem('ledge-dark-mode')).toBe('false')
  })

  it('toggleDarkMode passe par startViewTransition quand le navigateur le supporte', () => {
    const { toggleDarkMode, isDarkTheme } = useLayout()
    const startViewTransition = vi.fn((cb: () => void) => cb())
    ;(document as any).startViewTransition = startViewTransition

    toggleDarkMode()
    expect(startViewTransition).toHaveBeenCalledOnce()
    expect(isDarkTheme.value).toBe(true)
  })
})

describe('useLayout — menu', () => {
  it('toggleMenu en desktop + mode static bascule staticMenuInactive', () => {
    const { toggleMenu, isDesktop } = useLayout()
    expect(isDesktop()).toBe(true)

    toggleMenu()
    expect(layoutState.staticMenuInactive).toBe(true)
    toggleMenu()
    expect(layoutState.staticMenuInactive).toBe(false)
  })

  it('toggleMenu en desktop + mode overlay bascule overlayMenuActive', () => {
    const { toggleMenu, changeMenuMode, hasOpenOverlay } = useLayout()
    changeMenuMode({ value: 'overlay' })
    expect(layoutConfig.menuMode).toBe('overlay')

    toggleMenu()
    expect(layoutState.overlayMenuActive).toBe(true)
    expect(hasOpenOverlay.value).toBe(true)
  })

  it('toggleMenu en mobile bascule mobileMenuActive', () => {
    const { toggleMenu, isDesktop } = useLayout()
    setInnerWidth(500)
    expect(isDesktop()).toBe(false)

    toggleMenu()
    expect(layoutState.mobileMenuActive).toBe(true)
  })

  it('changeMenuMode remet a zero les etats transitoires de la sidebar', () => {
    const { changeMenuMode } = useLayout()
    layoutState.staticMenuInactive = true
    layoutState.mobileMenuActive = true
    layoutState.sidebarExpanded = true
    layoutState.menuHoverActive = true

    changeMenuMode({ value: 'static' })

    expect(layoutConfig.menuMode).toBe('static')
    expect(layoutState.staticMenuInactive).toBe(false)
    expect(layoutState.mobileMenuActive).toBe(false)
    expect(layoutState.sidebarExpanded).toBe(false)
    expect(layoutState.menuHoverActive).toBe(false)
  })

  it('toggleConfigSidebar et hideMobileMenu pilotent leurs drapeaux', () => {
    const { toggleConfigSidebar, hideMobileMenu } = useLayout()

    toggleConfigSidebar()
    expect(layoutState.configSidebarVisible).toBe(true)

    layoutState.mobileMenuActive = true
    hideMobileMenu()
    expect(layoutState.mobileMenuActive).toBe(false)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// LedgeLogo
// ─────────────────────────────────────────────────────────────────────────────

describe('LedgeLogo', () => {
  it('sans wordmark : le SVG porte le nom accessible "Ledge" (role img)', async () => {
    const { wrapper } = await mountPage(LedgeLogo)
    const svg = wrapper.find('svg')

    expect(svg.attributes('role')).toBe('img')
    expect(svg.attributes('aria-label')).toBe('Ledge')
    expect(svg.attributes('aria-hidden')).toBeUndefined()
    expect(svg.attributes('width')).toBe('32') // taille par defaut
    expect(wrapper.find('.ledge-logo-wordmark').exists()).toBe(false)
  })

  it('avec wordmark : le SVG devient decoratif et le texte "Ledge" est visible', async () => {
    const { wrapper } = await mountPage(LedgeLogo, { props: { size: 36, withWordmark: true } })
    const svg = wrapper.find('svg')

    expect(svg.attributes('aria-hidden')).toBe('true')
    expect(svg.attributes('role')).toBeUndefined()
    expect(svg.attributes('width')).toBe('36')
    expect(wrapper.find('.ledge-logo-wordmark').text()).toBe('Ledge')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// AppFooter
// ─────────────────────────────────────────────────────────────────────────────

describe('AppFooter', () => {
  it("affiche la marque, l'annee courante et le role contentinfo", async () => {
    const { wrapper } = await mountPage(AppFooter)
    const footer = wrapper.find('footer')

    expect(footer.attributes('role')).toBe('contentinfo')
    expect(footer.text()).toContain('Ledge')
    expect(footer.text()).toContain(String(new Date().getFullYear()))
    expect(footer.text()).toContain('Cabinet de conseil')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// AppTopbar
// ─────────────────────────────────────────────────────────────────────────────

describe('AppTopbar', () => {
  it('rend le header (role banner) avec le logo et le bouton menu accessible', async () => {
    const { wrapper } = await mountPage(AppTopbar)

    expect(wrapper.find('header').attributes('role')).toBe('banner')
    expect(findButton(wrapper, 'Ouvrir le menu')).toBeDefined()
    expect(wrapper.find('.ledge-logo').exists()).toBe(true)
  })

  it('le bouton menu declenche toggleMenu (desktop static -> staticMenuInactive)', async () => {
    const { wrapper } = await mountPage(AppTopbar)

    await findButton(wrapper, 'Ouvrir le menu')!.trigger('click')
    expect(layoutState.staticMenuInactive).toBe(true)
  })

  it("le bouton theme bascule le mode sombre et adapte son aria-label", async () => {
    const { wrapper } = await mountPage(AppTopbar)

    const toggle = findButton(wrapper, 'Passer en mode sombre')
    expect(toggle).toBeDefined()
    expect(toggle!.find('i').classes()).toContain('pi-sun')

    await toggle!.trigger('click')
    await nextTick()

    expect(layoutConfig.darkTheme).toBe(true)
    expect(findButton(wrapper, 'Passer en mode clair')).toBeDefined()
    expect(document.documentElement.classList.contains('app-dark')).toBe(true)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// AppMenu — filtrage par role
// ─────────────────────────────────────────────────────────────────────────────

describe('AppMenu — visibilite par role', () => {
  it('admin : voit gestion, facturation, relances et administration', async () => {
    const { wrapper } = await mountPage(AppMenu, { role: 'admin' })
    const texte = wrapper.text()

    expect(texte).toContain('Dashboard')
    expect(texte).toContain('Entreprises')
    expect(texte).toContain('Missions')
    expect(texte).toContain('Planning')
    expect(texte).toContain('Devis')
    expect(texte).toContain('Relances')
    expect(texte).toContain('Administration')
    expect(texte).toContain("Journal d'audit")
    expect(texte).toContain('Utilisateurs')
  })

  it('secretaire : entreprises + facturation, mais ni missions/planning ni administration', async () => {
    const { wrapper } = await mountPage(AppMenu, { role: 'secretaire' })
    const texte = wrapper.text()

    expect(texte).toContain('Entreprises')
    expect(texte).toContain('Devis')
    expect(texte).toContain('Factures')
    expect(texte).toContain('Creances')
    expect(texte).not.toContain('Missions')
    expect(texte).not.toContain('Planning')
    expect(texte).not.toContain('Relances')
    expect(texte).not.toContain('Administration')
  })

  it('collaborateur : missions + planning, mais ni entreprises ni facturation', async () => {
    const { wrapper } = await mountPage(AppMenu, { role: 'collaborateur' })
    const texte = wrapper.text()

    expect(texte).toContain('Missions')
    expect(texte).toContain('Planning')
    expect(texte).not.toContain('Entreprises')
    expect(texte).not.toContain('Devis')
    expect(texte).not.toContain('Administration')
  })

  it('client : ne voit que le dashboard (aucune section staff)', async () => {
    const { wrapper } = await mountPage(AppMenu, { role: 'client' })
    const texte = wrapper.text()

    expect(texte).toContain('Dashboard')
    expect(texte).not.toContain('Gestion')
    expect(texte).not.toContain('Facturation')
    expect(texte).not.toContain('Administration')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// AppMenuItem
// ─────────────────────────────────────────────────────────────────────────────

// isVisible() de VTU passe par getComputedStyle, qui ignore les styles inline
// sur un arbre detache sous happy-dom -> on lit directement le style du v-show.
function estDeplie(wrapper: { element: Element }): boolean {
  return (wrapper.element as HTMLElement).style.display !== 'none'
}

describe('AppMenuItem — accordeon', () => {
  const accordeon = {
    label: 'Administration',
    accordion: true,
    icon: 'pi pi-cog',
    items: [{ label: 'Prestations', icon: 'pi pi-list', to: '/prestations' }],
  }

  it('defaultOpen: ouvert au montage, repliable au clic et au clavier (enter/espace)', async () => {
    const { wrapper } = await mountPage(AppMenuItem, {
      props: { item: { ...accordeon, defaultOpen: true } },
    })
    const header = wrapper.find('a.layout-accordion-header')
    const submenu = wrapper.find('ul.layout-submenu')

    expect(header.attributes('aria-expanded')).toBe('true')
    expect(estDeplie(submenu)).toBe(true)

    await header.trigger('click')
    expect(header.attributes('aria-expanded')).toBe('false')
    expect(estDeplie(submenu)).toBe(false)

    await header.trigger('keydown', { key: 'Enter' })
    expect(header.attributes('aria-expanded')).toBe('true')
    expect(estDeplie(submenu)).toBe(true)

    await header.trigger('keydown', { key: ' ' })
    expect(header.attributes('aria-expanded')).toBe('false')
  })

  it('sans defaultOpen : deplie seulement si la route courante appartient au groupe', async () => {
    const actif = await mountPage(AppMenuItem, {
      path: '/prestations',
      props: { item: accordeon },
    })
    expect(estDeplie(actif.wrapper.find('ul.layout-submenu'))).toBe(true)

    const inactif = await mountPage(AppMenuItem, { props: { item: accordeon } })
    expect(estDeplie(inactif.wrapper.find('ul.layout-submenu'))).toBe(false)
  })
})

describe('AppMenuItem — clics et etats', () => {
  it('un item disabled ne declenche pas sa commande', async () => {
    const command = vi.fn()
    const { wrapper } = await mountPage(AppMenuItem, {
      props: { item: { label: 'Desactive', disabled: true, command } },
    })

    await wrapper.find('a').trigger('click')
    expect(command).not.toHaveBeenCalled()
  })

  it("un item avec command l'execute et ferme les menus overlay/mobile", async () => {
    const command = vi.fn()
    layoutState.overlayMenuActive = true
    layoutState.mobileMenuActive = true
    const { wrapper } = await mountPage(AppMenuItem, {
      props: { item: { label: 'Action', command } },
    })

    await wrapper.find('a').trigger('click')

    expect(command).toHaveBeenCalledOnce()
    expect(command.mock.calls[0][0].item.label).toBe('Action')
    expect(layoutState.overlayMenuActive).toBe(false)
    expect(layoutState.mobileMenuActive).toBe(false)
  })

  it('un groupe (items) active son chemin au 1er clic puis le desactive au 2e', async () => {
    const groupe = {
      label: 'Gestion',
      path: '/gestion',
      items: [{ label: 'Sous-item', to: '/gestion/sous' }],
    }
    const { wrapper } = await mountPage(AppMenuItem, { props: { item: groupe } })
    const lien = wrapper.find('a')

    await lien.trigger('click')
    expect(layoutState.activePath).toBe('/gestion')
    expect(layoutState.menuHoverActive).toBe(true)

    await lien.trigger('click') // isActive -> retire le segment du chemin actif
    expect(layoutState.activePath).toBe('')
  })

  it('un lien feuille (to) ferme overlay et mobile au clic', async () => {
    const { wrapper } = await mountPage(AppMenuItem, {
      props: { item: { label: 'Dashboard', to: '/' }, root: false },
    })
    layoutState.overlayMenuActive = true
    layoutState.mobileMenuActive = true

    await wrapper.find('a').trigger('click')

    expect(layoutState.overlayMenuActive).toBe(false)
    expect(layoutState.mobileMenuActive).toBe(false)
  })

  it('mouseenter sur un groupe racine active son chemin uniquement en survol de menu', async () => {
    const groupe = { label: 'Gestion', path: '/g', items: [{ label: 'x', to: '/g/x' }] }
    const { wrapper } = await mountPage(AppMenuItem, { props: { item: groupe } })
    const lien = wrapper.find('a')

    // menuHoverActive false -> rien ne se passe
    await lien.trigger('mouseenter')
    expect(layoutState.activePath).toBeNull()

    layoutState.menuHoverActive = true
    await lien.trigger('mouseenter')
    expect(layoutState.activePath).toBe('/g')
  })

  it('concatene parentPath + path et marque active-menuitem quand le chemin actif correspond', async () => {
    layoutState.activePath = '/parent/enfant'
    const { wrapper } = await mountPage(AppMenuItem, {
      props: {
        item: { label: 'Enfant', path: '/enfant', items: [] },
        root: false,
        parentPath: '/parent',
      },
    })

    expect(wrapper.find('li').classes()).toContain('active-menuitem')
  })

  it('un item visible=false ne rend ni texte ni lien', async () => {
    const { wrapper } = await mountPage(AppMenuItem, {
      props: { item: { label: 'Cache', visible: false, to: '/cache' } },
    })

    expect(wrapper.text()).not.toContain('Cache')
    expect(wrapper.find('a').exists()).toBe(false)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// AppSidebar
// ─────────────────────────────────────────────────────────────────────────────

describe('AppSidebar', () => {
  it("affiche les initiales, le nom et le libelle du role de l'utilisateur", async () => {
    const { wrapper } = await mountPage(AppSidebar, { role: 'admin' })

    expect(wrapper.find('.layout-sidebar-user-avatar').text()).toBe('TU')
    expect(wrapper.find('.layout-sidebar-user-name').text()).toBe('Test User')
    expect(wrapper.find('.layout-sidebar-user-role').text()).toBe('Administrateur')
    expect(wrapper.find('aside').attributes('aria-label')).toBe('Navigation principale')
  })

  it('affiche les libelles traduits pour secretaire et le role brut si inconnu', async () => {
    const secretaire = await mountPage(AppSidebar, { role: 'secretaire' })
    expect(secretaire.wrapper.find('.layout-sidebar-user-role').text()).toBe('Secrétaire')

    const inconnu = await mountPage(AppSidebar, { role: 'stagiaire' })
    expect(inconnu.wrapper.find('.layout-sidebar-user-role').text()).toBe('stagiaire')
  })

  it('affiche un libelle vide quand le user na aucun role', async () => {
    const { wrapper } = await mountPage(AppSidebar, { user: { roles: [] } })
    expect(wrapper.find('.layout-sidebar-user-role').text()).toBe('')
  })

  it('deconnecte puis redirige vers /login au clic sur le bouton de deconnexion', async () => {
    const { wrapper, router, auth } = await mountPage(AppSidebar, { role: 'admin' })

    await findButton(wrapper, 'Se déconnecter')!.trigger('click')
    await flushPromises()

    expect(mockApiPost).toHaveBeenCalledWith('/logout')
    expect(auth.user).toBeNull()
    expect(wrapper.find('.layout-sidebar-user').exists()).toBe(false)
    expect(router.currentRoute.value.path).toBe('/login')
  })

  it('desktop : reinitialise activePath a chaque changement de route', async () => {
    const { router } = await mountPage(AppSidebar)
    expect(layoutState.activePath).toBeNull() // watch immediate

    layoutState.activePath = '/quelque-part'
    layoutState.mobileMenuActive = true
    await router.push('/entreprises')
    await flushPromises()

    expect(layoutState.activePath).toBeNull()
    expect(layoutState.mobileMenuActive).toBe(false)
  })

  it('mobile : memorise le chemin courant comme activePath', async () => {
    setInnerWidth(500)
    const { router } = await mountPage(AppSidebar)
    expect(layoutState.activePath).toBe('/') // watch immediate en mobile

    await router.push('/missions')
    await flushPromises()
    expect(layoutState.activePath).toBe('/missions')
  })

  it("desktop : un clic hors de la sidebar ferme le menu overlay ouvert", async () => {
    await mountPage(AppSidebar)

    layoutState.overlayMenuActive = true
    await nextTick() // le watcher hasOpenOverlay attache le listener document

    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(layoutState.overlayMenuActive).toBe(false)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// AppLayout
// ─────────────────────────────────────────────────────────────────────────────

describe('AppLayout', () => {
  it('assemble topbar + sidebar + footer + zone principale avec skip link', async () => {
    const { wrapper } = await mountPage(AppLayout, { role: 'admin' })

    expect(wrapper.find('a.skip-link').text()).toBe('Aller au contenu principal')
    expect(wrapper.find('header.layout-topbar').exists()).toBe(true)
    expect(wrapper.find('aside.layout-sidebar').exists()).toBe(true)
    expect(wrapper.find('footer.layout-footer').exists()).toBe(true)
    expect(wrapper.find('#main-content').attributes('role')).toBe('main')
    expect(wrapper.find('.layout-wrapper').classes()).toContain('layout-static')
  })

  it('reflete le mode overlay et les etats actifs dans les classes du wrapper', async () => {
    layoutConfig.menuMode = 'overlay'
    const { wrapper } = await mountPage(AppLayout)

    expect(wrapper.find('.layout-wrapper').classes()).toContain('layout-overlay')

    layoutState.overlayMenuActive = true
    layoutState.staticMenuInactive = true
    await nextTick()
    const classes = wrapper.find('.layout-wrapper').classes()
    expect(classes).toContain('layout-overlay-active')
    expect(classes).toContain('layout-static-inactive')
  })

  it('un clic sur le masque ferme le menu mobile', async () => {
    const { wrapper } = await mountPage(AppLayout)

    layoutState.mobileMenuActive = true
    await nextTick()
    expect(wrapper.find('.layout-wrapper').classes()).toContain('layout-mobile-active')

    await wrapper.find('.layout-mask').trigger('click')
    expect(layoutState.mobileMenuActive).toBe(false)
  })

  it('reset la sidebar uniquement au franchissement du breakpoint 992px', async () => {
    await mountPage(AppLayout) // monte en desktop (1200)

    // desktop -> mobile : ferme les menus
    layoutState.mobileMenuActive = true
    layoutState.overlayMenuActive = true
    setInnerWidth(500)
    window.dispatchEvent(new Event('resize'))
    expect(layoutState.mobileMenuActive).toBe(false)
    expect(layoutState.overlayMenuActive).toBe(false)

    // mobile -> mobile (pas de franchissement) : ne touche a rien
    layoutState.mobileMenuActive = true
    setInnerWidth(600)
    window.dispatchEvent(new Event('resize'))
    expect(layoutState.mobileMenuActive).toBe(true)

    // mobile -> desktop : sidebar apparente
    layoutState.staticMenuInactive = true
    setInnerWidth(1200)
    window.dispatchEvent(new Event('resize'))
    expect(layoutState.staticMenuInactive).toBe(false)
    expect(layoutState.mobileMenuActive).toBe(false)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// AppConfigurator
// ─────────────────────────────────────────────────────────────────────────────

describe('AppConfigurator', () => {
  it('applique une couleur principale de la palette (updatePreset)', async () => {
    const { wrapper } = await mountPage(AppConfigurator)

    await wrapper.find('button[title="emerald"]').trigger('click')

    expect(layoutConfig.primary).toBe('emerald')
    expect(mockUpdatePreset).toHaveBeenCalledOnce()
    const preset = mockUpdatePreset.mock.calls[0][0]
    expect(preset.semantic.primary['500']).toBe('#10b981')
  })

  it("la couleur 'noir' mappe le primaire sur la palette surface", async () => {
    const { wrapper } = await mountPage(AppConfigurator)

    await wrapper.find('button[title="noir"]').trigger('click')

    expect(layoutConfig.primary).toBe('noir')
    const preset = mockUpdatePreset.mock.calls[0][0]
    expect(preset.semantic.primary['50']).toBe('{surface.50}')
    expect(preset.semantic.colorScheme.light.primary.contrastColor).toBe('#ffffff')
  })

  it('applique une palette de surface (updateSurfacePalette)', async () => {
    const { wrapper } = await mountPage(AppConfigurator)

    await wrapper.find('button[title="gray"]').trigger('click')

    expect(layoutConfig.surface).toBe('gray')
    expect(mockUpdateSurfacePalette).toHaveBeenCalledOnce()
    expect(mockUpdateSurfacePalette.mock.calls[0][0]['500']).toBe('#6b7280')
  })

  it('onPresetChange recompose le theme complet via $t (preset + surface)', async () => {
    layoutConfig.surface = 'slate'
    const { wrapper } = await mountPage(AppConfigurator)

    ;(wrapper.vm as any).preset = 'Lara'
    ;(wrapper.vm as any).onPresetChange()

    expect(layoutConfig.preset).toBe('Lara')
    expect(mock$t).toHaveBeenCalledOnce()
    expect(mockChain.preset).toHaveBeenCalledWith({ name: 'LaraMock' })
    expect(mockChain.surfacePalette).toHaveBeenCalledWith(
      expect.objectContaining({ 500: '#64748b' }),
    )
    expect(mockChain.use).toHaveBeenCalledWith({ useDefaultOptions: true })
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// FloatingConfigurator
// ─────────────────────────────────────────────────────────────────────────────

describe('FloatingConfigurator', () => {
  it('expose les deux boutons accessibles et le panneau de configuration', async () => {
    const { wrapper } = await mountPage(FloatingConfigurator)

    expect(findButton(wrapper, 'Basculer le mode sombre')).toBeDefined()
    expect(findButton(wrapper, 'Configuration du theme')).toBeDefined()
    expect(wrapper.find('.config-panel').exists()).toBe(true)
  })

  it('le bouton lune/soleil bascule le mode sombre', async () => {
    const { wrapper } = await mountPage(FloatingConfigurator)

    await findButton(wrapper, 'Basculer le mode sombre')!.trigger('click')
    expect(layoutConfig.darkTheme).toBe(true)
  })
})
