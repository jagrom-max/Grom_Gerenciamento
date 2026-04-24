<?php

namespace Database\Seeders;

use App\Services\Produtividade\LegacyProdutividadeSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class GromProdutividadeLegacySeeder extends Seeder
{
    public function run(): void
    {
        $result = app(LegacyProdutividadeSyncService::class)->sync();

        if (! empty($result['warnings'])) {
            Log::info('Sincronizacao legada de produtividade concluida com avisos.', $result);

            return;
        }

        Log::info('Sincronizacao legada de produtividade concluida com sucesso.', $result);
    }
}
