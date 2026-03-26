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
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, backoffice: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/pages/dashboard/DashboardPage.vue'),
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
      ],
    },
    {
      path: '/portail',
      component: () => import('@/layouts/PortailLayout.vue'),
      meta: { requiresAuth: true, portail: true },
      children: [
        {
          path: '',
          name: 'portail-dashboard',
          component: () => import('@/pages/portail/PortailDashboard.vue'),
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
