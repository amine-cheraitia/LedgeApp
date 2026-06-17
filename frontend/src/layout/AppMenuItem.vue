<script setup lang="ts">
import { useLayout } from '@/layout/composables/layout'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const { layoutState, isDesktop } = useLayout()
const route = useRoute()

const props = defineProps({
  item: {
    type: Object,
    default: () => ({}),
  },
  root: {
    type: Boolean,
    default: true,
  },
  parentPath: {
    type: String,
    default: null,
  },
})

const fullPath = computed(() =>
  props.item.path
    ? props.parentPath
      ? props.parentPath + props.item.path
      : props.item.path
    : null,
)

const isActive = computed(() =>
  props.item.path
    ? layoutState.activePath?.startsWith(fullPath.value!)
    : layoutState.activePath === props.item.to,
)

// ── Accordéon (groupe racine repliable) ────────────────────────────────────
const isAccordion = computed(() => props.root && !!props.item.accordion)

const hasActiveChild = computed(() =>
  (props.item.items ?? []).some(
    (child: { to?: string }) => child.to && route.path.startsWith(child.to),
  ),
)

const open = ref(false)

onMounted(() => {
  if (isAccordion.value) {
    // defaultOpen → toujours ouvert au chargement ;
    // sinon (ex. Administration) déplié seulement si la page courante appartient au groupe
    open.value = props.item.defaultOpen === true ? true : hasActiveChild.value
  }
})

function toggleAccordion() {
  open.value = !open.value
}

function itemClick(event: Event, item: any) {
  if (item.disabled) {
    event.preventDefault()
    return
  }

  if (item.command) {
    item.command({ originalEvent: event, item })
  }

  if (item.items) {
    if (isActive.value) {
      layoutState.activePath = layoutState.activePath!.replace(item.path, '')
    } else {
      layoutState.activePath = fullPath.value
      layoutState.menuHoverActive = true
    }
  } else {
    layoutState.overlayMenuActive = false
    layoutState.mobileMenuActive = false
    layoutState.menuHoverActive = false
  }
}

function onMouseEnter() {
  if (isDesktop() && props.root && props.item.items && layoutState.menuHoverActive) {
    layoutState.activePath = fullPath.value
  }
}
</script>

<template>
  <!-- Groupe racine repliable (accordéon) -->
  <li
    v-if="isAccordion && item.visible !== false"
    class="layout-root-menuitem"
    :class="{ 'active-menuitem': open }"
  >
    <a
      role="button"
      tabindex="0"
      class="layout-accordion-header"
      :aria-expanded="open"
      @click="toggleAccordion"
      @keydown.enter.prevent="toggleAccordion"
      @keydown.space.prevent="toggleAccordion"
    >
      <i :class="item.icon" class="layout-menuitem-icon" />
      <span class="layout-menuitem-text">{{ item.label }}</span>
      <i class="pi pi-fw pi-angle-down layout-submenu-toggler" />
    </a>
    <Transition name="layout-submenu">
      <ul v-show="open" class="layout-submenu">
        <app-menu-item
          v-for="child in item.items"
          :key="child.label + '_' + (child.to || child.path)"
          :item="child"
          :root="false"
          :parentPath="fullPath ?? undefined"
        />
      </ul>
    </Transition>
  </li>

  <!-- Rendu standard (sections + liens) -->
  <li v-else :class="{ 'layout-root-menuitem': root, 'active-menuitem': isActive }">
    <div v-if="root && item.visible !== false" class="layout-menuitem-root-text">{{ item.label }}</div>
    <a
      v-if="(!item.to || item.items) && item.visible !== false"
      :href="item.url"
      @click="itemClick($event, item)"
      :class="item.class"
      :target="item.target"
      tabindex="0"
      @mouseenter="onMouseEnter"
    >
      <i :class="item.icon" class="layout-menuitem-icon" />
      <span class="layout-menuitem-text">{{ item.label }}</span>
      <i v-if="item.items" class="pi pi-fw pi-angle-down layout-submenu-toggler" />
    </a>
    <router-link
      v-if="item.to && !item.items && item.visible !== false"
      @click="itemClick($event, item)"
      exactActiveClass="active-route"
      :class="item.class"
      tabindex="0"
      :to="item.to"
      @mouseenter="onMouseEnter"
    >
      <i :class="item.icon" class="layout-menuitem-icon" />
      <span class="layout-menuitem-text">{{ item.label }}</span>
      <i v-if="item.items" class="pi pi-fw pi-angle-down layout-submenu-toggler" />
    </router-link>
    <Transition v-if="item.items && item.visible !== false" name="layout-submenu">
      <ul v-show="root ? true : isActive" class="layout-submenu">
        <app-menu-item
          v-for="child in item.items"
          :key="child.label + '_' + (child.to || child.path)"
          :item="child"
          :root="false"
          :parentPath="fullPath ?? undefined"
        />
      </ul>
    </Transition>
  </li>
</template>
