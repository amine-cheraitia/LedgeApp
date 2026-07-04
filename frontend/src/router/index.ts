import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const ROLES = {
  allStaff: ['admin', 'secretaire', 'collaborateur'],
  adminSecretaire: ['admin', 'secretaire'],
  adminCollaborateur: ['admin', 'collaborateur'],
  adminOnly: ['admin'],
} as const

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/auth/LoginPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/mot-de-passe-oublie',
      name: 'mot-de-passe-oublie',
      component: () => import('@/pages/auth/MotDePasseOubliePage.vue'),
      meta: { guest: true },
    },
    {
      path: '/definir-mot-de-passe',
      name: 'definir-mot-de-passe',
      component: () => import('@/pages/auth/DefinirMotDePassePage.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('@/layout/AppLayout.vue'),
      meta: { requiresAuth: true, backoffice: true },
      children: [
        {
          path: 'acces-refuse',
          name: 'acces-refuse',
          component: () => import('@/pages/errors/AccesRefusePage.vue'),
        },
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/pages/dashboard/DashboardPage.vue'),
          meta: { roles: ROLES.allStaff },
        },
        {
          path: 'kpi/objectifs',
          name: 'kpi-objectifs',
          component: () => import('@/pages/dashboard/KpiObjectifsPage.vue'),
          meta: { roles: ROLES.adminOnly },
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/pages/users/UserListPage.vue'),
          meta: { roles: ROLES.adminOnly },
        },
        {
          path: 'entreprises',
          name: 'entreprises',
          component: () => import('@/pages/entreprises/EntrepriseListPage.vue'),
          meta: { roles: ROLES.adminSecretaire },
        },
        {
          path: 'entreprises/:id',
          name: 'entreprise-detail',
          component: () => import('@/pages/entreprises/EntrepriseDetailPage.vue'),
          meta: { roles: ROLES.adminSecretaire },
        },
        {
          path: 'exercices',
          name: 'exercices',
          component: () => import('@/pages/exercices/ExerciceListPage.vue'),
          meta: { roles: ROLES.adminOnly },
        },
        {
          path: 'prestations',
          name: 'prestations',
          component: () => import('@/pages/prestations/PrestationListPage.vue'),
          meta: { roles: ROLES.adminOnly },
        },
        {
          path: 'tva-taux',
          name: 'tva-taux',
          component: () => import('@/pages/settings/TvaTauxPage.vue'),
          meta: { roles: ROLES.adminOnly },
        },
        {
          path: 'missions',
          name: 'missions',
          component: () => import('@/pages/missions/MissionListPage.vue'),
          meta: { roles: ROLES.adminCollaborateur },
        },
        {
          path: 'missions/:id',
          name: 'mission-detail',
          component: () => import('@/pages/missions/MissionDetailPage.vue'),
          meta: { roles: ROLES.adminCollaborateur },
        },
        {
          path: 'missions/:id/taches/:tacheId',
          name: 'tache-detail',
          component: () => import('@/pages/missions/TacheDetailPage.vue'),
          meta: { roles: ROLES.adminCollaborateur },
        },
        {
          path: 'planning',
          name: 'planning',
          component: () => import('@/pages/planning/PlanningCalendarPage.vue'),
          meta: { roles: ROLES.adminCollaborateur },
        },
        {
          path: 'devis',
          name: 'devis',
          component: () => import('@/pages/devis/DevisListPage.vue'),
          meta: { roles: ROLES.adminSecretaire },
        },
        {
          path: 'factures',
          name: 'factures',
          component: () => import('@/pages/factures/FactureListPage.vue'),
          meta: { roles: ROLES.adminSecretaire },
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/pages/settings/SettingsPage.vue'),
          meta: { roles: ROLES.adminOnly },
        },
        {
          path: 'creances',
          name: 'creances',
          component: () => import('@/pages/relances/CreancesPage.vue'),
          meta: { roles: ROLES.adminSecretaire },
        },
        {
          path: 'relances/config',
          name: 'relances-config',
          component: () => import('@/pages/relances/RelancesConfigPage.vue'),
          meta: { roles: ROLES.adminOnly },
        },
        {
          path: 'audit-logs',
          name: 'audit-logs',
          component: () => import('@/pages/audit/AuditLogPage.vue'),
          meta: { roles: ROLES.adminOnly },
        },
      ],
    },
    {
      path: '/portail',
      component: () => import('@/layout/PortailLayout.vue'),
      meta: { requiresAuth: true, portail: true },
      children: [
        {
          path: '',
          name: 'portail-dashboard',
          component: () => import('@/pages/portail/PortailDashboard.vue'),
        },
        {
          path: 'factures',
          name: 'portail-factures',
          component: () => import('@/pages/portail/PortailFacturesPage.vue'),
        },
        {
          path: 'missions',
          name: 'portail-missions',
          component: () => import('@/pages/portail/PortailMissionsPage.vue'),
        },
        {
          path: 'documents',
          name: 'portail-documents',
          component: () => import('@/pages/portail/PortailDocumentsPage.vue'),
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  try {
    // Resolution de session une seule fois au demarrage, y compris sur les routes
    // guest (/login...) : sinon un utilisateur deja connecte qui recharge /login
    // n'est pas redirige (store vide au cold-load). Voir la garde meta.guest ci-dessous.
    if (!auth.initialized) {
      await auth.fetchUser()
    }

    if (to.meta.guest && auth.isAuthenticated) {
      return auth.isClient ? '/portail' : '/'
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return '/login'
    }

    if (to.meta.backoffice && auth.isClient) {
      return '/portail'
    }

    if (to.meta.portail && auth.isBackoffice) {
      return '/'
    }

    if (to.meta.roles && auth.user?.roles) {
      const allowed = to.meta.roles as string[]
      if (!auth.hasAnyRole(allowed)) {
        return { name: 'acces-refuse', query: { redirect: to.fullPath } }
      }
    }
  } catch {
    // Durcissement : une erreur inattendue dans la garde ne doit pas bloquer la
    // navigation en silence. Route protegee -> login, sinon on laisse passer.
    return to.meta.requiresAuth ? '/login' : undefined
  }
})

// ── Robustesse au chargement des chunks de route ────────────────────────────
// Les routes sont chargees en import dynamique. Apres un deploiement, un onglet
// deja ouvert reference d'anciens hash de chunks : le fetch fait 404, l'import()
// rejette et vue-router annule la navigation en silence (le clic "ne fait rien").
// On recharge alors la page a la bonne URL pour recuperer les chunks a jour.
const NAV_RELOAD_KEY = 'ledge:nav-reload'
const PRELOAD_RELOAD_KEY = 'ledge:preload-reload'

function isDynamicImportError(error: unknown): boolean {
  const message = error instanceof Error ? error.message : String(error)
  return /dynamically imported module|Importing a module script failed|Failed to fetch/i.test(message)
}

router.afterEach((to) => {
  // Navigation aboutie : on reinitialise les gardes anti-boucle de rechargement.
  sessionStorage.removeItem(NAV_RELOAD_KEY)
  sessionStorage.removeItem(PRELOAD_RELOAD_KEY)

  const title = (to.name as string)?.replace(/-/g, ' ') ?? 'Page'
  document.title = `${title.charAt(0).toUpperCase() + title.slice(1)} — Ledge`
})

// Echec au moment de la navigation (clic -> chunk 404) : on recharge une fois.
router.onError((error, to) => {
  if (!isDynamicImportError(error)) return
  if (sessionStorage.getItem(NAV_RELOAD_KEY) === to.fullPath) return // anti-boucle
  sessionStorage.setItem(NAV_RELOAD_KEY, to.fullPath)
  window.location.assign(to.fullPath)
})

// Filet Vite : echec de preload d'un module (emis par le helper d'import).
window.addEventListener('vite:preloadError', (event) => {
  event.preventDefault()
  if (sessionStorage.getItem(PRELOAD_RELOAD_KEY)) return // anti-boucle
  sessionStorage.setItem(PRELOAD_RELOAD_KEY, '1')
  window.location.reload()
})

export default router
