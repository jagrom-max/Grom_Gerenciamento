<?php

namespace Tests\Feature;

use App\Enums\ImportItemStatus;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\ProductivityFlagrante;
use App\Models\User;
use Database\Seeders\GromPilotDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PilotDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_demo_seeder_populates_local_navigation_data(): void
    {
        $this->seed();

        Artisan::call('db:seed', [
            '--class' => GromPilotDemoSeeder::class,
            '--force' => true,
        ]);

        $this->assertTrue(User::query()->where('username', 'gestor.demo')->where('must_change_password', false)->exists());
        $this->assertTrue(User::query()->where('username', 'operador.demo')->where('must_change_password', false)->exists());
        $this->assertTrue(Cartorio::query()->where('number', 10)->exists());
        $this->assertTrue(Cartorio::query()->where('number', 20)->exists());
        $this->assertTrue(ImportBatch::query()->where('source_type', 'DEMO_LOCAL')->exists());
        $this->assertSame(3, ImportItem::query()->where('import_status', ImportItemStatus::Pending->value)->count());
        $this->assertSame(1, ImportItem::query()->whereNull('cartorio_id')->where('import_status', ImportItemStatus::Pending->value)->count());
        $this->assertGreaterThanOrEqual(3, ProductivityFlagrante::query()->where('is_active', true)->count());
    }
}
