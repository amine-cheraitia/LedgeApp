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
    items: [
      { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/' },
      { label: 'KPI Objectifs', icon: 'pi pi-fw pi-chart-bar', to: '/kpi/objectifs', visible: isAdmin.value },
    ],
  },
  {
    label: 'Gestion',
    visible: isStaff.value,
    items: [
      { label: 'Entreprises', icon: 'pi pi-fw pi-building', to: '/entreprises', visible: isAdmin.value || isSecretaire.value },
      { label: 'Missions', icon: 'pi pi-fw pi-briefcase', to: '/missions' },
      { label: 'Planning', icon: 'pi pi-fw pi-calendar-clock', to: '/planning' },
    ],
  },
  {
    label: 'Facturation',
    visible: isAdmin.value || isSecretaire.value,
    items: [
      { label: 'Devis', icon: 'pi pi-fw pi-file', to: '/devis' },
      { label: 'Factures', icon: 'pi pi-fw pi-receipt', to: '/factures' },
      { label: 'Creances', icon: 'pi pi-fw pi-exclamation-circle', to: '/creances' },
      { label: 'Relances', icon: 'pi pi-fw pi-bell', to: '/relances/config' },
    ],
  },
  {
    label: 'Administration',
    visible: isAdmin.value,
    items: [
      { label: 'Utilisateurs', icon: 'pi pi-fw pi-users', to: '/users' },
      { label: 'Exercices', icon: 'pi pi-fw pi-calendar', to: '/exercices' },
      { label: 'Prestations', icon: 'pi pi-fw pi-list', to: '/prestations' },
      { label: 'Parametres', icon: 'pi pi-fw pi-cog', to: '/settings' },
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
