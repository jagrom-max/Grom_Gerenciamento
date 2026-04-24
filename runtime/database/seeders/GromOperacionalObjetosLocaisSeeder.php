<?php

namespace Database\Seeders;

use App\Models\OperacionalObjetoLocal;
use Illuminate\Database\Seeder;

/**
 * Seed dos locais de custódia vindos do legado Python
 * (tabela oper_objetos_locais, 12 registros ativos).
 *
 * Idempotente: usa updateOrCreate por legacy_id.
 */
class GromOperacionalObjetosLocaisSeeder extends Seeder
{
    public function run(): void
    {
        $locais = [
            ['legacy_id' => 1,  'nome' => 'Cartório Central',   'is_active' => false],
            ['legacy_id' => 3,  'nome' => 'C2',                 'is_active' => true],
            ['legacy_id' => 4,  'nome' => 'C3',                 'is_active' => true],
            ['legacy_id' => 5,  'nome' => 'C5',                 'is_active' => true],
            ['legacy_id' => 6,  'nome' => 'C6',                 'is_active' => true],
            ['legacy_id' => 7,  'nome' => 'IC',                 'is_active' => true],
            ['legacy_id' => 8,  'nome' => 'Fórum',              'is_active' => true],
            ['legacy_id' => 9,  'nome' => 'Cofre CC',           'is_active' => false],
            ['legacy_id' => 10, 'nome' => 'CC - Cofre',         'is_active' => true],
            ['legacy_id' => 12, 'nome' => 'CC - Arquivo',       'is_active' => true],
            ['legacy_id' => 14, 'nome' => 'Destruído',          'is_active' => true],
            ['legacy_id' => 15, 'nome' => 'Restituído',         'is_active' => true],
        ];

        foreach ($locais as $local) {
            OperacionalObjetoLocal::query()->updateOrCreate(
                ['legacy_id' => $local['legacy_id']],
                ['nome' => $local['nome'], 'is_active' => $local['is_active']]
            );
        }
    }
}
