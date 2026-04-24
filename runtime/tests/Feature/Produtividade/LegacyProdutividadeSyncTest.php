<?php

namespace Tests\Feature\Produtividade;

use App\Models\Cartorio;
use App\Models\ProductivityStatMonthly;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyProdutividadeSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeders_import_legacy_productivity_data(): void
    {
        if (! config('grom_legacy.enabled')) {
            $this->markTestSkipped('Legacy sync is disabled in this environment.');
        }

        $this->seed();

        $this->assertSame(4, Cartorio::query()->whereIn('number', [2, 3, 5, 6])->count());
        $this->assertDatabaseMissing('cartorios', [
            'number' => 1,
        ]);
        $this->assertDatabaseHas('cartorios', [
            'number' => 2,
            'name' => 'Patrícia',
        ]);
        $this->assertSame(39, ProductivityStatMonthly::query()->count());
        $this->assertDatabaseHas('productivity_stats_monthly', [
            'reference_year' => 2025,
            'reference_month' => 1,
            'ip_instaurados' => 19,
        ]);
    }
}
