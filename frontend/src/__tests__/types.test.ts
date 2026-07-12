import { describe, it, expect } from 'vitest'
import type { Facture, Devis, Paiement, Entreprise, PaginatedResponse } from '@/types'

describe('TypeScript interfaces', () => {
  it('Entreprise has required fields', () => {
    const entreprise: Entreprise = {
      id: 1,
      raison_sociale: 'Test SARL',
      nif: '123456789',
      nis: null,
      num_rc: null,
      article_imposition: null,
      regime_fiscal: 'forfait',
      categorie: 'TPE',
      secteur_activite: null,
      adresse: null,
      ville: null,
      wilaya: null,
      telephone: null,
      email: null,
      contact_principal: null,
      statut: 'prospect',
      notes: null,
      created_at: '2026-03-25',
      updated_at: '2026-03-25',
    }
    expect(entreprise.raison_sociale).toBe('Test SARL')
    expect(entreprise.statut).toBe('prospect')
  })

  it('Facture has snapshot fields', () => {
    const facture: Facture = {
      id: 1,
      entreprise_id: 1,
      exercice_id: 1,
      mission_id: null,
      devis_id: null,
      created_by: 1,
      numero: 'FF2026-001',
      type: 'FF',
      facture_origine_id: null,
      date_facture: '2026-03-25',
      date_echeance: '2026-04-25',
      montant_ht: 100000,
      taux_tva: 19,
      montant_tva: 19000,
      montant_ttc: 119000,
      montant_paye: 0,
      statut_paiement: 'en_attente',
      mode_paiement: 'non_defini',
      montant_restant: 119000,
      created_at: '2026-03-25',
      updated_at: '2026-03-25',
    }

    // Snapshots immuables
    expect(facture.taux_tva).toBe(19)
    expect(facture.montant_tva).toBe(19000)
    expect(facture.montant_ttc).toBe(facture.montant_ht + facture.montant_tva)
  })

  it('Paiement modes match enum', () => {
    const modes: Paiement['mode_paiement'][] = ['virement', 'cheque', 'autre']
    expect(modes).toHaveLength(3)
  })

  it('PaginatedResponse has meta', () => {
    const response: PaginatedResponse<Entreprise> = {
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
      links: { first: '', last: '', prev: null, next: null },
    }
    expect(response.meta.total).toBe(0)
    expect(response.data).toEqual([])
  })

  it('Devis statuts cover all cases', () => {
    const statuts: Devis['statut'][] = ['brouillon', 'envoye', 'accepte', 'refuse', 'expire']
    expect(statuts).toHaveLength(5)
  })
})
