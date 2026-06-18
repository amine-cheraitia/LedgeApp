<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import AppMenuItem from './AppMenuItem.vue'

const auth = useAuthStore()

const isAdmin = computed(() => auth.user?.roles?.includes('admin'))
const isCollaborateur = computed(() => auth.user?.roles?.includes('collaborateur'))
const isSecretaire = computed(() => auth.user?.roles?.includes('secretaire'))
const isStaff = computed(() => isAdmin.value || isCollaborateur.value || isSecretaire.value)

const model = computed(() => [
  {
    label: 'Accueil',
    accordion: true,
    defaultOpen: true,
    icon: 'pi pi-fw pi-th-large',
    items: [
      { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/' },
    ],
  },
  {
    label: 'Gestion',
    visible: isStaff.value,
    accordion: true,
    defaultOpen: true,
    icon: 'pi pi-fw pi-folder',
    items: [
      { label: 'Entreprises', icon: 'pi pi-fw pi-building', to: '/entreprises', visible: isAdmin.value || isSecretaire.value },
      { label: 'Missions', icon: 'pi pi-fw pi-briefcase', to: '/missions', visible: isAdmin.value || isCollaborateur.value },
      { label: 'Planning', icon: 'pi pi-fw pi-calendar-clock', to: '/planning', visible: isAdmin.value || isCollaborateur.value },
    ],
  },
  {
    label: 'Facturation',
    visible: isAdmin.value || isSecretaire.value,
    accordion: true,
    defaultOpen: true,
    icon: 'pi pi-fw pi-credit-card',
    items: [
      { label: 'Devis', icon: 'pi pi-fw pi-file', to: '/devis' },
      { label: 'Factures', icon: 'pi pi-fw pi-receipt', to: '/factures' },
      { label: 'Creances', icon: 'pi pi-fw pi-exclamation-circle', to: '/creances' },
      { label: 'Relances', icon: 'pi pi-fw pi-bell', to: '/relances/config', visible: isAdmin.value },
    ],
  },
  {
    label: 'Administration',
    visible: isAdmin.value,
    accordion: true,
    icon: 'pi pi-fw pi-cog',
    items: [
      { label: 'Prestations', icon: 'pi pi-fw pi-list', to: '/prestations' },
      { label: 'Parametres', icon: 'pi pi-fw pi-sliders-h', to: '/settings' },
      { label: 'Taux de TVA', icon: 'pi pi-fw pi-percentage', to: '/tva-taux' },
      { label: 'Exercices', icon: 'pi pi-fw pi-calendar', to: '/exercices' },
      { label: 'Utilisateurs', icon: 'pi pi-fw pi-users', to: '/users' },
      { label: 'KPI Objectifs', icon: 'pi pi-fw pi-chart-bar', to: '/kpi/objectifs' },
      { label: "Journal d'audit", icon: 'pi pi-fw pi-history', to: '/audit-logs' },
    ],
  },
])
</script>

<template>
  <ul class="layout-menu">
    <template v-for="(item, i) in model" :key="i">
      <app-menu-item v-if="item.visible !== false" :item="item" :index="i" />
    </template>
  </ul>
</template>
