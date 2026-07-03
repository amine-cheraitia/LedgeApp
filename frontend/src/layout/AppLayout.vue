<script setup lang="ts">
import { useLayout } from '@/layout/composables/layout'
import { computed, onBeforeUnmount, onMounted } from 'vue'
import AppFooter from './AppFooter.vue'
import AppSidebar from './AppSidebar.vue'
import AppTopbar from './AppTopbar.vue'

const { layoutConfig, layoutState, hideMobileMenu, isDesktop } = useLayout()

const containerClass = computed(() => ({
  'layout-overlay': layoutConfig.menuMode === 'overlay',
  'layout-static': layoutConfig.menuMode === 'static',
  'layout-overlay-active': layoutState.overlayMenuActive,
  'layout-mobile-active': layoutState.mobileMenuActive,
  'layout-static-inactive': layoutState.staticMenuInactive,
}))

// Reset de la sidebar au FRANCHISSEMENT du breakpoint 992px (pas a chaque pixel) :
// - mobile -> desktop : sidebar apparente
// - desktop -> mobile : sidebar fermee
let wasDesktop = isDesktop()
function handleBreakpointChange(): void {
  const nowDesktop = isDesktop()
  if (nowDesktop === wasDesktop) return
  wasDesktop = nowDesktop
  if (nowDesktop) {
    layoutState.staticMenuInactive = false
    layoutState.mobileMenuActive = false
  } else {
    layoutState.mobileMenuActive = false
    layoutState.overlayMenuActive = false
  }
}

onMounted(() => window.addEventListener('resize', handleBreakpointChange))
onBeforeUnmount(() => window.removeEventListener('resize', handleBreakpointChange))
</script>

<template>
  <div class="layout-wrapper" :class="containerClass">
    <a href="#main-content" class="skip-link">Aller au contenu principal</a>
    <AppTopbar />
    <AppSidebar />
    <div class="layout-main-container">
      <div id="main-content" class="layout-main" role="main">
        <router-view />
      </div>
      <AppFooter />
    </div>
    <div class="layout-mask animate-fadein" @click="hideMobileMenu" aria-hidden="true" />
  </div>
  <Toast />
  <ConfirmDialog />
</template>
