import { describe, it, expect, vi } from 'vitest'
import type { Ref } from 'vue'
import type { SecretaireStats } from '@/api/modules/stats'

// ── Mock du count-up : rend les valeurs KPI deterministes (pas d'animation) ──
vi.mock('@/composables/useCountUp', () => ({
  useCountUp: (target: Ref<number>) => target,
}))

import SecretaireDashboardSection from '@/pages/dashboard/SecretaireDashboardSection.vue'
import { mountPage } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
function makeStats(overrides: Partial<SecretaireStats> = {}): SecretaireStats {
  return {
    alertes: [
      { type: 'danger', message: 'Créances critiques détectées' },
      { type: 'warn', message: 'Relances en attente' },
      { type: 'info', message: 'Nouveau paiement enregistré' },
    ],
    actions: [
      { key: 'relances', label: 'Relances à envoyer', count: 4, severity: 'danger', icon: 'pi-bell', route: '/relances' },
      { key: 'devis', label: 'Devis à transmettre', count: 1, severity: 'info', icon: 'pi-send', route: '/devis' },
    ],
    creances: { total_impaye: 1250000, clients_debiteurs: 4, en_retard: 3 },
    aging: { retard_15_30: 50000, retard_30_60: 30000, retard_60_plus: 120000 },
    relances_dues: { niveau_1: 2, niveau_2: 1, niveau_3: 1 },
    top_debiteurs: [
      { entreprise_id: 7, raison_sociale: 'SARL Atlas Conseil', montant_impaye: 800000 },
      { entreprise_id: 9, raison_sociale: 'EURL Sahara Btp', montant_impaye: 400000 },
    ],
    encaissements_mois: { count: 5, montant: 300000 },
    recentes_creances: [
      {
        id: 1, numero: 'FF2026-001', entreprise: 'SARL Atlas Conseil',
        montant_restant: 45000, date_echeance: '2026-05-01', statut_paiement: 'en_attente', en_retard: true,
      },
      {
        id: 2, numero: 'FF2026-002', entreprise: null,
        montant_restant: 10000, date_echeance: null, statut_paiement: 'inconnu', en_retard: false,
      },
    ],
    ...overrides,
  }
}

const emptyStats: SecretaireStats = {
  alertes: [],
  actions: [],
  creances: { total_impaye: 0, clients_debiteurs: 0, en_retard: 0 },
  aging: { retard_15_30: 0, retard_30_60: 0, retard_60_plus: 0 },
  relances_dues: { niveau_1: 0, niveau_2: 0, niveau_3: 0 },
  top_debiteurs: [],
  encaissements_mois: { count: 0, montant: 0 },
  recentes_creances: [],
}

async function mountSection(stats: SecretaireStats) {
  return mountPage(SecretaireDashboardSection, { role: 'secretaire', props: { stats } })
}

describe('SecretaireDashboardSection — bandeau et alertes', () => {
  it('affiche les alertes avec une zone role=alert', async () => {
    const { wrapper } = await mountSection(makeStats())

    const zone = wrapper.find('[role="alert"]')
    expect(zone.exists()).toBe(true)
    expect(wrapper.text()).toContain('Créances critiques détectées')
    expect(wrapper.text()).toContain('Relances en attente')
    expect(wrapper.text()).toContain('Nouveau paiement enregistré')
  })

  it('affiche la synthèse (à recouvrer, encaissé, relances au pluriel) et l alerte créances critiques', async () => {
    const { wrapper } = await mountSection(makeStats())
    const text = wrapper.text()

    expect(text).toContain('Facturation & recouvrement')
    expect(text).toContain('à recouvrer')
    expect(text).toContain('encaissé ce mois')
    // total relances = 2 + 1 + 1 = 4 -> pluriel
    expect(text).toMatch(/4\s*relances dues/)
    // retard_60_plus > 0 -> alerte critique
    expect(text).toContain('Créances critiques')
    expect(text).toContain('à plus de 60 jours')
    expect(wrapper.find('.reco-alert--ok').exists()).toBe(false)
  })

  it('affiche "Sous contrôle" quand aucune créance > 60 jours', async () => {
    const { wrapper } = await mountSection(emptyStats)

    expect(wrapper.text()).toContain('Sous contrôle')
    expect(wrapper.text()).toContain('Aucune créance > 60 jours')
    expect(wrapper.find('.reco-alert--ok').exists()).toBe(true)
  })
})

describe('SecretaireDashboardSection — worklist « À faire »', () => {
  it('liste les actions avec compteur et lien de navigation', async () => {
    const { wrapper } = await mountSection(makeStats())

    expect(wrapper.text()).toContain('À faire')
    expect(wrapper.text()).toMatch(/2\s*points d'attention/)
    expect(wrapper.text()).toContain('Relances à envoyer')
    expect(wrapper.text()).toContain('Devis à transmettre')

    const liens = wrapper.findAll('a.reco-todo')
    expect(liens).toHaveLength(2)
    expect(liens[0].attributes('href')).toBe('/relances')
    expect(liens[1].attributes('href')).toBe('/devis')
  })

  it('affiche un message positif quand il n y a rien à faire', async () => {
    const { wrapper } = await mountSection(emptyStats)

    expect(wrapper.text()).toContain("Rien d'urgent — tout est à jour.")
    expect(wrapper.findAll('a.reco-todo')).toHaveLength(0)
  })
})

describe('SecretaireDashboardSection — cartes KPI', () => {
  it('affiche créances, relances par niveau et encaissements avec aria-labels', async () => {
    const { wrapper } = await mountSection(makeStats())
    const text = wrapper.text()

    expect(text).toMatch(/4\s*débiteurs/)
    expect(text).toMatch(/3\s*en retard/)
    expect(text).toContain('N1 : 2')
    expect(text).toContain('N2 : 1')
    expect(text).toContain('N3 : 1')
    expect(text).toMatch(/5\s*paiements/)

    // aria-labels RGAA construits depuis les stats brutes (pas la valeur animee)
    const label = wrapper.find('[aria-label^="Créances totales"]')
    expect(label.exists()).toBe(true)
    expect(label.attributes('aria-label')).toContain('DA')

    // Cartes KPI en formatDAKpi : compact >= 1M (creances 1 250 000), entier sinon (encaisse 300 000)
    expect(text).toMatch(/1,25\s*M DA/)
    expect(text).toMatch(/300\s*000\s*DA/)
    expect(label.attributes('title')).toMatch(/1\s*250\s*000,00\s*DA/)
  })
})

describe('SecretaireDashboardSection — aging des créances', () => {
  it('rend les 3 tranches avec pourcentage du total et aria-label descriptif', async () => {
    const { wrapper } = await mountSection(makeStats())
    const text = wrapper.text()

    expect(text).toContain('15 – 29 jours')
    expect(text).toContain('30 – 59 jours')
    expect(text).toContain('60 jours et +')
    // 50000 / 200000 = 25 %, 120000 / 200000 = 60 %
    expect(text).toContain('25% du total')
    expect(text).toContain('60% du total')

    const chart = wrapper.find('ul.reco-bars')
    expect(chart.attributes('aria-label')).toContain('Aging des créances')
    // largeur relative au max (120000)
    const buckets = (wrapper.vm as any).agingBuckets
    expect(buckets[2].width).toBe(100)
    expect(buckets[0].width).toBe(Math.round((50000 / 120000) * 100))
  })

  it('affiche un état vide quand aucune créance en retard (pct = 0)', async () => {
    const { wrapper } = await mountSection(emptyStats)

    expect(wrapper.text()).toContain('Aucune créance en retard.')
    expect(wrapper.find('ul.reco-bars').exists()).toBe(false)
    // le computed reste correct meme sans rendu
    const buckets = (wrapper.vm as any).agingBuckets
    expect(buckets.every((b: { pct: number }) => b.pct === 0)).toBe(true)
  })
})

describe('SecretaireDashboardSection — donut relances', () => {
  it('rend un segment par niveau actif et la légende complète', async () => {
    const { wrapper } = await mountSection(makeStats())

    expect(wrapper.findAll('.reco-donut__seg')).toHaveLength(3)
    expect(wrapper.text()).toContain('Niveau 1')
    expect(wrapper.text()).toContain('Niveau 3')
    const donut = wrapper.find('.reco-donut-wrap')
    expect(donut.attributes('aria-label')).toContain('Relances dues')
    expect(donut.attributes('aria-label')).toContain('Niveau 1, 2')
  })

  it('gère le cas d une seule relance (singulier, pas d espace inter-segment)', async () => {
    const { wrapper } = await mountSection(makeStats({ relances_dues: { niveau_1: 1, niveau_2: 0, niveau_3: 0 } }))

    expect(wrapper.text()).toMatch(/1\s*relance due(?!s)/)
    const segments = (wrapper.vm as any).relancesSegments
    expect(segments).toHaveLength(1)
    // segment unique = cercle complet (2 * PI * 38), sans gap
    expect(segments[0].dasharray.startsWith('238.7')).toBe(true)
  })

  it('affiche un état vide quand aucune relance due', async () => {
    const { wrapper } = await mountSection(emptyStats)

    expect(wrapper.text()).toContain('Aucune relance à envoyer.')
    expect((wrapper.vm as any).relancesSegments).toEqual([])
    expect(wrapper.find('.reco-donut').exists()).toBe(false)
  })
})
