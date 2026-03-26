<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\TvaTaux;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvaTauxTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_correct_rate_for_date(): void
    {
        TvaTaux::create([
            'taux' => 17,
            'designation' => 'Ancien taux',
            'date_debut' => '2020-01-01',
            'date_fin' => '2022-12-31',
            'type' => 'standard',
            'actif' => true,
        ]);

        TvaTaux::create([
            'taux' => 19,
            'designation' => 'Nouveau taux',
            'date_debut' => '2023-01-01',
            'date_fin' => null,
            'type' => 'standard',
            'actif' => true,
        ]);

        $ancien = TvaTaux::enVigueurLe(Carbon::parse('2022-06-15'));
        $this->assertNotNull($ancien);
        $this->assertEquals(17, (float) $ancien->taux);

        $nouveau = TvaTaux::enVigueurLe(Carbon::parse('2026-03-25'));
        $this->assertNotNull($nouveau);
        $this->assertEquals(19, (float) $nouveau->taux);
    }

    public function test_returns_null_for_date_before_any_rate(): void
    {
        TvaTaux::create([
            'taux' => 19,
            'designation' => 'Standard',
            'date_debut' => '2023-01-01',
            'type' => 'standard',
            'actif' => true,
        ]);

        $rate = TvaTaux::enVigueurLe('2019-01-01');
        $this->assertNull($rate);
    }

    public function test_accepts_string_date(): void
    {
        TvaTaux::create([
            'taux' => 19,
            'designation' => 'Standard',
            'date_debut' => '2023-01-01',
            'type' => 'standard',
            'actif' => true,
        ]);

        $rate = TvaTaux::enVigueurLe('2026-03-25');
        $this->assertNotNull($rate);
        $this->assertEquals(19, (float) $rate->taux);
    }
}
