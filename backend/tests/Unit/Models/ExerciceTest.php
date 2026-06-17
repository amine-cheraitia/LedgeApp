<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Exercice;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_retourne_exercice_ouvert_de_lannee(): void
    {
        $ouvert = Exercice::factory()->create(['annee' => now()->year, 'statut' => 'ouvert']);

        $current = Exercice::current();

        $this->assertNotNull($current);
        $this->assertEquals($ouvert->id, $current->id);
    }

    public function test_current_retourne_null_si_aucun_ouvert(): void
    {
        Exercice::factory()->create(['annee' => now()->year, 'statut' => 'cloture']);

        $this->assertNull(Exercice::current());
    }

    public function test_current_ignore_exercices_annees_precedentes(): void
    {
        Exercice::factory()->create(['annee' => now()->year - 1, 'statut' => 'ouvert']);

        $this->assertNull(Exercice::current());
    }

    public function test_is_ouvert_retourne_true_si_statut_ouvert(): void
    {
        $exercice = Exercice::factory()->create(['statut' => 'ouvert']);

        $this->assertTrue($exercice->isOuvert());
    }

    public function test_is_ouvert_retourne_false_si_statut_cloture(): void
    {
        $exercice = Exercice::factory()->create(['statut' => 'cloture']);

        $this->assertFalse($exercice->isOuvert());
    }

    public function test_exercice_a_des_relations_has_many(): void
    {
        $exercice = Exercice::factory()->create(['statut' => 'ouvert']);

        $this->assertInstanceOf(HasMany::class, $exercice->missions());
        $this->assertInstanceOf(HasMany::class, $exercice->factures());
        $this->assertInstanceOf(HasMany::class, $exercice->devis());
    }
}
