<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalas_versoes', function (Blueprint $table): void {
            $table->id();

            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->unsignedTinyInteger('versao')->default(1);

            /**
             * provisoria  — em conferência/auditoria, todos os campos editáveis
             * definitiva  — escala homologada; apenas dias futuros editáveis via nova versão
             */
            $table->enum('status', ['provisoria', 'definitiva'])->default('provisoria');

            $table->text('obs')->nullable();                  // observação ao gravar definitivo

            // Auditoria
            $table->uuid('created_by')->nullable();
            $table->uuid('fechada_por')->nullable();
            $table->timestamp('fechada_em')->nullable();

            $table->timestamps();

            // Uma linha por (ano, mes, versao)
            $table->unique(['ano', 'mes', 'versao'], 'ux_escala_versao');
            $table->index(['ano', 'mes'], 'idx_escala_versao_ano_mes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalas_versoes');
    }
};
