<?php

namespace Database\Seeders;

use App\Models\RhDelegadoExterno;
use Illuminate\Database\Seeder;

class GromRhDelegadoExternoSeeder extends Seeder
{
    public function run(): void
    {
        RhDelegadoExterno::query()->updateOrCreate(
            ['registration_code' => 'DEX-001'],
            [
                'name' => 'Helena Martins',
                'origin_unit' => 'Delegacia Seccional Central',
                'role_title' => 'Delegada Externa',
                'contact' => '(11) 4000-1001',
                'email' => 'helena.martins@grom.local',
                'start_date' => now()->subMonths(4)->toDateString(),
                'end_date' => null,
                'is_active' => true,
                'notes' => 'Exemplo de delegacao externa com atuacao no cartorio central.',
            ],
        );

        RhDelegadoExterno::query()->updateOrCreate(
            ['registration_code' => 'DEX-002'],
            [
                'name' => 'Ricardo Alves',
                'origin_unit' => 'Unidade de Apoio Regional',
                'role_title' => 'Delegado Externo Substituto',
                'contact' => '(11) 4000-1002',
                'email' => 'ricardo.alves@grom.local',
                'start_date' => now()->addWeeks(2)->toDateString(),
                'end_date' => now()->addMonths(6)->toDateString(),
                'is_active' => true,
                'notes' => 'Delegacao agendada para cobertura temporaria.',
            ],
        );
    }
}
