import { ref, watch } from 'vue'
import type { Ref } from 'vue'

// RGAA / WCAG 2.3.3 — si l'utilisateur a demande a reduire les animations au
// niveau de l'OS, on affiche directement la valeur cible (pas de defilement).
function prefersReducedMotion(): boolean {
  return (
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  )
}

export function useCountUp(target: Ref<number>, duration = 800) {
  const displayed = ref(0)

  function animate(from: number, to: number) {
    const start = performance.now()
    function step(now: number) {
      const progress = Math.min((now - start) / duration, 1)
      const eased = 1 - Math.pow(1 - progress, 3) // ease-out cubic
      displayed.value = Math.round(from + (to - from) * eased)
      if (progress < 1) requestAnimationFrame(step)
    }
    requestAnimationFrame(step)
  }

  watch(
    target,
    (newVal, oldVal) => {
      if (prefersReducedMotion()) {
        displayed.value = newVal
        return
      }
      animate(oldVal ?? 0, newVal)
    },
    { immediate: true },
  )

  return displayed
}
