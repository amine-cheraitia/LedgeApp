import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/client'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.roles?.includes('admin') ?? false)
  const isClient = computed(() => user.value?.roles?.includes('client') ?? false)
  const isBackoffice = computed(() => {
    const roles = user.value?.roles ?? []
    return roles.some(r => ['admin', 'collaborateur', 'secretaire'].includes(r))
  })

  async function login(email: string, password: string) {
    loading.value = true
    try {
      const { data } = await api.post('/login', { email, password })
      user.value = data.user
      return data.user
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    await api.post('/logout')
    user.value = null
  }

  async function fetchUser() {
    try {
      const { data } = await api.get('/me')
      user.value = data.user
    } catch {
      user.value = null
    }
  }

  return { user, loading, isAuthenticated, isAdmin, isClient, isBackoffice, login, logout, fetchUser }
})
