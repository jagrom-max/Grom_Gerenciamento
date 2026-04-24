<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacional_ordens_servico', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // --- Identificação ---
            $table->string('numero', 30)->nullable()->index()->comment('Número/código da OS');
            $table->date('data_emissao')->nullable();
            $table->date('data_prazo')->nullable();

            // --- Origem ---
            $table->string('cartorio_id', 60)->nullable()->index()->comment('Cartório de origem, se aplicável');
            $table->string('solicitante', 120)->nullable();

            // --- Conteúdo ---
            $table->string('tipo', 80)->nullable()->index()->comment('Categoria da ordem de serviço');
            $table->string('assunto', 255);
            $table->text('descricao')->nullable();

            // --- Status ---
            $table->string('status', 40)->default('Aberta')->index();
            $table->date('data_conclusao')->nullable();
            $table->string('responsavel', 120)->nullable();
            $table->text('resultado')->nullable();

            // --- Auditoria ---
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->text('deleted_motivo')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('status', 'idx_os_status');
            $table->index('data_emissao', 'idx_os_emissao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacional_ordens_servico');
    }
};
