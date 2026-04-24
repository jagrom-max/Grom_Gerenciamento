<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacional_mandados', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Tipo unificado com sigla canônica (MPP, MPT, MPD, MPC, MBA, MAM)
            $table->string('tipo_sigla', 8)->index();
            // Campos descritivos internos para compatibilidade e relatório
            $table->string('tipo_mandado');
            $table->string('subtipo_prisao')->nullable();

            // Identificação judicial
            $table->string('cnj_numero', 30)->nullable()->index();
            $table->string('vara')->nullable();

            // Alvo
            $table->string('nome');
            $table->string('cpf', 11)->nullable();
            $table->string('rg', 30)->nullable();

            // Datas
            $table->date('data_emissao');
            $table->date('validade');

            // Tipificação penal
            $table->string('tipificacao_penal')->nullable();       // Lei (ex: 11.343/2006)
            $table->string('artigo', 30)->nullable();
            $table->string('paragrafo', 30)->nullable();
            $table->json('tipificacoes_extra')->nullable();        // array de {lei, artigo, paragrafo}

            // Pena
            $table->unsignedSmallInteger('pena_anos')->default(0);
            $table->unsignedTinyInteger('pena_meses')->default(0);
            $table->unsignedSmallInteger('pena_dias')->default(0);
            $table->string('regime', 20)->nullable();              // Aberto | Semiaberto | Fechado

            // Cumprimento
            $table->string('procedimento', 20)->default('Em Aberto')->index(); // Cumprido | Em Aberto | Revogado
            $table->string('cumprido_por', 30)->nullable();       // DDM | PM | GCM | Polícia Civil
            $table->date('data_cumprimento')->nullable();
            $table->string('bo_numero', 20)->nullable();          // B.O. nº formato AA0000/0000

            $table->text('observacoes')->nullable();

            // Auditoria
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->string('deleted_motivo')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Origem legada (quando sincronizado do SQLite)
            $table->unsignedInteger('legacy_id')->nullable()->unique();

            $table->index(['tipo_sigla', 'procedimento'], 'idx_mand_tipo_proc');
            $table->index(['nome'], 'idx_mand_nome');
            $table->index('validade', 'idx_mand_validade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacional_mandados');
    }
};
