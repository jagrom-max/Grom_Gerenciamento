<?php

namespace Database\Seeders;

use App\Models\RhCargo;
use Illuminate\Database\Seeder;

class GromRhSeeder extends Seeder
{
    public function run(): void
    {
        RhCargo::query()->updateOrCreate(
            ['code' => 'RH-001'],
            [
                'name' => 'Escrivao',
                'description' => 'Cargo administrativo base do quadro de pessoal.',
                'is_active' => true,
            ],
        );

        RhCargo::query()->updateOrCreate(
            ['code' => 'RH-002'],
            [
                'name' => 'Delegado',
                'description' => 'Cargo de chefia e supervisao do quadro de pessoal.',
                'is_active' => true,
            ],
        );
    }
}
