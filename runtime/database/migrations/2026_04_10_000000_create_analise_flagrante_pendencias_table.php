<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analise_flagrante_pendencias', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Origem
            $table->string('import_source');                  // nome do arquivo importado
            $table->string('spj');                            // ex: TB4615/2025
            $table->string('spj_prefix')->nullable();         // prefixo extraído
            $table->unsignedSmallInteger('spj_year')->nullable();

            // Dados da ocorrência (conforme vieram na planilha)
            $table->string('data_ocorrencia')->nullable();    // string normalizada
            $table->string('lavrado')->nullable();
            $table->string('area_fato')->nullable();
            $table->text('naturezas')->nullable();            // JSON resumido das naturezas
            $table->string('num_ip')->nullable();
            $table->string('mpu_numero')->nullable();

            // Cartório conforme planilha (pode estar errado/vazio → motivo da pendência)
            $table->string('cartorio_ip_planilha')->nullable();

            // Status de auditoria
            // pending   = aguardando revisão
            // approved  = cartório confirmado (estava correto)
            // corrected = cartório corrigido (era engano)
            // dismissed = dispensado (não era flagrante ou registro duplicado)
            $table->string('status')->default('pending'); // pending|approved|corrected|dismissed

            // Cartório atribuído após auditoria
            $table->uuid('cartorio_id')->nullable();
            $table->foreign('cartorio_id')->references('id')->on('cartorios')->nullOnDelete();

            // Auditoria
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'spj_year'], 'idx_afp_status_year');
            $table->index('spj', 'idx_afp_spj');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analise_flagrante_pendencias');
    }
};
