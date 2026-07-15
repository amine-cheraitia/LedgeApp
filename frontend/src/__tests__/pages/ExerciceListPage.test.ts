import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Exercice } from '@/types'

// ── Mocks API (hoistes) — le module est utilise par useExercices ET par la page (rapport PDF)
const { mockGetAll, mockGetCurrent, mockCreate, mockUpdate, mockRapportPdf } = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
  mockGetCurrent: vi.fn(),
  mockCreate: vi.fn(),
  mockUpdate: vi.fn(),
  mockRapportPdf: vi.fn(),
}))

vi.mock('@/api/modules/exercices', () => ({
  exercicesApi: {
    getAll: mockGetAll,
    getCurrent: mockGetCurrent,
    create: mockCreate,
    update: mockUpdate,
    rapportCloturePdf: mockRapportPdf,
  },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import ExerciceListPage from '@/pages/exercices/ExerciceListPage.vue'
import { mountPage, findButton } from '../helpers/mount'

const exercices: Exercice[] = [
  { id: 1, annee: 2026, date_ouverture: '2026-01-01', date_cloture: '2026-12-31', statut: 'ouvert', created_at: '', updated_at: '' },
  { id: 2, annee: 2025, date_ouverture: '2025-01-01', date_cloture: '2025-12-31', statut: 'cloture', created_at: '', updated_at: '' },
]

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({ data: exercices })
})

describe('ExerciceListPage — liste', () => {
  it('charge et affiche les exercices avec leur statut', async () => {
    const { wrapper } = await mountPage(ExerciceListPage)

    expect(mockGetAll).toHaveBeenCalledOnce()
    expect(wrapper.text()).toContain('Exercices fiscaux')
    expect(wrapper.text()).toContain('2026')
    expect(wrapper.text()).toContain('2025')
    expect(wrapper.text()).toContain('ouvert')
    expect(wrapper.text()).toContain('cloture')
  })
})

describe('ExerciceListPage — creation', () => {
  it('ouvre le dialog, soumet avec les dates converties en ISO et ferme', async () => {
    mockCreate.mockResolvedValue({ data: { id: 3 } })
    const { wrapper } = await mountPage(ExerciceListPage)

    await findButton(wrapper, 'Nouvel exercice')!.trigger('click')
    await flushPromises()
    expect(wrapper.find('#ex-annee').exists()).toBe(true)

    // DatePicker reels : on sette les refs du vm (pattern accepte)
    const vm = wrapper.vm as any
    vm.dateOuverture = new Date('2027-01-01T00:00:00Z')
    vm.dateCloture = new Date('2027-12-31T00:00:00Z')
    vm.form.annee = 2027

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalledWith({
      annee: 2027,
      date_ouverture: '2027-01-01',
      date_cloture: '2027-12-31',
      statut: 'ouvert',
    })
    // Refetch apres creation puis fermeture
    expect(mockGetAll).toHaveBeenCalledTimes(2)
    expect(wrapper.find('#ex-annee').exists()).toBe(false)
  })

  // Regression : le DatePicker emet une Date a minuit LOCAL. L'ancien code passait
  // par toISOString() (UTC) -> en UTC+1 (Algerie), 01/01/2025 devenait 2024-12-31
  // et 31/12/2025 devenait 2025-12-30. Les dates locales doivent partir telles quelles.
  it('n applique aucun decalage UTC : minuit local reste le meme jour (J-1 bug)', async () => {
    mockCreate.mockResolvedValue({ data: { id: 3 } })
    const { wrapper } = await mountPage(ExerciceListPage)

    await findButton(wrapper, 'Nouvel exercice')!.trigger('click')
    await flushPromises()

    const vm = wrapper.vm as any
    // new Date(annee, mois, jour) = minuit LOCAL — exactement ce qu'emet le DatePicker
    vm.dateOuverture = new Date(2025, 0, 1)
    vm.dateCloture = new Date(2025, 11, 31)
    vm.form.annee = 2025

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalledWith(
      expect.objectContaining({ date_ouverture: '2025-01-01', date_cloture: '2025-12-31' }),
    )
  })

  it("garde le dialog ouvert si la creation echoue (dates vides -> '')", async () => {
    mockCreate.mockRejectedValue(new Error('422'))
    const { wrapper } = await mountPage(ExerciceListPage)

    await findButton(wrapper, 'Nouvel exercice')!.trigger('click')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    // Sans dates choisies, toIsoDate(null) renvoie ''
    expect(mockCreate).toHaveBeenCalledWith(expect.objectContaining({ date_ouverture: '', date_cloture: '' }))
    expect(wrapper.find('#ex-annee').exists()).toBe(true)
  })
})

describe('ExerciceListPage — modification (cloture)', () => {
  it('pre-remplit le formulaire et envoie la mise a jour', async () => {
    mockUpdate.mockResolvedValue({ data: exercices[0] })
    const { wrapper } = await mountPage(ExerciceListPage)

    await findButton(wrapper, "Modifier l'exercice")!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain("Modifier l'exercice")

    const vm = wrapper.vm as any
    expect(vm.form.annee).toBe(2026)
    // Cloture de l'exercice
    vm.form.statut = 'cloture'

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockUpdate).toHaveBeenCalledOnce()
    const [id, payload] = mockUpdate.mock.calls[0]
    expect(id).toBe(1)
    expect(payload).toMatchObject({
      annee: 2026,
      statut: 'cloture',
      date_ouverture: '2026-01-01',
      date_cloture: '2026-12-31',
    })
    expect(mockGetAll).toHaveBeenCalledTimes(2)
  })
})

describe('ExerciceListPage — rapport de cloture PDF', () => {
  beforeEach(() => {
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:rapport')
    globalThis.URL.revokeObjectURL = vi.fn()
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
  })

  it('telecharge le rapport PDF pour un admin', async () => {
    mockRapportPdf.mockResolvedValue(new Blob(['%PDF'], { type: 'application/pdf' }))
    const { wrapper } = await mountPage(ExerciceListPage, { role: 'admin' })

    const pdfButton = findButton(wrapper, 'Télécharger le rapport de clôture 2026')
    expect(pdfButton).toBeDefined()
    await pdfButton!.trigger('click')
    await flushPromises()

    expect(mockRapportPdf).toHaveBeenCalledWith(1)
    expect(globalThis.URL.createObjectURL).toHaveBeenCalled()
    expect(globalThis.URL.revokeObjectURL).toHaveBeenCalledWith('blob:rapport')
    expect((wrapper.vm as any).downloadingId).toBeNull()
  })

  it("reste silencieux si l'API du rapport echoue", async () => {
    mockRapportPdf.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountPage(ExerciceListPage)

    await findButton(wrapper, 'Télécharger le rapport de clôture 2025')!.trigger('click')
    await flushPromises()

    expect(mockRapportPdf).toHaveBeenCalledWith(2)
    expect(globalThis.URL.createObjectURL).not.toHaveBeenCalled()
    expect((wrapper.vm as any).downloadingId).toBeNull()
  })

  it('masque le bouton rapport PDF pour un non-admin', async () => {
    const { wrapper } = await mountPage(ExerciceListPage, { role: 'collaborateur' })

    expect(findButton(wrapper, 'Télécharger le rapport de clôture')).toBeUndefined()
    // Le bouton modifier reste accessible
    expect(findButton(wrapper, "Modifier l'exercice")).toBeDefined()
  })
})
