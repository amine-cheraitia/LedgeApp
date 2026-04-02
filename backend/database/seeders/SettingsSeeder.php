<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'cabinet_nom', 'value' => 'Cabinet Conseil & Expertise', 'group' => 'cabinet', 'label' => 'Nom du cabinet'],
            ['key' => 'cabinet_adresse', 'value' => 'Alger, Algérie', 'group' => 'cabinet', 'label' => 'Adresse'],
            ['key' => 'cabinet_nif', 'value' => '', 'group' => 'cabinet', 'label' => 'NIF'],
            ['key' => 'cabinet_nis', 'value' => '', 'group' => 'cabinet', 'label' => 'NIS'],
            ['key' => 'cabinet_rib', 'value' => '', 'group' => 'cabinet', 'label' => 'RIB'],
            ['key' => 'cabinet_telephone', 'value' => '', 'group' => 'cabinet', 'label' => 'Téléphone'],
            ['key' => 'facture_prefixe', 'value' => 'FF', 'group' => 'facturation', 'label' => 'Préfixe facture'],
            ['key' => 'devis_prefixe', 'value' => 'DV', 'group' => 'facturation', 'label' => 'Préfixe devis'],
            ['key' => 'devise', 'value' => 'DA', 'group' => 'facturation', 'label' => 'Devise'],
            ['key' => 'relance_niveau1_jours', 'value' => '15', 'group' => 'relances', 'label' => 'Délai relance niveau 1 (jours)'],
            ['key' => 'relance_niveau2_jours', 'value' => '30', 'group' => 'relances', 'label' => 'Délai relance niveau 2 (jours)'],
            ['key' => 'relance_niveau3_jours', 'value' => '45', 'group' => 'relances', 'label' => 'Délai relance niveau 3 (jours)'],
            ['key' => 'relance_template_n1', 'value' => "Bonjour {{client}},\n\nNous vous rappelons que votre facture {{numero_facture}} d'un montant de {{montant}} est echue depuis le {{echeance}}.\n\nMerci de bien vouloir regulariser votre situation dans les meilleurs delais.\n\nCordialement,\nLe Cabinet", 'group' => 'relances', 'label' => 'Template relance niveau 1'],
            ['key' => 'relance_template_n2', 'value' => "Bonjour {{client}},\n\nMalgre notre premiere relance, nous constatons que la facture {{numero_facture}} d'un montant de {{montant}} reste impayee depuis le {{echeance}}.\n\nNous vous demandons de regulariser cette situation sous 48 heures.\n\nCordialement,\nLe Cabinet", 'group' => 'relances', 'label' => 'Template relance niveau 2'],
            ['key' => 'relance_template_n3', 'value' => "Bonjour {{client}},\n\nNous vous mettons en demeure de regler la facture {{numero_facture}} d'un montant de {{montant}} echue depuis le {{echeance}}.\n\nA defaut de paiement sous 72 heures, nous nous reservons le droit de prendre toutes mesures legales necessaires.\n\nCordialement,\nLe Cabinet", 'group' => 'relances', 'label' => 'Template relance niveau 3'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
