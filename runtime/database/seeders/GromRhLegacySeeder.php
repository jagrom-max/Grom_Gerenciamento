<?php

namespace Database\Seeders;

use App\Services\Rh\LegacyFuncionariosSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class GromRhLegacySeeder extends Seeder
{
    public function run(): void
    {
        $result = app(LegacyFuncionariosSyncService::class)->sync();

        if (! empty($result['warnings'])) {
            Log::info('Sincronizacao legada de funcionarios concluida com avisos.', $result);

            return;
        }

        Log::info('Sincronizacao legada de funcionarios concluida com sucesso.', $result);
    }
}
