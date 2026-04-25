<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getApiError } from '@/composables/useApiError'
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
  } catch (e: unknown) {
    const apiError = getApiError(e)
    if (apiError.kind === 'validation' || apiError.status === 422) {
      error.value = 'Identifiants incorrects.'
    } else {
      error.value = apiError.message
    }
  }
}
</script>

<template>
  <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen min-w-screen overflow-hidden">
    <FloatingConfigurator />
    <div class="flex flex-col items-center justify-center w-full max-w-xl px-4">
      <div class="w-full" style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, var(--p-primary-color) 10%, rgba(33, 150, 243, 0) 30%)">
        <div class="w-full bg-surface-0 dark:bg-surface-900 py-12 sm:py-20 px-6 sm:px-12 md:px-20" style="border-radius: 53px">
          <div class="text-center mb-8">
            <div class="mb-4">
              <i class="pi pi-briefcase" style="font-size: 3rem; color: var(--p-primary-color)"></i>
            </div>
            <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">Bienvenue sur Ledge</div>
            <span class="text-muted-color font-medium">Connectez-vous pour acceder a votre espace</span>
          </div>

          <form @submit.prevent="handleLogin" novalidate>
            <Message
              v-if="error"
              severity="error"
              :closable="false"
              class="mb-6 login-error"
              role="alert"
              aria-live="assertive"
              :aria-describedby="undefined"
            >
              <span class="login-error-text">{{ error }}</span>
            </Message>

            <label for="email" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Adresse email</label>
            <InputText
              id="email"
              v-model="email"
              type="email"
              placeholder="admin@ledge.dz"
              autocomplete="email"
              class="w-full mb-8 login-input"
              aria-label="Adresse email"
              :aria-invalid="!!error"
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
              class="mb-8 login-password"
              fluid
              aria-label="Mot de passe"
              :aria-invalid="!!error"
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

<style scoped>
/* Empeche un long message d'erreur de casser le layout en largeur */
.login-error :deep(.p-message-text),
.login-error-text {
  display: block;
  word-break: break-word;
  overflow-wrap: anywhere;
  max-width: 100%;
  white-space: pre-line;
}

/* Surcharge l'autofill Chrome/Edge qui force un fond jaune-olive
   illisible par-dessus les themes clair/sombre */
.login-input :deep(input):-webkit-autofill,
.login-input :deep(input):-webkit-autofill:hover,
.login-input :deep(input):-webkit-autofill:focus,
.login-password :deep(input):-webkit-autofill,
.login-password :deep(input):-webkit-autofill:hover,
.login-password :deep(input):-webkit-autofill:focus {
  -webkit-box-shadow: 0 0 0 1000px var(--p-inputtext-background, var(--p-content-background)) inset !important;
  -webkit-text-fill-color: var(--p-inputtext-color, var(--p-text-color)) !important;
  caret-color: var(--p-inputtext-color, var(--p-text-color));
  transition: background-color 9999s ease-in-out 0s;
}

/* Focus visible RGAA */
.login-input :deep(input):focus-visible,
.login-password :deep(input):focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}
</style>
