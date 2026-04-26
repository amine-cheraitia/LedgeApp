<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getApiError } from '@/composables/useApiError'
import LedgeLogo from '@/components/LedgeLogo.vue'

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
  <div class="login-scene bg-surface-50 dark:bg-surface-950">
    <div class="login-wrap">

      <!-- Brand mark -->
      <div class="login-brand">
        <LedgeLogo :size="36" :with-wordmark="true" />
      </div>

      <!-- Card -->
      <div class="login-card bg-surface-0 dark:bg-surface-900">

        <!-- Card header -->
        <div class="login-card-head">
          <p class="ledger-eyebrow">Connexion</p>
          <h1 class="login-title">Bienvenue sur Ledge</h1>
          <p class="login-lede">Connectez-vous pour accéder à votre espace.</p>
        </div>

        <!-- Error -->
        <Message
          v-if="error"
          severity="error"
          :closable="false"
          class="login-error"
          role="alert"
          aria-live="assertive"
        >
          <span class="login-error-text">{{ error }}</span>
        </Message>

        <form @submit.prevent="handleLogin" novalidate class="login-form">
          <div class="login-field">
            <label for="email" class="login-label">Adresse email</label>
            <InputText
              id="email"
              v-model="email"
              type="email"
              placeholder="admin@ledge.dz"
              autocomplete="email"
              class="w-full login-input"
              aria-label="Adresse email"
              :aria-invalid="!!error"
              required
            />
          </div>

          <div class="login-field">
            <label for="password" class="login-label">Mot de passe</label>
            <Password
              id="password"
              v-model="password"
              placeholder="••••••••"
              :toggleMask="true"
              :feedback="false"
              autocomplete="current-password"
              class="login-password"
              fluid
              aria-label="Mot de passe"
              :aria-invalid="!!error"
              required
            />
          </div>

          <Button
            type="submit"
            label="Se connecter"
            :loading="auth.loading"
            class="w-full login-btn"
            aria-label="Se connecter"
          />
        </form>
      </div>

      <!-- Footer legal -->
      <p class="login-footer">
        Cabinet de conseil &amp; comptabilité — Ledge v2
      </p>
    </div>
  </div>
</template>

<style scoped>
/* ── Scene ──────────────────────────────────────────────────────────── */
.login-scene {
  min-height: 100vh;
  min-width: 100vw;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
}

.login-wrap {
  width: 100%;
  max-width: 26rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
}

/* ── Brand mark ─────────────────────────────────────────────────────── */
.login-brand {
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ── Card ───────────────────────────────────────────────────────────── */
.login-card {
  width: 100%;
  border-top: 3px solid var(--ledge-accent);
  border-radius: var(--ledge-radius-sm, 2px);
  padding: 2rem 2rem 2.5rem;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.08);
}

/* ── Card header (centers inline-flex eyebrow + title + lede) ──────── */
.login-card-head {
  text-align: center;
  margin-bottom: 1.75rem;
}

.login-card-head .ledger-eyebrow {
  margin-bottom: 0.5rem;
}

/* ── Title ──────────────────────────────────────────────────────────── */
.login-title {
  font-family: var(--ledge-ff-display);
  font-weight: 400;
  font-size: 1.65rem;
  line-height: 1.15;
  letter-spacing: -0.02em;
  color: var(--p-text-color);
  margin: 0 0 0.5rem;
  text-align: center;
  font-variation-settings: 'opsz' 144, 'SOFT' 30;
}

/* ── Lede ───────────────────────────────────────────────────────────── */
.login-lede {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
  text-align: center;
  margin: 0;
  line-height: 1.5;
}

/* ── Error ──────────────────────────────────────────────────────────── */
.login-error {
  margin-bottom: 1.25rem;
}

.login-error :deep(.p-message-text),
.login-error-text {
  display: block;
  word-break: break-word;
  overflow-wrap: anywhere;
  max-width: 100%;
  white-space: pre-line;
}

/* ── Form ───────────────────────────────────────────────────────────── */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.login-field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin-bottom: 1.25rem;
}

/* ── Labels — mono uppercase ────────────────────────────────────────── */
.login-label {
  font-family: var(--ledge-ff-mono);
  font-size: 0.62rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--p-text-muted-color);
}

/* ── CTA button ─────────────────────────────────────────────────────── */
.login-btn {
  margin-top: 0.25rem;
}

/* ── Footer ─────────────────────────────────────────────────────────── */
.login-footer {
  font-family: var(--ledge-ff-mono);
  font-size: 0.6rem;
  color: var(--p-text-muted-color);
  text-align: center;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin: 0;
  opacity: 0.6;
}

/* ── Autofill override (Chrome/Edge jaune-olive) ────────────────────── */
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

/* ── Focus visible RGAA ─────────────────────────────────────────────── */
.login-input :deep(input):focus-visible,
.login-password :deep(input):focus-visible {
  outline: 2px solid var(--ledge-accent);
  outline-offset: 2px;
}
</style>
