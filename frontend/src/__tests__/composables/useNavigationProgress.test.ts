import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { useNavigationProgress } from '@/composables/useNavigationProgress'

// Etat module singleton (partage router/App.vue) : chaque test part d'un etat
// neutre en terminant la navigation precedente puis en purgeant les timers.
describe('useNavigationProgress', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    const progress = useNavigationProgress()
    progress.done()
    vi.runAllTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it("n'affiche rien si la navigation aboutit avant 150 ms (anti-flash)", () => {
    const { visible, valeur, start, done } = useNavigationProgress()

    start()
    vi.advanceTimersByTime(100)
    expect(visible.value).toBe(false)

    done()
    vi.runAllTimers()
    expect(visible.value).toBe(false)
    expect(valeur.value).toBe(0)
  })

  it('apparait apres 150 ms puis progresse sans jamais depasser 90 %', () => {
    const { visible, valeur, start } = useNavigationProgress()

    start()
    vi.advanceTimersByTime(149)
    expect(visible.value).toBe(false)

    vi.advanceTimersByTime(1)
    expect(visible.value).toBe(true)
    expect(valeur.value).toBe(10)

    vi.advanceTimersByTime(200)
    const apresUnTick = valeur.value
    expect(apresUnTick).toBeGreaterThan(10)

    // Longue attente : la progression est asymptotique, plafonnee a 90 %
    vi.advanceTimersByTime(60_000)
    expect(valeur.value).toBeGreaterThan(apresUnTick)
    expect(valeur.value).toBeLessThanOrEqual(90)
    expect(visible.value).toBe(true)
  })

  it('done() complete a 100 % puis masque la barre apres 250 ms', () => {
    const { visible, valeur, start, done } = useNavigationProgress()

    start()
    vi.advanceTimersByTime(300)
    expect(visible.value).toBe(true)

    done()
    expect(valeur.value).toBe(100)
    expect(visible.value).toBe(true)

    vi.advanceTimersByTime(250)
    expect(visible.value).toBe(false)
    expect(valeur.value).toBe(0)
  })

  it('un nouveau start() repart de zero apres une navigation terminee', () => {
    const { visible, valeur, start, done } = useNavigationProgress()

    start()
    vi.advanceTimersByTime(300)
    done()
    vi.advanceTimersByTime(250)
    expect(visible.value).toBe(false)

    start()
    vi.advanceTimersByTime(149)
    expect(visible.value).toBe(false)
    vi.advanceTimersByTime(1)
    expect(visible.value).toBe(true)
    expect(valeur.value).toBe(10)
  })

  it("start() pendant une barre en cours d'affichage reinitialise la progression", () => {
    const { visible, valeur, start } = useNavigationProgress()

    start()
    vi.advanceTimersByTime(1_000)
    expect(valeur.value).toBeGreaterThan(10)

    // Nouvelle navigation avant la fin de la precedente
    start()
    expect(valeur.value).toBe(0)
    vi.advanceTimersByTime(150)
    expect(visible.value).toBe(true)
    expect(valeur.value).toBe(10)
  })
})
