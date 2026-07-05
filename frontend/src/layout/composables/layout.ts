import { computed, reactive } from 'vue'

function loadDarkPreference(): boolean {
  const saved = localStorage.getItem('ledge-dark-mode')
  if (saved !== null) return saved === 'true'
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

/**
 * Applique la preference de theme (clair/sombre) sur <html>. A appeler au bootstrap
 * (main.ts) pour couvrir TOUTES les routes — y compris les pages standalone
 * (login, 404) chargees a froid, qui ne passent pas par l'AppLayout (seul importeur
 * historique de ce module, d'ou le theme non applique sur ces pages avant ce correctif).
 */
export function applyStoredTheme(): boolean {
  const dark = loadDarkPreference()
  document.documentElement.classList.toggle('app-dark', dark)
  return dark
}

const initialDark = applyStoredTheme()

const layoutConfig = reactive({
  preset: 'Aura',
  primary: 'emerald',
  surface: null as string | null,
  darkTheme: initialDark,
  menuMode: 'static' as 'static' | 'overlay',
})

const layoutState = reactive({
  staticMenuInactive: false,
  overlayMenuActive: false,
  configSidebarVisible: false,
  sidebarExpanded: false,
  menuHoverActive: false,
  activeMenuItem: null as string | null,
  activePath: null as string | null,
  mobileMenuActive: false,
})

export function useLayout() {
  const toggleDarkMode = () => {
    if (!(document as any).startViewTransition) {
      executeDarkModeToggle()
      return
    }
    ;(document as any).startViewTransition(() => executeDarkModeToggle())
  }

  const executeDarkModeToggle = () => {
    layoutConfig.darkTheme = !layoutConfig.darkTheme
    document.documentElement.classList.toggle('app-dark')
    localStorage.setItem('ledge-dark-mode', String(layoutConfig.darkTheme))
  }

  const toggleMenu = () => {
    if (isDesktop()) {
      if (layoutConfig.menuMode === 'static') {
        layoutState.staticMenuInactive = !layoutState.staticMenuInactive
      }
      if (layoutConfig.menuMode === 'overlay') {
        layoutState.overlayMenuActive = !layoutState.overlayMenuActive
      }
    } else {
      layoutState.mobileMenuActive = !layoutState.mobileMenuActive
    }
  }

  const toggleConfigSidebar = () => {
    layoutState.configSidebarVisible = !layoutState.configSidebarVisible
  }

  const hideMobileMenu = () => {
    layoutState.mobileMenuActive = false
  }

  const changeMenuMode = (event: { value: 'static' | 'overlay' }) => {
    layoutConfig.menuMode = event.value
    layoutState.staticMenuInactive = false
    layoutState.mobileMenuActive = false
    layoutState.sidebarExpanded = false
    layoutState.menuHoverActive = false
  }

  const isDarkTheme = computed(() => layoutConfig.darkTheme)
  const isDesktop = () => window.innerWidth > 991

  const hasOpenOverlay = computed(() => layoutState.overlayMenuActive)

  return {
    layoutConfig,
    layoutState,
    isDarkTheme,
    toggleDarkMode,
    toggleConfigSidebar,
    toggleMenu,
    hideMobileMenu,
    changeMenuMode,
    isDesktop,
    hasOpenOverlay,
  }
}
