import { readonly, ref } from 'vue'

// Etat module unique (singleton) : le router declenche start/done, App.vue affiche.
// Couvre la phase invisible des loaders de pages : chargement des chunks lazy
// et resolution de session, AVANT le montage du composant de destination.
const visible = ref(false)
const valeur = ref(0)

let showTimer: ReturnType<typeof setTimeout> | null = null
let trickleTimer: ReturnType<typeof setInterval> | null = null
let hideTimer: ReturnType<typeof setTimeout> | null = null

function resetTimers(): void {
  if (showTimer) {
    clearTimeout(showTimer)
    showTimer = null
  }
  if (trickleTimer) {
    clearInterval(trickleTimer)
    trickleTimer = null
  }
  if (hideTimer) {
    clearTimeout(hideTimer)
    hideTimer = null
  }
}

export function useNavigationProgress() {
  function start(): void {
    resetTimers()
    valeur.value = 0
    // N'affiche la barre que si la navigation depasse 150 ms : les pages deja
    // en cache naviguent instantanement, sans flash parasite.
    showTimer = setTimeout(() => {
      visible.value = true
      valeur.value = 10
      // Progression asymptotique vers 90 % en attendant la fin reelle de la
      // navigation (les 10 % restants sont completes par done()).
      trickleTimer = setInterval(() => {
        valeur.value = Math.min(90, valeur.value + (90 - valeur.value) * 0.15)
      }, 200)
    }, 150)
  }

  function done(): void {
    const etaitVisible = visible.value
    resetTimers()
    if (!etaitVisible) {
      valeur.value = 0
      return
    }
    valeur.value = 100
    hideTimer = setTimeout(() => {
      visible.value = false
      valeur.value = 0
    }, 250)
  }

  return { visible: readonly(visible), valeur: readonly(valeur), start, done }
}
