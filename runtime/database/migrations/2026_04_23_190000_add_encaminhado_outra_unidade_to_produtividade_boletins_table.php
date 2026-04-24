<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONTEXTO DE NEGÓCIO:
 *
 * - lavrado_unidade (DDM|OUTRAS_UNIDADES): indica ONDE o BO foi registrado.
 *   - DDM: lavrado na própria DDM. É nossa atribuição.
 *   - OUTRAS_UNIDADES: lavrado por outra unidade e encaminhado para a DDM por ser atribuição nossa.
 *     O procedimento prossegue normalmente nos nossos cartórios e pode gerar IP.
 *
 * - encaminhado_outra_unidade (bool): indica se o BO lavrado na DDM foi enviado para outra unidade
 *   APÓS determinação, por não ser atribuição da DDM.
 *   Esses casos NÃO vão gerar IP nos cartórios da DDM (salvo ordem judicial).
 *   São excluídos das Pendências Críticas (MPU sem IP) por serem encerrados operacionalmente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtividade_boletins', function (Blueprint $table): void {
            $table->boolean('encaminhado_outra_unidade')->default(false)->after('despacho_fundamentado');
            $table->string('encaminhado_para_unidade', 200)->nullable()->after('encaminhado_outra_unidade');
        });
    }

    public function down(): void
    {
        Schema::table('produtividade_boletins', function (Blueprint $table): void {
            $table->dropColumn(['encaminhado_outra_unidade', 'encaminhado_para_unidade']);
        });
    }
};
