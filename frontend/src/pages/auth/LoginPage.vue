<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Dialog from 'primevue/dialog'
import { useAuthStore } from '@/stores/auth'
import { getApiError } from '@/composables/useApiError'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')

const aideDialogVisible = ref(false)
const aideDialogTitle = ref('')
const aideDialogText = ref('')

function openAideConnexion(): void {
  aideDialogTitle.value = 'Aide à la connexion'
  aideDialogText.value =
    'Ledge distingue l’espace cabinet (personnel) et le portail client. Si vous ne parvenez pas à vous connecter, vérifiez vos identifiants ou contactez l’administrateur du cabinet.'
  aideDialogVisible.value = true
}

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
  <div class="login-page">
    <a href="#login-main" class="skip-link">
      Aller au formulaire de connexion
    </a>

    <div class="login-shell">
      <!-- Branding : pleine largeur mobile (bandeau), ~60 % desktop -->
      <aside class="login-brand" aria-label="Présentation de Ledge">
        <div class="login-brand-inner">
          <div class="login-brand-lockup">
            <svg
              class="login-brand-grid"
              width="48"
              height="48"
              viewBox="0 0 48 48"
              aria-hidden="true"
              focusable="false"
            >
              <rect x="4" y="4" width="18" height="18" rx="5" fill="currentColor" opacity="0.88" />
              <rect x="26" y="4" width="18" height="18" rx="5" fill="currentColor" opacity="0.65" />
              <rect x="4" y="26" width="18" height="18" rx="5" fill="currentColor" opacity="0.65" />
              <rect x="26" y="26" width="18" height="18" rx="5" fill="currentColor" opacity="0.88" />
            </svg>
            <span class="login-brand-wordmark">Ledge</span>
          </div>

          <p class="login-brand-tagline">
            Gestion intégrée pour cabinets algériens
          </p>
          <p class="login-brand-pills">
            <span>Facturation</span>
            <span class="login-brand-dot" aria-hidden="true">·</span>
            <span>Planning</span>
            <span class="login-brand-dot" aria-hidden="true">·</span>
            <span>Comptabilité</span>
            <span class="login-brand-dot" aria-hidden="true">·</span>
            <span>KPI</span>
          </p>

          <p class="login-brand-meta">
            Ledge v2 · RNCP 39583
          </p>
        </div>
      </aside>

      <!-- Formulaire : zone principale mobile-first -->
      <main
        id="login-main"
        class="login-main"
        role="main"
        tabindex="-1"
        lang="fr"
      >
        <div class="login-main-card">
          <header class="login-form-head">
            <h1 class="login-h1">Connexion</h1>
            <p id="login-subtitle" class="login-subtitle">Espace cabinet</p>
          </header>

          <Message
            v-if="error"
            severity="error"
            :closable="false"
            class="login-alert"
            role="alert"
            aria-live="assertive"
          >
            <span class="login-alert-text">{{ error }}</span>
          </Message>

          <form
            @submit.prevent="handleLogin"
            novalidate
            class="login-form"
            aria-describedby="login-subtitle login-form-hint"
          >
            <p id="login-form-hint" class="sr-only">
              Champs obligatoires : adresse e-mail et mot de passe. Format e-mail attendu : nom@cabinet.dz.
            </p>

            <div class="login-field">
              <label for="email" class="login-label">Adresse e-mail</label>
              <div class="login-input-wrap">
                <span class="login-input-icon" aria-hidden="true">
                  <i class="pi pi-envelope" />
                </span>
                <InputText
                  id="email"
                  v-model="email"
                  type="email"
                  inputmode="email"
                  placeholder="nom@cabinet.dz"
                  autocomplete="email"
                  class="w-full login-field-input login-field-input--email"
                  :aria-invalid="!!error"
                  aria-required="true"
                  required
                />
              </div>
            </div>

            <div class="login-field">
              <label for="password" class="login-label">Mot de passe</label>
              <div class="login-input-wrap login-input-wrap--password">
                <span class="login-input-icon" aria-hidden="true">
                  <i class="pi pi-lock" />
                </span>
                <Password
                  id="password"
                  v-model="password"
                  placeholder="••••••••"
                  :toggle-mask="true"
                  :feedback="false"
                  autocomplete="current-password"
                  class="login-password-field"
                  fluid
                  :input-class="'login-field-input login-field-input--pass'"
                  :input-props="{
                    'aria-invalid': !!error,
                    'aria-required': 'true',
                  }"
                  required
                />
              </div>
            </div>

            <Button
              type="submit"
              label="Se connecter"
              :loading="auth.loading"
              class="w-full login-submit"
              aria-label="Se connecter à Ledge"
            />

            <div class="login-forgot-wrap">
              <RouterLink to="/mot-de-passe-oublie" class="login-text-btn">
                Mot de passe oublié ?
              </RouterLink>
            </div>
          </form>

          <hr class="login-sep" aria-hidden="true" />

          <footer class="login-foot">
            <p class="login-foot-line">Accès réservé au personnel du cabinet</p>
            <p class="login-foot-version">Ledge v2 · RNCP 39583</p>
          </footer>
        </div>

        <button
          type="button"
          class="login-help-fab"
          aria-label="Afficher l’aide à la connexion"
          @click="openAideConnexion"
        >
          <i class="pi pi-question" aria-hidden="true" />
        </button>
      </main>
    </div>

    <Dialog
      v-model:visible="aideDialogVisible"
      :header="aideDialogTitle"
      modal
      :dismissable-mask="true"
      :style="{ width: 'min(100vw - 2rem, 28rem)' }"
      :breakpoints="{ '576px': '95vw' }"
      :content-class="'login-aide-dialog'"
    >
      <p class="login-aide-body">{{ aideDialogText }}</p>
      <template #footer>
        <Button
          label="Fermer"
          severity="secondary"
          aria-label="Fermer la fenêtre d’aide"
          @click="aideDialogVisible = false"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
/* ── Mobile-first : une colonne, bandeau branding en haut ───────────── */
.login-page {
  min-height: 100vh;
  min-height: 100dvh;
}

.login-shell {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-height: 100dvh;
}

@media (min-width: 768px) {
  .login-shell {
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
    align-items: stretch;
  }
}

/* ── Panneau branding (navy plein contraste WCAG sur texte clair) ──── */
.login-brand {
  position: relative;
  background: linear-gradient(165deg, #0b1220 0%, #0f172a 50%, #172554 100%);
  color: #f8fafc;
  padding: clamp(1.25rem, 4vw, 2.5rem) clamp(1rem, 4vw, 2.5rem);
  flex-shrink: 0;
}

.login-brand-inner {
  max-width: 36rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.75rem;
}

@media (min-width: 768px) {
  .login-brand-inner {
    margin: 0;
    min-height: 100%;
    justify-content: center;
    align-items: flex-start;
    text-align: left;
    padding: 0 clamp(0.5rem, 2vw, 2rem) 0 0;
    gap: 1rem;
  }
}

.login-brand-lockup {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.login-brand-grid {
  flex-shrink: 0;
  color: #e2e8f0;
}

.login-brand-wordmark {
  font-family: var(--ledge-ff-body);
  font-weight: 700;
  font-size: clamp(1.75rem, 5vw, 2.35rem);
  letter-spacing: -0.03em;
  line-height: 1;
  color: #ffffff;
}

.login-brand-tagline {
  margin: 0;
  font-family: var(--ledge-ff-body);
  font-size: clamp(0.9rem, 2.8vw, 1.05rem);
  line-height: 1.5;
  /* #e2e8f0 sur #0f172a ≈ contraste élevé */
  color: #e2e8f0;
  max-width: 28rem;
}

.login-brand-pills {
  margin: 0.25rem 0 0;
  font-family: var(--ledge-ff-body);
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.6;
  color: #cbd5e1;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.35rem;
}

@media (min-width: 768px) {
  .login-brand-pills {
    justify-content: flex-start;
  }
}

.login-brand-dot {
  color: #94a3b8;
  user-select: none;
}

.login-brand-meta {
  display: none;
  margin: 2rem 0 0;
  font-family: var(--ledge-ff-mono);
  font-size: 0.7rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #94a3b8;
}

@media (min-width: 768px) {
  .login-brand-meta {
    display: block;
  }
}

/* ── Colonne formulaire ─────────────────────────────────────────────── */
.login-main {
  position: relative;
  flex: 1;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: clamp(1.5rem, 4vw, 2.75rem) clamp(1rem, 4vw, 2rem);
  padding-bottom: max(1.5rem, env(safe-area-inset-bottom, 0px));
  background: var(--p-surface-50);
}

/* Dark mode : #111827 au lieu de #020617 (surface-950 trop sombre,
   inputs et bg indiscernables) */
.app-dark .login-main {
  background: #111827;
}

/* Dark mode : card formulaire avec surface levée + bordure subtile
   pour distinguer le contenu du fond */
.app-dark .login-main-card {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: var(--ledge-radius-md);
  padding: 1.5rem;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

/* Dark mode : séparation panneau brand / formulaire sur desktop */
@media (min-width: 768px) {
  .app-dark .login-brand {
    border-right: 1px solid rgba(255, 255, 255, 0.07);
  }
}

@media (min-width: 768px) {
  .login-main {
    align-items: center;
  }
}

.login-main-card {
  width: 100%;
  max-width: 22rem;
}

.login-form-head {
  margin-bottom: 1.5rem;
}

.login-h1 {
  font-family: var(--ledge-ff-display);
  font-weight: 500;
  font-size: clamp(1.5rem, 4vw, 1.85rem);
  line-height: 1.15;
  letter-spacing: -0.02em;
  color: var(--p-text-color);
  margin: 0 0 0.375rem;
}

/* Sous-titre : #334155 sur fond clair ≥ 4.5:1 */
.login-subtitle {
  margin: 0;
  font-family: var(--ledge-ff-body);
  font-size: 0.9375rem;
  font-weight: 500;
  line-height: 1.4;
  color: #334155;
}

.app-dark .login-subtitle {
  color: #cbd5e1;
}

.login-alert {
  margin-bottom: 1.25rem;
}

.login-alert :deep(.p-message-text),
.login-alert-text {
  word-break: break-word;
  overflow-wrap: anywhere;
}

.login-field {
  margin-bottom: 1.125rem;
}

.login-label {
  display: block;
  margin-bottom: 0.375rem;
  font-family: var(--ledge-ff-body);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--p-text-color);
}

/* Champs avec icône — cible tactile min ~44px hauteur sur mobile */
.login-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.login-input-icon {
  position: absolute;
  left: 0.85rem;
  z-index: 1;
  display: flex;
  color: #64748b;
  pointer-events: none;
  font-size: 0.95rem;
}

.app-dark .login-input-icon {
  color: #94a3b8;
}

/* InputText est rendu comme <input class="p-inputtext login-field-input"> (même élément)
   → cibler la classe directement, pas en descendant */
:deep(.login-field-input--email) {
  width: 100%;
  min-height: 2.75rem;
  padding-left: 2.65rem;
  border-radius: var(--ledge-radius-md);
  font-size: 1rem;
}

.login-password-field :deep(.p-password-input) {
  width: 100%;
  min-height: 2.75rem;
  padding-left: 2.65rem;
  border-radius: var(--ledge-radius-md);
  font-size: 1rem;
}

.login-password-field {
  width: 100%;
}

.login-password-field :deep(.p-password) {
  width: 100%;
}

.login-password-field :deep(.p-password-input) {
  padding-right: 2.75rem;
}

.login-input-wrap--password .login-input-icon {
  z-index: 2;
}

:deep(.login-field-input--email):focus-visible,
.login-password-field :deep(.p-password-input):focus-visible {
  outline: 2px solid var(--ledge-accent);
  outline-offset: 2px;
}

:deep(.login-field-input--email):-webkit-autofill,
.login-password-field :deep(.p-password-input):-webkit-autofill {
  -webkit-box-shadow: 0 0 0 1000px var(--p-inputtext-background, var(--p-content-background)) inset !important;
  -webkit-text-fill-color: var(--p-inputtext-color, var(--p-text-color)) !important;
  transition: background-color 9999s ease-out;
}

/* Bouton CTA — accent chaud (Trust & Authority : warm vibrant CTA) */
/* :deep(.login-submit) cible l'élément racine du composant Button */
:deep(.login-submit) {
  margin-top: 0.25rem;
  min-height: 2.75rem;
  border-radius: var(--ledge-radius-md);
  font-weight: 600;
  font-size: 1rem;
  background: var(--ledge-accent) !important;
  border-color: var(--ledge-accent) !important;
  color: #ffffff !important;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

:deep(.login-submit:not(:disabled):hover) {
  background: #a13209 !important;
  border-color: #a13209 !important;
}

:deep(.login-submit:focus-visible) {
  outline: 2px solid var(--ledge-accent);
  outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
  :deep(.login-submit) {
    transition: none;
  }
}

.login-forgot-wrap {
  margin-top: 1rem;
  text-align: center;
}

.login-text-btn {
  padding: 0.5rem 0.75rem;
  margin: 0 -0.75rem;
  border: none;
  background: none;
  font-family: var(--ledge-ff-body);
  font-size: 0.875rem;
  font-weight: 500;
  color: #0f172a;
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
  border-radius: var(--ledge-radius-sm);
}

/* Dark : lien neutre lisible, pas de bleu incohérent avec l'accent orange */
.app-dark .login-text-btn {
  color: #e2e8f0;
}

.login-text-btn:focus-visible {
  outline: 2px solid var(--ledge-accent);
  outline-offset: 2px;
}

.login-sep {
  margin: 1.5rem 0;
  border: none;
  border-top: 1px solid var(--p-surface-200);
}

.app-dark .login-sep {
  border-top-color: var(--p-surface-700);
}

.login-foot {
  text-align: center;
}

.login-foot-line {
  margin: 0 0 0.35rem;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: #475569;
  font-family: var(--ledge-ff-body);
}

.app-dark .login-foot-line {
  color: #94a3b8;
}

/* #475569 sur fond clair = 5.8:1 ✅ (vs #64748b = 4.3:1 fail WCAG petit texte) */
.login-foot-version {
  margin: 0;
  font-family: var(--ledge-ff-mono);
  font-size: 0.6875rem;
  letter-spacing: 0.04em;
  color: #475569;
}

.app-dark .login-foot-version {
  color: #94a3b8;
}

@media (min-width: 768px) {
  .login-foot-version {
    display: none;
  }
}

/* FAB aide — 44×44 mini, focus visible */
.login-help-fab {
  position: fixed;
  right: max(1rem, env(safe-area-inset-right, 0px));
  bottom: max(1rem, env(safe-area-inset-bottom, 0px));
  z-index: 50;
  width: 2.75rem;
  height: 2.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: none;
  background: #0f172a;
  color: #fff;
  font-size: 1.1rem;
  cursor: pointer;
  box-shadow: 0 4px 14px rgb(15 23 42 / 0.35);
  transition: transform 0.2s ease, background-color 0.2s ease;
}

.login-help-fab:hover {
  background: #1e293b;
}

.login-help-fab:focus-visible {
  outline: 2px solid var(--ledge-accent);
  outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
  .login-help-fab {
    transition: none;
  }
}

/* Dark : accent orange cohérent avec le CTA, pas l'emerald PrimeVue */
.app-dark .login-help-fab {
  background: var(--ledge-accent);
  box-shadow: 0 4px 14px rgba(194, 65, 12, 0.4);
}

.login-aide-body {
  margin: 0;
  font-size: 0.9375rem;
  line-height: 1.55;
  color: var(--p-text-color);
}
</style>
