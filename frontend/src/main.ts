import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
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

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(PrimeVue, {
  theme: {
    preset: Aura,
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
