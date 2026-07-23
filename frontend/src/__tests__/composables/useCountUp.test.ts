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

// RGAA — prefers-reduced-motion : la valeur cible s'affiche sans defilement.
describe('useCountUp — prefers-reduced-motion', () => {
  beforeEach(() => {
    vi.stubGlobal('matchMedia', vi.fn().mockReturnValue({ matches: true }))
    // rAF volontairement inerte : si l'animation etait lancee, displayed resterait a 0.
    vi.stubGlobal('requestAnimationFrame', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('saute directement a la valeur cible sans animation', async () => {
    const target = ref(340)
    const displayed = useCountUp(target, 800)

    expect(displayed.value).toBe(340)

    target.value = 512
    await nextTick()
    expect(displayed.value).toBe(512)
    expect(requestAnimationFrame).not.toHaveBeenCalled()
  })
})
