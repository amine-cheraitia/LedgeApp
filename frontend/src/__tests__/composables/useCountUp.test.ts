import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { ref, nextTick } from 'vue'
import { useCountUp } from '@/composables/useCountUp'

// On pilote le temps : requestAnimationFrame avance de 1000ms et execute le callback
// une seule fois (progress >= 1 avec une duree par defaut de 800ms).
describe('useCountUp', () => {
  beforeEach(() => {
    let t = 0
    vi.spyOn(performance, 'now').mockImplementation(() => t)
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      t += 1000
      cb(t)
      return 0
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  it('initialise displayed a 0', () => {
    const displayed = useCountUp(ref(0))

    expect(displayed.value).toBe(0)
  })

  it('converge exactement sur la valeur cible', async () => {
    const target = ref(0)
    const displayed = useCountUp(target, 800)

    target.value = 500
    await nextTick()

    expect(displayed.value).toBe(500)
  })

  it('gere une valeur initiale non nulle', async () => {
    const target = ref(120)
    const displayed = useCountUp(target, 800)
    await nextTick()

    expect(displayed.value).toBe(120)
  })
})
