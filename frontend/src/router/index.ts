import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const ROLES = {
  allStaff: ['admin', 'secretaire', 'collaborateur'],
  adminSecretaire: ['admin', 'secretaire'],
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
          path: 'missions',
          name: 'missions',
          component: () => import('@/pages/missions/MissionListPage.vue'),
          meta: { roles: ROLES.allStaff },
        },
        {
          path: 'missions/:id',
          name: 'mission-detail',
          component: () => import('@/pages/missions/MissionDetailPage.vue'),
          meta: { roles: ROLES.allStaff },
        },
        {
          path: 'missions/:id/taches/:tacheId',
          name: 'tache-detail',
          component: () => import('@/pages/missions/TacheDetailPage.vue'),
          meta: { roles: ROLES.allStaff },
        },
        {
          path: 'planning',
          name: 'planning',
          component: () => import('@/pages/planning/PlanningCalendarPage.vue'),
          meta: { roles: ROLES.allStaff },
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

  if (!auth.isAuthenticated && !to.meta.guest) {
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
})

router.afterEach((to) => {
  const title = (to.name as string)?.replace(/-/g, ' ') ?? 'Page'
  document.title = `${title.charAt(0).toUpperCase() + title.slice(1)} — Ledge`
})

export default router
