<script setup lang="ts">
import { useLayout } from '@/layout/composables/layout'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import LedgeLogo from '@/components/LedgeLogo.vue'
import AppMenu from './AppMenu.vue'

const { layoutState, isDesktop, hasOpenOverlay } = useLayout()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const sidebarRef = ref<HTMLElement | null>(null)
let outsideClickListener: ((event: MouseEvent) => void) | null = null

const initiales = computed(() => {
  const name = auth.user?.name ?? '?'
  return name.split(' ').map((p) => p[0]).join('').toUpperCase().slice(0, 2)
})

const roleLabel = computed(() => {
  const r = auth.user?.roles?.[0]
  if (!r) return ''
  const map: Record<string, string> = {
    admin: 'Administrateur',
    collaborateur: 'Collaborateur',
    secretaire: 'Secrétaire',
    client: 'Client',
  }
  return map[r] ?? r
})

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

watch(
  () => route.path,
  (newPath) => {
    if (isDesktop()) layoutState.activePath = null
    else layoutState.activePath = newPath

    layoutState.overlayMenuActive = false
    layoutState.mobileMenuActive = false
    layoutState.menuHoverActive = false
  },
  { immediate: true },
)

watch(hasOpenOverlay, (newVal) => {
  if (isDesktop()) {
    if (newVal) bindOutsideClickListener()
    else unbindOutsideClickListener()
  }
})

function bindOutsideClickListener() {
  if (!outsideClickListener) {
    outsideClickListener = (event: MouseEvent) => {
      if (isOutsideClicked(event)) {
        layoutState.overlayMenuActive = false
      }
    }
    document.addEventListener('click', outsideClickListener)
  }
}

function unbindOutsideClickListener() {
  if (outsideClickListener) {
    document.removeEventListener('click', outsideClickListener)
    outsideClickListener = null
  }
}

function isOutsideClicked(event: MouseEvent) {
  const topbarButtonEl = document.querySelector('.layout-menu-button')
  return !(
    sidebarRef.value?.isSameNode(event.target as Node) ||
    sidebarRef.value?.contains(event.target as Node) ||
    topbarButtonEl?.isSameNode(event.target as Node) ||
    topbarButtonEl?.contains(event.target as Node)
  )
}

onBeforeUnmount(() => {
  unbindOutsideClickListener()
})
</script>

<template>
  <aside ref="sidebarRef" class="layout-sidebar" aria-label="Navigation principale">
    <!-- Brand header -->
    <div class="layout-sidebar-brand">
      <router-link to="/" class="layout-sidebar-brand-link" aria-label="Ledge — accueil">
        <LedgeLogo :size="36" with-wordmark />
      </router-link>
      <p class="layout-sidebar-tagline">Cabinet · Gestion intégrée</p>
    </div>

    <!-- Menu -->
    <nav class="layout-sidebar-nav">
      <AppMenu />
    </nav>

    <!-- User footer -->
    <div v-if="auth.user" class="layout-sidebar-user">
      <div class="layout-sidebar-user-avatar" aria-hidden="true">{{ initiales }}</div>
      <div class="layout-sidebar-user-meta">
        <span class="layout-sidebar-user-name">{{ auth.user.name }}</span>
        <span class="layout-sidebar-user-role">{{ roleLabel }}</span>
      </div>
      <button
        type="button"
        class="layout-sidebar-user-logout"
        aria-label="Se déconnecter"
        title="Se déconnecter"
        @click="handleLogout"
      >
        <i class="pi pi-sign-out" aria-hidden="true"></i>
      </button>
    </div>
  </aside>
</template>
