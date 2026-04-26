import { computed, reactive } from 'vue'

function loadDarkPreference(): boolean {
  const saved = localStorage.getItem('ledge-dark-mode')
  if (saved !== null) return saved === 'true'
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function loadCollapsedPreference(): boolean {
  return localStorage.getItem('ledge-sidebar-collapsed') === 'true'
}

const initialDark = loadDarkPreference()
if (initialDark) {
  document.documentElement.classList.add('app-dark')
}

const layoutConfig = reactive({
  preset: 'Aura',
  primary: 'emerald',
  surface: null as string | null,
  darkTheme: initialDark,
  menuMode: 'static' as 'static' | 'overlay',
})

const layoutState = reactive({
  staticMenuInactive: false,
  staticMenuCollapsed: loadCollapsedPreference(),
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

  const toggleMenuCollapse = () => {
    if (!isDesktop()) return
    layoutState.staticMenuCollapsed = !layoutState.staticMenuCollapsed
    localStorage.setItem('ledge-sidebar-collapsed', String(layoutState.staticMenuCollapsed))
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
    toggleMenuCollapse,
    hideMobileMenu,
    changeMenuMode,
    isDesktop,
    hasOpenOverlay,
  }
}
