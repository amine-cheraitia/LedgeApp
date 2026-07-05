import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Activity } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockGetAll } = vi.hoisted(() => ({
  mockGetAll: vi.fn(),
}))

vi.mock('@/api/modules/audit', () => ({
  auditApi: { getAll: mockGetAll },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import AuditLogPage from '@/pages/audit/AuditLogPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const actCreation: Activity = {
  id: 1,
  event: 'created',
  description: 'created',
  log_name: 'default',
  subject_type: 'Facture',
  subject_id: 12,
  causer: { id: 1, name: 'Amine Cheraiti' },
  changes: { attributes: { numero: 'FF2026-001', montant_ttc: 119000 }, old: {} },
  created_at: '2026-06-01T10:00:00Z',
}

const actModification: Activity = {
  id: 2,
  event: 'updated',
  description: 'updated',
  log_name: 'default',
  subject_type: 'User',
  subject_id: 3,
  causer: { id: 2, name: 'Sarah Secretaire' },
  changes: {
    attributes: { statut: 'partiel', meta: { plan: 'pro' } },
    old: { statut: null, ancien_champ: 'x' },
  },
  created_at: '2026-06-02T14:30:00Z',
}

const actSysteme: Activity = {
  id: 3,
  event: null,
  description: 'system',
  log_name: null,
  subject_type: 'Setting',
  subject_id: null,
  causer: null,
  changes: {} as Activity['changes'],
  created_at: '2026-06-03T08:00:00Z',
}

function boutonsDetail(wrapper: any) {
  return wrapper.findAll('button').filter(
    (b: any) => b.attributes('aria-label')?.startsWith('Voir le detail'),
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetAll.mockResolvedValue({
    data: [actCreation, actModification, actSysteme],
    meta: { current_page: 1, last_page: 4, per_page: 15, total: 60 },
  })
})

describe('AuditLogPage — liste', () => {
  it('charge le journal page 1 et affiche utilisateurs, actions et entites', async () => {
    const { wrapper } = await mountPage(AuditLogPage)

    expect(mockGetAll).toHaveBeenCalledWith(
      expect.objectContaining({ page: 1, per_page: 15, subject_type: undefined, event: undefined }),
    )
    expect(wrapper.text()).toContain("Journal d'audit")
    expect(wrapper.text()).toContain('Amine Cheraiti')
    expect(wrapper.text()).toContain('Creation')
    expect(wrapper.text()).toContain('Modification')
    // Libelles d entites traduits + id
    expect(wrapper.text()).toContain('Facture')
    expect(wrapper.text()).toContain('#12')
    expect(wrapper.text()).toContain('Utilisateur')
    // Activite systeme : causer null -> 'Systeme', event null -> '—'
    expect(wrapper.text()).toContain('Systeme')
    expect(wrapper.text()).toContain('—')
    // Dates 2026 formatees
    expect(wrapper.text()).toContain('2026')
  })

  it('expose des filtres accessibles (role search + labels sr-only)', async () => {
    const { wrapper } = await mountPage(AuditLogPage)

    const toolbar = wrapper.find('[role="search"]')
    expect(toolbar.exists()).toBe(true)
    expect(toolbar.attributes('aria-label')).toBe("Filtres du journal d'audit")
    expect(wrapper.find('label[for="f-entite"]').exists()).toBe(true)
    expect(wrapper.find('label[for="f-debut"]').exists()).toBe(true)
  })

  it('affiche le message vide quand aucune activite ne correspond', async () => {
    mockGetAll.mockResolvedValue({ data: [], meta: { total: 0 } })
    const { wrapper } = await mountPage(AuditLogPage)

    expect(wrapper.text()).toContain('Aucune action enregistree pour ces criteres.')
  })

  it('retombe sur data.length quand la reponse ne contient pas de meta', async () => {
    mockGetAll.mockResolvedValue({ data: [actCreation] })
    const { wrapper } = await mountPage(AuditLogPage)

    expect((wrapper.vm as any).totalRecords).toBe(1)
  })

  it('affiche un toast erreur si le chargement echoue', async () => {
    mockGetAll.mockRejectedValue(new Error('500'))
    await mountPage(AuditLogPage)

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: "Impossible de charger le journal d'audit." }),
    )
  })
})

describe('AuditLogPage — pagination et filtres', () => {
  it('recharge la page demandee lors du changement de page', async () => {
    const { wrapper } = await mountPage(AuditLogPage)

    ;(wrapper.vm as any).onPage({ page: 1 })
    await flushPromises()

    expect(mockGetAll).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2 }))
  })

  it('relance la recherche page 1 quand un filtre change (entite + action)', async () => {
    const { wrapper } = await mountPage(AuditLogPage)
    const vm = wrapper.vm as any

    // Se place d abord en page 2
    vm.onPage({ page: 1 })
    await flushPromises()

    vm.filtres.subject_type = 'Facture'
    vm.filtres.event = 'deleted'
    await flushPromises()

    expect(mockGetAll).toHaveBeenLastCalledWith(
      expect.objectContaining({ page: 1, subject_type: 'Facture', event: 'deleted' }),
    )
  })

  it('convertit les dates de filtre en ISO (yyyy-mm-dd)', async () => {
    const { wrapper } = await mountPage(AuditLogPage)
    const vm = wrapper.vm as any

    vm.filtres.date_debut = new Date(Date.UTC(2026, 0, 5))
    vm.filtres.date_fin = new Date(Date.UTC(2026, 1, 28))
    await flushPromises()

    expect(mockGetAll).toHaveBeenLastCalledWith(
      expect.objectContaining({ date_debut: '2026-01-05', date_fin: '2026-02-28' }),
    )
  })
})

describe('AuditLogPage — dialog detail', () => {
  it('ouvre le detail et affiche les champs modifies avant/apres', async () => {
    const { wrapper } = await mountPage(AuditLogPage)

    // 2e bouton = activite 'updated' avec attributes + old
    await boutonsDetail(wrapper)[1].trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain("Detail de l'action")
    expect(wrapper.text()).toContain('Champs modifies')
    expect(wrapper.text()).toContain('Sarah Secretaire')
    // Champ modifie : old null -> '—', apres 'partiel'
    expect(wrapper.text()).toContain('statut')
    expect(wrapper.text()).toContain('partiel')
    // Objet serialise en JSON
    expect(wrapper.text()).toContain('{"plan":"pro"}')
    // Champ present uniquement dans old -> apres '—'
    expect(wrapper.text()).toContain('ancien_champ')
    expect(wrapper.text()).toContain('—')
  })

  it("affiche un message quand l action n a aucun detail de champ", async () => {
    const { wrapper } = await mountPage(AuditLogPage)

    // 3e bouton = activite systeme sans changes
    await boutonsDetail(wrapper)[2].trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Aucun detail de champ disponible pour cette action.')
    expect(wrapper.text()).toContain('Systeme')
  })

  it('detailRows est vide tant qu aucune activite n est selectionnee', async () => {
    const { wrapper } = await mountPage(AuditLogPage)

    expect((wrapper.vm as any).detailRows).toEqual([])
  })

  it('le bouton detail porte un aria-label explicite (RGAA)', async () => {
    const { wrapper } = await mountPage(AuditLogPage)

    const bouton = findButton(wrapper, 'Voir le detail')
    expect(bouton).toBeTruthy()
    expect(bouton!.attributes('aria-label')).toContain('2026')
  })
})
