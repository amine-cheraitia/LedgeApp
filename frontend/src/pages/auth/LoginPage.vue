<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import FloatingConfigurator from '@/components/FloatingConfigurator.vue'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')

async function handleLogin() {
  error.value = ''
  try {
    const user = await auth.login(email.value, password.value)
    const isClient = user.roles?.includes('client')
    router.push(isClient ? '/portail' : '/')
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Identifiants incorrects.'
  }
}
</script>

<template>
  <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen min-w-[100vw] overflow-hidden">
    <FloatingConfigurator />
    <div class="flex flex-col items-center justify-center">
      <div style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, var(--p-primary-color) 10%, rgba(33, 150, 243, 0) 30%)">
        <div class="w-full bg-surface-0 dark:bg-surface-900 py-20 px-8 sm:px-20" style="border-radius: 53px">
          <div class="text-center mb-8">
            <div class="mb-4">
              <i class="pi pi-briefcase" style="font-size: 3rem; color: var(--p-primary-color)"></i>
            </div>
            <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">Bienvenue sur Ledge</div>
            <span class="text-muted-color font-medium">Connectez-vous pour acceder a votre espace</span>
          </div>

          <form @submit.prevent="handleLogin" novalidate>
            <Message v-if="error" severity="error" :closable="false" class="mb-6" role="alert" aria-live="polite">
              {{ error }}
            </Message>

            <label for="email" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Adresse email</label>
            <InputText
              id="email"
              v-model="email"
              type="email"
              placeholder="admin@ledge.dz"
              autocomplete="email"
              class="w-full md:w-[30rem] mb-8"
              aria-label="Adresse email"
              required
            />

            <label for="password" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Mot de passe</label>
            <Password
              id="password"
              v-model="password"
              placeholder="Mot de passe"
              :toggleMask="true"
              :feedback="false"
              autocomplete="current-password"
              class="mb-8"
              fluid
              aria-label="Mot de passe"
              required
            />

            <Button
              type="submit"
              label="Se connecter"
              :loading="auth.loading"
              class="w-full"
              aria-label="Se connecter"
            />
          </form>
        </div>
      </div>
    </div>
  </div>
</template>
