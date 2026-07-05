/**
 * Harnais de montage partage pour les tests de pages/composants.
 *
 * Usage type dans un test de page :
 *
 *   // 1. Mocks API (hoistes) — un vi.mock par module api utilise par la page
 *   const { mockGetAll } = vi.hoisted(() => ({ mockGetAll: vi.fn() }))
 *   vi.mock('@/api/modules/prestations', () => ({ prestationsApi: { getAll: mockGetAll } }))
 *
 *   // 2. Mocks PrimeVue services (convention du projet)
 *   const toastAdd = vi.fn()
 *   vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))
 *   const confirmRequire = vi.fn()
 *   vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))
 *
 *   // 3. Import de la page APRES les mocks, puis montage
 *   import Page from '@/pages/xxx/XxxPage.vue'
 *   const { wrapper } = await mountPage(Page, { role: 'admin' })
 *
 * Les dialogs PrimeVue (Teleport) sont rendus DANS le wrapper (teleport stubbe)
 * -> wrapper.text() contient le contenu des Dialog ouverts.
 */
import { mount, flushPromises } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import type { Component } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
import StyleClass from 'primevue/styleclass'
import Ripple from 'primevue/ripple'
import { createRouter, createMemoryHistory } from 'vue-router'
import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { User } from '@/types'

export function makeUser(role: string = 'admin', overrides: Partial<User> = {}): User {
  return {
    id: 1,
    name: 'Test User',
    email: 'test@ledge.dz',
    entreprise_id: role === 'client' ? 1 : null,
    portail_actif: role === 'client',
    email_verified_at: null,
    roles: [role],
    created_at: '2026-01-01T00:00:00.000000Z',
    updated_at: '2026-01-01T00:00:00.000000Z',
    ...overrides,
  }
}

export interface MountPageOptions {
  /** Role de l'utilisateur connecte ('admin' par defaut). `null` => visiteur non connecte. */
  role?: string | null
  /** Surcharges du user (ex: { id: 99 }). */
  user?: Partial<User>
  props?: Record<string, unknown>
  /** URL initiale du router memoire (ex: '/missions/5'). */
  path?: string
  /** Pattern de la route pour extraire les params (ex: '/missions/:id'). */
  routePattern?: string
  /** Stubs additionnels (fusionnes avec les stubs par defaut). */
  stubs?: Record<string, Component | boolean>
  /** Ne pas attendre flushPromises apres montage. */
  skipFlush?: boolean
}

export interface MountPageResult {
  wrapper: VueWrapper
  router: Router
  auth: ReturnType<typeof useAuthStore>
}

/** Stubs par defaut : composants lourds/canvas inutilisables sous happy-dom. */
const DEFAULT_STUBS: Record<string, Component | boolean> = {
  // chart.js exige un canvas 2D (absent de happy-dom)
  Chart: { template: '<div class="chart-stub" aria-hidden="true" />', props: ['data', 'options', 'type'] },
  // FullCalendar manipule intensivement le DOM reel
  FullCalendar: { template: '<div class="fullcalendar-stub" />', props: ['options'] },
  // Les Dialog/Drawer teleportes restent dans le wrapper
  teleport: true,
}

export async function mountPage(component: Component, options: MountPageOptions = {}): Promise<MountPageResult> {
  setActivePinia(createPinia())
  const auth = useAuthStore()
  if (options.role !== null) {
    auth.user = makeUser(options.role ?? 'admin', options.user)
  }

  const pattern = options.routePattern ?? options.path ?? '/'
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: pattern, component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } },
    ],
  })
  await router.push(options.path ?? '/')
  await router.isReady()

  const wrapper = mount(component, {
    props: options.props,
    global: {
      plugins: [PrimeVue, ToastService, ConfirmationService, router],
      directives: { tooltip: Tooltip, styleclass: StyleClass, ripple: Ripple },
      stubs: { ...DEFAULT_STUBS, ...options.stubs },
    },
  })

  if (!options.skipFlush) await flushPromises()
  return { wrapper, router, auth }
}

/** Retrouve un bouton par son texte visible ou son aria-label. */
export function findButton(wrapper: VueWrapper, textOrLabel: string) {
  return wrapper.findAll('button').find(
    b => b.text().includes(textOrLabel) || b.attributes('aria-label')?.includes(textOrLabel),
  )
}
