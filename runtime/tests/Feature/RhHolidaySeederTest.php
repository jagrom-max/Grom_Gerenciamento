<?php

namespace Tests\Feature;

use App\Models\RhHoliday;
use Database\Seeders\GromRhHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RhHolidaySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_seeder_is_idempotent_when_run_multiple_times(): void
    {
        Artisan::call('db:seed', [
            '--class' => GromRhHolidaySeeder::class,
            '--force' => true,
        ]);

        $firstCount = RhHoliday::query()->count();
        $firstHoliday = RhHoliday::query()
            ->whereDate('holiday_date', '2025-11-20')
            ->first();

        Artisan::call('db:seed', [
            '--class' => GromRhHolidaySeeder::class,
            '--force' => true,
        ]);

        $this->assertGreaterThan(0, $firstCount);
        $this->assertNotNull($firstHoliday);
        $this->assertSame($firstCount, RhHoliday::query()->count());
        $this->assertSame(1, RhHoliday::query()->whereDate('holiday_date', '2025-11-20')->count());
    }
}