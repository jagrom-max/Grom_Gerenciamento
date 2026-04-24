<?php

namespace Database\Seeders;

use App\Models\EscalaDelegadoExterno;
use Illuminate\Database\Seeder;

class GromEscalasDelegadosExternosSeeder extends Seeder
{
    public function run(): void
    {
        $delegados = [
            ['legacy_id' => 6,  'nome_completo' => 'Carlos Alberto Schio Filho',    'nome_simplificado' => 'Dr. Schio'],
            ['legacy_id' => 7,  'nome_completo' => 'Eusmar Danilo Bortolozi Broetto', 'nome_simplificado' => 'Dr. Danilo'],
            ['legacy_id' => 8,  'nome_completo' => 'Rodolpho Lopes do Canto Jr.',   'nome_simplificado' => 'Dr. Rodolpho'],
            ['legacy_id' => 9,  'nome_completo' => 'Alexandre Socolowski',           'nome_simplificado' => 'Dr. Alexandre'],
            ['legacy_id' => 10, 'nome_completo' => 'André L. A. Muller',             'nome_simplificado' => 'Dr. André'],
            ['legacy_id' => 11, 'nome_completo' => 'Aroldo Cezário Diniz',           'nome_simplificado' => 'Dr. Aroldo'],
            ['legacy_id' => 12, 'nome_completo' => 'Douglas F. B. do Amaral',        'nome_simplificado' => 'Dr. Douglas'],
            ['legacy_id' => 13, 'nome_completo' => 'João Vitor Rigo Bonilha',        'nome_simplificado' => 'Dr. João'],
            ['legacy_id' => 14, 'nome_completo' => 'Luis Gonzaga Bovo Jr.',          'nome_simplificado' => 'Dr. Luis'],
            ['legacy_id' => 15, 'nome_completo' => 'Rodrigo Marcel Porto',           'nome_simplificado' => 'Dr. Rodrigo'],
        ];

        foreach ($delegados as $d) {
            EscalaDelegadoExterno::updateOrCreate(
                ['legacy_id' => $d['legacy_id']],
                [
                    'nome_completo'     => trim($d['nome_completo']),
                    'nome_simplificado' => trim($d['nome_simplificado']),
                    'is_active'         => true,
                ]
            );
        }

        $this->command->info('EscalaDelegadoExterno: ' . count($delegados) . ' delegados semeados.');
    }
}
