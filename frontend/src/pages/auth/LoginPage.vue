<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'

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
  <div class="login-page">
    <main class="login-card" role="main">
      <h1 class="login-title">Ledge</h1>
      <p class="login-subtitle">Connexion à votre espace</p>

      <form @submit.prevent="handleLogin" novalidate>
        <Message v-if="error" severity="error" :closable="false" class="login-error">
          {{ error }}
        </Message>

        <div class="form-field">
          <label for="email">Adresse email</label>
          <InputText
            id="email"
            v-model="email"
            type="email"
            autocomplete="email"
            placeholder="admin@ledge.dz"
            required
            fluid
          />
        </div>

        <div class="form-field">
          <label for="password">Mot de passe</label>
          <Password
            id="password"
            v-model="password"
            :feedback="false"
            toggleMask
            autocomplete="current-password"
            required
            fluid
          />
        </div>

        <Button
          type="submit"
          label="Se connecter"
          :loading="auth.loading"
          fluid
          class="login-btn"
        />
      </form>
    </main>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: var(--p-surface-ground);
}

.login-card {
  width: 100%;
  max-width: 24rem;
  background: var(--p-content-background);
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.75rem;
  padding: 2rem;
}

.login-title {
  font-size: 1.5rem;
  font-weight: 700;
  text-align: center;
  margin-bottom: 0.25rem;
}

.login-subtitle {
  text-align: center;
  color: var(--p-text-muted-color);
  margin-bottom: 1.5rem;
}

.login-error {
  margin-bottom: 1rem;
}

.form-field {
  margin-bottom: 1rem;
}

.form-field label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 0.375rem;
}

.login-btn {
  margin-top: 0.5rem;
}
</style>
