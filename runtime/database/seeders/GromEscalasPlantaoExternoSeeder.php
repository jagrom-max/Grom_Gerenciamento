<?php

namespace Database\Seeders;

use App\Models\EscalaPlantaoExterno;
use Illuminate\Database\Seeder;

/**
 * Semeia os 8 plantões externos do banco legado.
 * Usa updateOrCreate por legacy_id — completamente idempotente.
 */
class GromEscalasPlantaoExternoSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['legacy_id' => 1, 'nome' => 'Plantão Noturno',  'sigla' => 'PLN',    'unidade' => 'Plantão Seccional', 'regra' => 'AMBOS',       'is_active' => true],
            ['legacy_id' => 2, 'nome' => 'Plantão Diurno',   'sigla' => 'PLD',    'unidade' => 'Plantão Seccional', 'regra' => 'MESMO_DIA',   'is_active' => true],
            ['legacy_id' => 3, 'nome' => 'DDM 24 horas',     'sigla' => 'DDM24h', 'unidade' => 'DDM 24h',           'regra' => 'AMBOS',       'is_active' => true],
            ['legacy_id' => 4, 'nome' => 'Reforço Diurno',   'sigla' => 'RD',     'unidade' => 'Plantão Seccional', 'regra' => 'MESMO_DIA',   'is_active' => true],
            ['legacy_id' => 5, 'nome' => 'Reforço Noturno',  'sigla' => 'RN',     'unidade' => 'Plantão Seccional', 'regra' => 'DIA_SEGUINTE','is_active' => true],
            ['legacy_id' => 6, 'nome' => 'Cadeia Diurno',    'sigla' => 'CadD',   'unidade' => 'Outras',            'regra' => 'MESMO_DIA',   'is_active' => true],
            ['legacy_id' => 7, 'nome' => 'Cadeia Noturno',   'sigla' => 'CadN',   'unidade' => 'Outras',            'regra' => 'AMBOS',       'is_active' => true],
            ['legacy_id' => 9, 'nome' => 'Escolta',          'sigla' => 'Escolta','unidade' => 'Outras',            'regra' => 'MESMO_DIA',   'is_active' => true],
        ];

        foreach ($registros as $reg) {
            EscalaPlantaoExterno::query()->updateOrCreate(
                ['legacy_id' => $reg['legacy_id']],
                $reg,
            );
        }

        $this->command->info('EscalasPlantaoExterno: ' . count($registros) . ' registros semeados.');
    }
}
