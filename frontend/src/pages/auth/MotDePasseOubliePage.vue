<script setup lang="ts">
import { ref } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { authApi } from '@/api/modules/auth'
import { getApiError } from '@/composables/useApiError'

const email = ref('')
const error = ref('')
const submitted = ref(false)
const submitting = ref(false)

async function onSubmit() {
  error.value = ''
  submitting.value = true
  try {
    await authApi.forgotPassword(email.value)
    // Reponse generique cote back (anti-enumeration) : on confirme toujours.
    submitted.value = true
  } catch (e: unknown) {
    const apiError = getApiError(e)
    error.value =
      apiError.kind === 'throttle'
        ? 'Trop de demandes. Patientez quelques instants avant de reessayer.'
        : apiError.message
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <a href="#forgot-main" class="skip-link">
      Aller au contenu principal
    </a>

    <main id="forgot-main" class="auth-card" role="main" tabindex="-1" lang="fr">
      <header class="auth-head">
        <h1 class="auth-h1">Mot de passe oublie</h1>
        <p class="auth-sub">
          Saisissez votre adresse e-mail : vous recevrez un lien pour definir un nouveau mot de passe.
        </p>
      </header>

      <Message
        v-if="error"
        severity="error"
        :closable="false"
        class="auth-alert"
        role="alert"
        aria-live="assertive"
      >
        {{ error }}
      </Message>

      <Message
        v-if="submitted"
        severity="success"
        :closable="false"
        class="auth-alert"
        role="status"
        aria-live="polite"
      >
        Si un compte existe pour cette adresse, un e-mail de reinitialisation vient d'etre envoye. Pensez a verifier vos courriers indesirables.
      </Message>

      <form v-if="!submitted" @submit.prevent="onSubmit" novalidate class="auth-form">
        <div class="auth-field">
          <label for="forgot-email" class="auth-label">Adresse e-mail</label>
          <InputText
            id="forgot-email"
            v-model="email"
            type="email"
            inputmode="email"
            autocomplete="email"
            placeholder="nom@cabinet.dz"
            fluid
            :aria-required="true"
            required
          />
        </div>

        <Button
          type="submit"
          label="Envoyer le lien"
          :loading="submitting"
          class="auth-submit"
          fluid
        />
      </form>

      <p class="auth-foot">
        <RouterLink to="/login" class="auth-link">Retour a la connexion</RouterLink>
      </p>
    </main>
  </div>
</template>

<style scoped>
.auth-page {
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: clamp(1rem, 4vw, 2rem);
  background: var(--p-surface-50);
}
.app-dark .auth-page {
  background: #111827;
}
.auth-card {
  width: 100%;
  max-width: 24rem;
  background: var(--p-content-background, #fff);
  border: 1px solid var(--p-content-border-color, rgba(128, 128, 128, 0.18));
  border-radius: 0.75rem;
  padding: clamp(1.5rem, 4vw, 2rem);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
}
.auth-head {
  margin-bottom: 1.25rem;
}
.auth-h1 {
  margin: 0 0 0.375rem;
  font-size: clamp(1.35rem, 4vw, 1.65rem);
  font-weight: 600;
  color: var(--p-text-color);
}
.auth-sub {
  margin: 0;
  font-size: 0.9375rem;
  line-height: 1.5;
  color: var(--p-text-muted-color, #475569);
}
.auth-alert {
  margin-bottom: 1rem;
}
.auth-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.auth-field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}
.auth-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--p-text-color);
}
.auth-submit {
  margin-top: 0.25rem;
}
.auth-foot {
  margin: 1.25rem 0 0;
  text-align: center;
  font-size: 0.875rem;
}
.auth-link {
  color: var(--p-primary-color);
  text-decoration: underline;
  text-underline-offset: 3px;
}
.auth-link:focus-visible {
  outline: 2px solid var(--ledge-accent, var(--p-primary-color));
  outline-offset: 2px;
}
</style>
