import { describe, it, expect, vi, beforeEach, beforeAll } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Entreprise, Facture, Mission, Prestation, Tache } from '@/types'

// ── Mock du client axios (le module @/api/modules/portail reel s'execute) ────
const { mockGet, mockPost } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: {
    get: mockGet,
    post: mockPost,
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
  resetCsrf: vi.fn(),
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Imports APRES les mocks ──────────────────────────────────────────────────
import PortailDashboard from '@/pages/portail/PortailDashboard.vue'
import PortailMissionsPage from '@/pages/portail/PortailMissionsPage.vue'
import PortailFacturesPage from '@/pages/portail/PortailFacturesPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
function makeEntreprise(overrides: Partial<Entreprise> = {}): Entreprise {
  return {
    id: 1,
    raison_sociale: 'SARL Atlas Conseil',
    nif: '099912345678901',
    nis: null,
    num_rc: null,
    article_imposition: null,
    regime_fiscal: 'reel',
    categorie: 'pme',
    secteur_activite: null,
    adresse: null,
    ville: 'Alger',
    wilaya: null,
    telephone: null,
    email: null,
    contact_principal: null,
    statut: 'client',
    notes: null,
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

const prestation: Prestation = {
  id: 1, code: 'ACMPT', designation: 'Assistance comptable',
  tarif_initial: 120000, duree_mois: 12, description: null, actif: true,
  created_at: '', updated_at: '',
}

function makeTache(id: number, statut: Tache['statut'], titre: string): Tache {
  return {
    id, mission_id: 1, assigned_to: null, titre, description: null, statut,
    date_debut: null, date_echeance: null, priorite: 2, created_at: '', updated_at: '',
  }
}

function makeMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 1, entreprise_id: 1, exercice_id: 1, prestation_id: 1,
    reference: 'M2026-001', convention_numero: 'CV2026-001', mandat_numero: 'MD2026-001',
    visible_portail: true, prix_ht: 315000, date_debut: '2026-01-15', date_fin: null,
    statut: 'en_cours', notes: null, prestation,
    taches: [makeTache(1, 'terminee', 'Collecte documents'), makeTache(2, 'en_cours', 'Saisie comptable')],
    created_at: '', updated_at: '',
    ...overrides,
  }
}

function makeFacture(overrides: Partial<Facture> = {}): Facture {
  return {
    id: 1, entreprise_id: 1, exercice_id: 1, mission_id: 1, devis_id: null, created_by: 1,
    tva_rate_id: 1, numero: 'FF2026-001', type: 'FF', facture_origine_id: null,
    date_facture: '2026-02-01', date_echeance: '2026-03-01',
    montant_ht: 100000, taux_tva: 19, montant_tva: 19000, montant_ttc: 119000,
    montant_paye: 0, statut_paiement: 'en_attente', mode_paiement: 'non_defini',
    montant_restant: 119000, pdf_path: null, created_at: '', updated_at: '',
    ...overrides,
  }
}

function paginated<T>(rows: T[]) {
  return { data: { data: rows, meta: { current_page: 1, last_page: 1, per_page: 15, total: rows.length } } }
}

/** Capture les <a> crees par les telechargements PDF (blob) et neutralise click(). */
function captureAnchors() {
  const anchors: HTMLAnchorElement[] = []
  const original = document.createElement.bind(document)
  const spy = vi.spyOn(document, 'createElement').mockImplementation(((tagName: string, options?: ElementCreationOptions) => {
    const el = original(tagName as never, options)
    if (String(tagName).toLowerCase() === 'a') {
      vi.spyOn(el as unknown as HTMLAnchorElement, 'click').mockImplementation(() => {})
      anchors.push(el as unknown as HTMLAnchorElement)
    }
    return el
  }) as never)
  return { anchors, restore: () => spy.mockRestore() }
}

const createObjectURL = vi.fn(() => 'blob:mock-url')
const revokeObjectURL = vi.fn()

beforeAll(() => {
  Object.assign(URL, { createObjectURL, revokeObjectURL })
})

beforeEach(() => {
  vi.clearAllMocks()
})

// ─────────────────────────────────────────────────────────────────────────────
describe('PortailDashboard', () => {
  it("affiche le nom du client, l'entreprise et les infos cabinet (NIF, ville)", async () => {
    const { wrapper } = await mountPage(PortailDashboard, {
      role: 'client',
      user: { name: 'Karim Client', entreprise: makeEntreprise() },
    })

    expect(wrapper.text()).toContain('Bienvenue')
    expect(wrapper.text()).toContain('Karim Client')
    expect(wrapper.text()).toContain('SARL Atlas Conseil')
    expect(wrapper.text()).toContain('Informations cabinet')
    expect(wrapper.text()).toContain('reel')
    expect(wrapper.text()).toContain('pme')
    expect(wrapper.text()).toContain('099912345678901')
    expect(wrapper.text()).toContain('Alger')
    // Acces rapide : les 2 cartes
    expect(wrapper.text()).toContain('Mes factures')
    expect(wrapper.text()).toContain('Mes missions')
  })

  it("masque NIF et ville quand l'entreprise ne les renseigne pas", async () => {
    const { wrapper } = await mountPage(PortailDashboard, {
      role: 'client',
      user: { entreprise: makeEntreprise({ nif: null, ville: null }) },
    })

    expect(wrapper.text()).toContain('Informations cabinet')
    expect(wrapper.text()).not.toContain('NIF')
    expect(wrapper.text()).not.toContain('Ville')
  })

  it("affiche le repli 'votre entreprise' et masque la section infos sans entreprise", async () => {
    const { wrapper } = await mountPage(PortailDashboard, { role: 'client' })

    expect(wrapper.text()).toContain('votre entreprise')
    expect(wrapper.text()).not.toContain('Informations cabinet')
  })

  it('navigue vers /portail/factures au clic sur la carte Mes factures', async () => {
    const { wrapper, router } = await mountPage(PortailDashboard, {
      role: 'client',
      user: { entreprise: makeEntreprise() },
    })

    const btn = findButton(wrapper, 'Accéder à Mes factures')
    expect(btn, 'bouton avec aria-label RGAA').toBeTruthy()
    await btn!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/portail/factures')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
describe('PortailMissionsPage', () => {
  function mockMissions(list: Mission[], detail?: Mission) {
    mockGet.mockImplementation((url: string) => {
      if (url === '/portail/missions') return Promise.resolve(paginated(list))
      if (detail && url === `/portail/missions/${detail.id}`) {
        return Promise.resolve({ data: { data: detail } })
      }
      return Promise.reject(new Error(`GET inattendu: ${url}`))
    })
  }

  it("charge les missions et affiche reference, statut et avancement", async () => {
    mockMissions([
      makeMission(), // 1 tache terminee sur 2 -> 50%
      makeMission({ id: 2, reference: 'M2026-002', statut: 'terminee', prestation: undefined, taches: undefined }),
    ])
    const { wrapper } = await mountPage(PortailMissionsPage, { role: 'client' })

    expect(mockGet).toHaveBeenCalledWith('/portail/missions', {
      params: expect.objectContaining({ page: 1, per_page: 15 }),
    })
    expect(wrapper.text()).toContain('Mes missions')
    expect(wrapper.text()).toContain('M2026-001')
    expect(wrapper.text()).toContain('Assistance comptable')
    expect(wrapper.text()).toContain('En cours')
    expect(wrapper.text()).toContain('50%')
    // Mission sans prestation ni taches
    expect(wrapper.text()).toContain('M2026-002')
    expect(wrapper.text()).toContain('Terminée')
    expect(wrapper.text()).toContain('—')
    expect(wrapper.text()).toContain('0%')
    // RGAA : barre de progression accessible
    expect(wrapper.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('50')
  })

  it('affiche un toast erreur si le chargement echoue', async () => {
    mockGet.mockRejectedValue(new Error('500'))
    await mountPage(PortailMissionsPage, { role: 'client' })

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
      severity: 'error',
      detail: 'Impossible de charger les missions.',
    }))
  })

  it('relance la recherche avec le filtre statut et la pagination', async () => {
    mockMissions([makeMission()])
    const { wrapper } = await mountPage(PortailMissionsPage, { role: 'client' })
    const vm = wrapper.vm as never as { filtreStatut: string | null; fetchMissions: () => Promise<void>; onPage: (e: { page: number }) => void }

    vm.filtreStatut = 'terminee'
    await vm.fetchMissions()
    expect(mockGet).toHaveBeenLastCalledWith('/portail/missions', {
      params: expect.objectContaining({ statut: 'terminee' }),
    })

    vm.onPage({ page: 1 })
    await flushPromises()
    expect(mockGet).toHaveBeenLastCalledWith('/portail/missions', {
      params: expect.objectContaining({ page: 2 }),
    })
  })

  it('ouvre le detail : taches visibles puis telechargement du rapport PDF', async () => {
    const detail = makeMission({ date_fin: '2026-12-31' })
    mockMissions([makeMission()], detail)
    const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)

    const { wrapper } = await mountPage(PortailMissionsPage, { role: 'client' })
    await findButton(wrapper, 'Voir le détail de la mission M2026-001')!.trigger('click')
    await flushPromises()

    expect(mockGet).toHaveBeenCalledWith('/portail/missions/1')
    expect(wrapper.text()).toContain('Tâches (2)')
    expect(wrapper.text()).toContain('Collecte documents')
    expect(wrapper.text()).toContain('Saisie comptable')
    expect(wrapper.text()).toContain('Fin')
    expect(wrapper.text()).toContain('2026-12-31')

    await findButton(wrapper, 'Télécharger le rapport')!.trigger('click')
    expect(openSpy).toHaveBeenCalledWith('/api/v1/portail/missions/1/rapport/pdf', '_blank')
    openSpy.mockRestore()
  })

  it('affiche un message quand la mission ne comporte aucune tache', async () => {
    const detail = makeMission({ taches: [] })
    mockMissions([makeMission()], detail)
    const { wrapper } = await mountPage(PortailMissionsPage, { role: 'client' })

    await findButton(wrapper, 'Voir le détail de la mission M2026-001')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Aucune tâche pour cette mission.')
  })

  it('ferme le dialog et affiche un toast si le detail echoue', async () => {
    const mission = makeMission()
    mockGet.mockImplementation((url: string) =>
      url === '/portail/missions' ? Promise.resolve(paginated([mission])) : Promise.reject(new Error('500')),
    )
    const { wrapper } = await mountPage(PortailMissionsPage, { role: 'client' })
    const vm = wrapper.vm as never as { openDetail: (m: Mission) => Promise<void>; detailVisible: boolean }

    await vm.openDetail(mission)
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
      severity: 'error',
      detail: 'Impossible de charger le détail.',
    }))
    expect(vm.detailVisible).toBe(false)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
describe('PortailFacturesPage', () => {
  function mockFactures(list: Facture[], pdfOk = true) {
    mockGet.mockImplementation((url: string) => {
      if (url === '/portail/factures') return Promise.resolve(paginated(list))
      if (/^\/portail\/factures\/\d+\/pdf$/.test(url)) {
        return pdfOk ? Promise.resolve({ data: 'PDF-BYTES' }) : Promise.reject(new Error('500'))
      }
      return Promise.reject(new Error(`GET inattendu: ${url}`))
    })
  }

  it('charge les factures et affiche montants en DA et statuts', async () => {
    mockFactures([
      makeFacture(), // en_attente, restant > 0
      makeFacture({ id: 2, numero: 'FF2026-002', statut_paiement: 'solde', montant_restant: 0 }),
    ])
    const { wrapper } = await mountPage(PortailFacturesPage, { role: 'client' })

    expect(mockGet).toHaveBeenCalledWith('/portail/factures', {
      params: expect.objectContaining({ page: 1, per_page: 15 }),
    })
    expect(wrapper.text()).toContain('Mes factures')
    expect(wrapper.text()).toContain('FF2026-001')
    expect(wrapper.text()).toContain('FF2026-002')
    expect(wrapper.text()).toContain('DA')
    expect(wrapper.text()).toContain('En attente')
    expect(wrapper.text()).toContain('Soldé')
    // Restant du > 0 mis en evidence
    expect(wrapper.find('.text-danger').exists()).toBe(true)
    // RGAA : label du filtre
    expect(wrapper.find('label[for="filtre-statut"]').text()).toContain('Filtrer par statut')
  })

  it('affiche un toast erreur si le chargement echoue', async () => {
    mockGet.mockRejectedValue(new Error('500'))
    await mountPage(PortailFacturesPage, { role: 'client' })

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
      severity: 'error',
      detail: 'Impossible de charger les factures.',
    }))
  })

  it('telecharge le PDF de la facture (blob + nom de fichier)', async () => {
    mockFactures([makeFacture()])
    const { wrapper } = await mountPage(PortailFacturesPage, { role: 'client' })

    const { anchors, restore } = captureAnchors()
    await findButton(wrapper, 'Télécharger la facture FF2026-001')!.trigger('click')
    await flushPromises()
    restore()

    expect(mockGet).toHaveBeenCalledWith('/portail/factures/1/pdf', { responseType: 'blob' })
    expect(createObjectURL).toHaveBeenCalled()
    expect(anchors.at(-1)!.download).toBe('facture-FF2026-001.pdf')
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:mock-url')
  })

  it('affiche un toast erreur si le telechargement du PDF echoue', async () => {
    mockFactures([makeFacture()], false)
    const { wrapper } = await mountPage(PortailFacturesPage, { role: 'client' })

    await findButton(wrapper, 'Télécharger la facture FF2026-001')!.trigger('click')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
      severity: 'error',
      detail: 'Impossible de télécharger le PDF.',
    }))
  })

  it('filtre par statut de paiement et pagine', async () => {
    mockFactures([makeFacture()])
    const { wrapper } = await mountPage(PortailFacturesPage, { role: 'client' })
    const vm = wrapper.vm as never as { filtreStatut: string | null; fetchFactures: () => Promise<void>; onPage: (e: { page: number }) => void }

    vm.filtreStatut = 'solde'
    await vm.fetchFactures()
    expect(mockGet).toHaveBeenLastCalledWith('/portail/factures', {
      params: expect.objectContaining({ statut_paiement: 'solde' }),
    })

    vm.onPage({ page: 2 })
    await flushPromises()
    expect(mockGet).toHaveBeenLastCalledWith('/portail/factures', {
      params: expect.objectContaining({ page: 3 }),
    })
  })
})

// ─────────────────────────────────────────────────────────────────────────────
