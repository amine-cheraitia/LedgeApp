<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { authApi } from '@/api/modules/auth'
import { getApiError } from '@/composables/useApiError'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const token = ref((route.query.token as string) ?? '')
const email = ref((route.query.email as string) ?? '')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const submitting = ref(false)

const lienInvalide = computed(() => !token.value || !email.value)

async function onSubmit() {
  error.value = ''

  if (password.value !== passwordConfirmation.value) {
    error.value = 'Les deux mots de passe ne correspondent pas.'
    return
  }

  submitting.value = true
  try {
    await authApi.resetPassword({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    toast.add({
      severity: 'success',
      summary: 'Mot de passe defini',
      detail: 'Vous pouvez maintenant vous connecter.',
      life: 4000,
    })
    router.push('/login')
  } catch (e: unknown) {
    const apiError = getApiError(e)
    error.value =
      apiError.errors?.token?.[0] ??
      apiError.errors?.password?.[0] ??
      apiError.message
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  if (lienInvalide.value) {
    error.value = 'Lien invalide ou incomplet. Demandez un nouveau lien depuis « Mot de passe oublie ».'
  }
})
</script>

<template>
  <div class="auth-page">
    <main class="auth-card" role="main" lang="fr">
      <header class="auth-head">
        <h1 class="auth-h1">Definir votre mot de passe</h1>
        <p class="auth-sub">Choisissez un mot de passe pour activer votre acces Ledge.</p>
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

      <form v-if="!lienInvalide" @submit.prevent="onSubmit" novalidate class="auth-form">
        <div class="auth-field">
          <label for="reset-email" class="auth-label">Adresse e-mail</label>
          <InputText id="reset-email" v-model="email" type="email" autocomplete="username" readonly fluid />
        </div>

        <div class="auth-field">
          <label for="reset-password" class="auth-label">Nouveau mot de passe</label>
          <Password
            id="reset-password"
            v-model="password"
            :toggle-mask="true"
            :feedback="true"
            prompt-label="Saisissez un mot de passe"
            weak-label="Faible"
            medium-label="Moyen"
            strong-label="Fort"
            autocomplete="new-password"
            input-id="reset-password"
            fluid
            :input-props="{ 'aria-required': 'true' }"
            required
          />
          <small class="auth-hint">Au moins 8 caracteres, avec une majuscule et un chiffre.</small>
        </div>

        <div class="auth-field">
          <label for="reset-password-confirm" class="auth-label">Confirmer le mot de passe</label>
          <Password
            id="reset-password-confirm"
            v-model="passwordConfirmation"
            :toggle-mask="true"
            :feedback="false"
            autocomplete="new-password"
            input-id="reset-password-confirm"
            fluid
            :input-props="{ 'aria-required': 'true' }"
            required
          />
        </div>

        <Button
          type="submit"
          label="Definir mon mot de passe"
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
.auth-hint {
  font-size: 0.75rem;
  color: var(--p-text-muted-color, #64748b);
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
:deep(.p-password) {
  width: 100%;
}
</style>
