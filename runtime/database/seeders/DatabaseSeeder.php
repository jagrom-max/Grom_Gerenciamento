<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GromAccessSeeder::class,
            GromRhSeeder::class,
            GromRhLegacySeeder::class,
            GromRhHolidaySeeder::class,
            GromRhDelegadoExternoSeeder::class,
        ]);

        if ($this->shouldSyncLegacyProdutividade()) {
            $this->call(GromProdutividadeLegacySeeder::class);
        }

        if ($this->shouldSeedPilotDemo()) {
            $this->call(GromPilotDemoSeeder::class);
        }

        $this->call([
            GromEscalasPlantaoExternoSeeder::class,
            GromEscalasDelegadosExternosSeeder::class,
        ]);
    }

    private function shouldSyncLegacyProdutividade(): bool
    {
        if (! config('grom_legacy.enabled')) {
            return false;
        }

        $dbPath = (string) config('grom_legacy.analise_db_path');

        return $dbPath !== '' && File::exists($dbPath);
    }

    private function shouldSeedPilotDemo(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        if (app()->environment('local')) {
            return true;
        }

        return (bool) env('GROM_PILOT_DEMO_SEED_ENABLED', false);
    }
}
