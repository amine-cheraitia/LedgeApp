<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\EnvoyerRelancesJob;
use App\Mail\RelanceClientMail;
use App\Models\Entreprise;
use App\Models\Facture;
use App\Models\Setting;
use App\Models\TvaTaux;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnvoyerRelancesJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function facture(array $overrides = []): Facture
    {
        $entreprise = Entreprise::factory()->create(['email' => 'client@example.com']);

        $tva = TvaTaux::create([
            'taux' => 19,
            'designation' => 'TVA standard',
            'type' => 'standard',
            'date_debut' => '2023-01-01',
            'actif' => true,
        ]);

        return Facture::factory()->create(array_merge([
            'entreprise_id' => $entreprise->id,
            'tva_taux_id' => $tva->id,
            'statut_paiement' => 'en_attente',
            'montant_ttc' => 112455,
            'montant_paye' => 0,
        ], $overrides));
    }

    public function test_envoie_une_relance_niveau_1_pour_une_facture_echue(): void
    {
        Mail::fake();
        Setting::set('relance_prefixe', 'R');

        // Echeance depassee de 20 jours -> niveau 1 (>= 15 j, sans prerequis).
        $facture = $this->facture(['date_echeance' => now()->subDays(20)->toDateString()]);

        EnvoyerRelancesJob::dispatchSync();

        $this->assertDatabaseHas('relances', [
            'facture_id' => $facture->id,
            'niveau' => 1,
            'type' => 'automatique',
            'statut' => 'envoyee',
        ]);
        Mail::assertSent(RelanceClientMail::class);
    }

    public function test_n_envoie_rien_pour_une_facture_non_echue(): void
    {
        Mail::fake();

        // Echeance dans le futur -> aucune relance.
        $this->facture(['date_echeance' => now()->addDays(10)->toDateString()]);

        EnvoyerRelancesJob::dispatchSync();

        $this->assertDatabaseCount('relances', 0);
        Mail::assertNothingSent();
    }
}
