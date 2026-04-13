import { ref, watch } from 'vue'
import type { Ref } from 'vue'

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

  watch(target, (newVal, oldVal) => animate(oldVal ?? 0, newVal), { immediate: true })

  return displayed
}
