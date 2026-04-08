import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { resetCsrf } from '@/api/client'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.roles?.includes('admin') ?? false)
  const isClient = computed(() => user.value?.roles?.includes('client') ?? false)
  const isSecretaire = computed(() => user.value?.roles?.includes('secretaire') ?? false)
  const isCollaborateur = computed(() => user.value?.roles?.includes('collaborateur') ?? false)
  const isBackoffice = computed(() => {
    const roles = user.value?.roles ?? []
    return roles.some(r => ['admin', 'collaborateur', 'secretaire'].includes(r))
  })

  function normalizeUser(raw: Record<string, unknown>): User {
    const u = raw as unknown as User
    if (Array.isArray(u.roles) && u.roles.length > 0 && typeof u.roles[0] === 'object') {
      u.roles = (u.roles as unknown as Array<{ name: string }>).map(r => r.name)
    }
    return u
  }

  async function login(email: string, password: string) {
    loading.value = true
    try {
      const { data } = await api.post('/login', { email, password })
      user.value = normalizeUser(data.user)
      return user.value
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    await api.post('/logout')
    user.value = null
    resetCsrf()
  }

  async function fetchUser() {
    try {
      const { data } = await api.get('/me')
      user.value = normalizeUser(data.user)
    } catch {
      user.value = null
    }
  }

  return { user, loading, isAuthenticated, isAdmin, isClient, isSecretaire, isCollaborateur, isBackoffice, login, logout, fetchUser }
})
