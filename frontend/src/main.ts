import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import { definePreset } from '@primeuix/themes'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import StyleClass from 'primevue/styleclass'
import 'primeicons/primeicons.css'
import '@/assets/styles/tokens.css'
import '@/assets/styles/tailwind.css'
import '@/assets/styles/main.css'
import '@/assets/styles/layout.scss'
import '@/assets/styles/editorial.scss'

import App from './App.vue'
import router from './router'

/**
 * Identite Ledge — navy / slate monochrome (aucun orange).
 * primary = surface (slate) -> boutons/liens navy ; surface = slate pour les
 * DEUX modes (Aura mappe le dark sur zinc par defaut -> on force slate = navy).
 */
const slate = {
  0: '#ffffff', 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1',
  400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334155', 800: '#1e293b',
  900: '#0f172a', 950: '#020617',
}

const LedgePreset = definePreset(Aura, {
  semantic: {
    primary: {
      50: '{surface.50}', 100: '{surface.100}', 200: '{surface.200}', 300: '{surface.300}',
      400: '{surface.400}', 500: '{surface.500}', 600: '{surface.600}', 700: '{surface.700}',
      800: '{surface.800}', 900: '{surface.900}', 950: '{surface.950}',
    },
    colorScheme: {
      light: {
        surface: slate,
        primary: { color: '{primary.800}', contrastColor: '#ffffff', hoverColor: '{primary.700}', activeColor: '{primary.900}' },
        highlight: { background: '{primary.100}', focusBackground: '{primary.200}', color: '{primary.900}', focusColor: '{primary.900}' },
      },
      dark: {
        surface: slate,
        primary: { color: '{primary.600}', contrastColor: '#ffffff', hoverColor: '{primary.700}', activeColor: '{primary.800}' },
        highlight: { background: 'color-mix(in srgb, {primary.300}, transparent 82%)', focusBackground: 'color-mix(in srgb, {primary.300}, transparent 74%)', color: 'rgba(255,255,255,.92)', focusColor: '#ffffff' },
      },
    },
  },
})

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(PrimeVue, {
  theme: {
    preset: LedgePreset,
    options: {
      darkModeSelector: '.app-dark',
    },
  },
  locale: {
    accept: 'Accepter',
    reject: 'Refuser',
    choose: 'Choisir',
    cancel: 'Annuler',
    dayNames: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
    dayNamesShort: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
    dayNamesMin: ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'],
    monthNames: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
    monthNamesShort: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
    today: "Aujourd'hui",
    clear: 'Effacer',
    dateFormat: 'dd/mm/yy',
    firstDayOfWeek: 1,
  },
})
app.use(ToastService)
app.use(ConfirmationService)
app.directive('styleclass', StyleClass)

app.mount('#app')
