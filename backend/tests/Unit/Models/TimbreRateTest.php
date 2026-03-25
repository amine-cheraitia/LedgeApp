<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\TimbreRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimbreRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_timbre_correctly(): void
    {
        $timbre = TimbreRate::create([
            'taux' => 1,
            'plafond' => 2500,
            'designation' => 'Timbre fiscal',
            'date_debut' => '2024-01-01',
            'actif' => true,
        ]);

        // 100000 * 1% = 1000, cap 2500 => 1000
        $this->assertEquals(1000, $timbre->calculer(100000));

        // 500000 * 1% = 5000, cap 2500 => 2500
        $this->assertEquals(2500, $timbre->calculer(500000));
    }

    public function test_returns_correct_rate_for_date(): void
    {
        TimbreRate::create([
            'taux' => 1,
            'plafond' => 2500,
            'designation' => 'Timbre',
            'date_debut' => '2024-01-01',
            'actif' => true,
        ]);

        $rate = TimbreRate::enVigueurLe('2026-03-25');
        $this->assertNotNull($rate);
        $this->assertEquals(1, (float) $rate->taux);
    }

    public function test_returns_null_before_any_rate(): void
    {
        TimbreRate::create([
            'taux' => 1,
            'plafond' => 2500,
            'designation' => 'Timbre',
            'date_debut' => '2024-01-01',
            'actif' => true,
        ]);

        $rate = TimbreRate::enVigueurLe('2020-01-01');
        $this->assertNull($rate);
    }
}
