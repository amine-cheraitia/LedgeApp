import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

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
          path: '',
          name: 'dashboard',
          component: () => import('@/pages/dashboard/DashboardPage.vue'),
        },
        {
          path: 'kpi/objectifs',
          name: 'kpi-objectifs',
          component: () => import('@/pages/dashboard/KpiObjectifsPage.vue'),
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/pages/users/UserListPage.vue'),
        },
        {
          path: 'entreprises',
          name: 'entreprises',
          component: () => import('@/pages/entreprises/EntrepriseListPage.vue'),
        },
        {
          path: 'entreprises/:id',
          name: 'entreprise-detail',
          component: () => import('@/pages/entreprises/EntrepriseDetailPage.vue'),
        },
        {
          path: 'exercices',
          name: 'exercices',
          component: () => import('@/pages/exercices/ExerciceListPage.vue'),
        },
        {
          path: 'prestations',
          name: 'prestations',
          component: () => import('@/pages/prestations/PrestationListPage.vue'),
        },
        {
          path: 'missions',
          name: 'missions',
          component: () => import('@/pages/missions/MissionListPage.vue'),
        },
        {
          path: 'missions/:id',
          name: 'mission-detail',
          component: () => import('@/pages/missions/MissionDetailPage.vue'),
        },
        {
          path: 'missions/:id/taches/:tacheId',
          name: 'tache-detail',
          component: () => import('@/pages/missions/TacheDetailPage.vue'),
        },
        {
          path: 'planning',
          name: 'planning',
          component: () => import('@/pages/planning/PlanningCalendarPage.vue'),
        },
        {
          path: 'devis',
          name: 'devis',
          component: () => import('@/pages/devis/DevisListPage.vue'),
        },
        {
          path: 'factures',
          name: 'factures',
          component: () => import('@/pages/factures/FactureListPage.vue'),
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/pages/settings/SettingsPage.vue'),
        },
        {
          path: 'creances',
          name: 'creances',
          component: () => import('@/pages/relances/CreancesPage.vue'),
        },
        {
          path: 'relances/config',
          name: 'relances-config',
          component: () => import('@/pages/relances/RelancesConfigPage.vue'),
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

  // Charger l'utilisateur si pas encore fait
  if (!auth.isAuthenticated && !to.meta.guest) {
    await auth.fetchUser()
  }

  // Page guest (login) — rediriger si déjà connecté
  if (to.meta.guest && auth.isAuthenticated) {
    return auth.isClient ? '/portail' : '/'
  }

  // Pages protégées — rediriger si non connecté
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return '/login'
  }

  // Vérifier accès back-office
  if (to.meta.backoffice && auth.isClient) {
    return '/portail'
  }

  // Vérifier accès portail
  if (to.meta.portail && auth.isBackoffice) {
    return '/'
  }
})

// Accessibilité RGAA : annoncer la navigation
router.afterEach((to) => {
  const title = (to.name as string)?.replace(/-/g, ' ') ?? 'Page'
  document.title = `${title.charAt(0).toUpperCase() + title.slice(1)} — Ledge`
})

export default router
