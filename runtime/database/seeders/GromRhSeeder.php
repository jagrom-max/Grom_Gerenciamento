<?php

namespace Database\Seeders;

use App\Models\RhCargo;
use App\Models\RhAfastamento;
use App\Models\RhFuncionario;
use Illuminate\Database\Seeder;

class GromRhSeeder extends Seeder
{
    public function run(): void
    {
        $cargoEscrivao = RhCargo::query()->updateOrCreate(
            ['code' => 'RH-001'],
            [
                'name' => 'Escrivao',
                'description' => 'Cargo administrativo base do piloto web.',
                'is_active' => true,
            ],
        );

        $cargoDelegado = RhCargo::query()->updateOrCreate(
            ['code' => 'RH-002'],
            [
                'name' => 'Delegado',
                'description' => 'Cargo de chefia e supervisao do piloto web.',
                'is_active' => true,
            ],
        );

        RhFuncionario::query()->updateOrCreate(
            ['matricula' => 'FUN-001'],
            [
                'name' => 'Maria Souza',
                'short_name' => 'Maria Souza',
                'email' => 'maria.souza@grom.local',
                'cargo_id' => $cargoEscrivao->id,
                'sector' => 'Cartorio Central',
                'phone' => '(11) 4000-1001',
                'rg' => '12.345.678-9',
                'cpf' => '123.456.789-00',
                'birth_date' => '1987-02-14',
                'admission_date' => now()->subYears(2)->toDateString(),
                'designation_date' => now()->subYears(2)->addMonth()->toDateString(),
                'departure_date' => null,
                'removal_date' => null,
                'concorre_escala' => true,
                'is_active' => true,
                'notes' => 'Origem: piloto local | Cadastro demonstrativo do modulo de RH.',
            ],
        );

        RhFuncionario::query()->updateOrCreate(
            ['matricula' => 'FUN-002'],
            [
                'name' => 'Carlos Lima',
                'short_name' => 'Carlos Lima',
                'email' => 'carlos.lima@grom.local',
                'cargo_id' => $cargoDelegado->id,
                'sector' => 'Plantao',
                'phone' => '(11) 4000-1002',
                'rg' => '98.765.432-1',
                'cpf' => '987.654.321-00',
                'birth_date' => '1982-11-05',
                'admission_date' => now()->subYear()->toDateString(),
                'designation_date' => now()->subYear()->addDays(15)->toDateString(),
                'departure_date' => null,
                'removal_date' => null,
                'concorre_escala' => false,
                'is_active' => true,
                'notes' => 'Origem: piloto local | Cadastro demonstrativo do modulo de RH.',
            ],
        );

        $maria = RhFuncionario::query()->where('matricula', 'FUN-001')->first();

        if ($maria) {
            RhAfastamento::query()->updateOrCreate(
                [
                    'funcionario_id' => $maria->id,
                    'reason' => 'Ferias',
                    'start_date' => now()->subDays(15)->toDateString(),
                ],
                [
                    'end_date' => now()->subDays(5)->toDateString(),
                    'is_active' => true,
                    'notes' => 'Afastamento demonstrativo do modulo de RH.',
                ],
            );
        }
    }
}
