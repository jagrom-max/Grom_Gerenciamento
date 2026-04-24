<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Catálogo de plantões externos ───────────────────────────────
        Schema::create('escalas_plantoes_externos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 80);
            $table->string('sigla', 20)->nullable()->unique();
            $table->string('unidade', 80)->nullable();
            $table->string('regra', 20)->nullable();       // AMBOS | MESMO_DIA | DIA_SEGUINTE
            $table->text('observacao')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // ─── 2. Escala diária — uma linha por data/versão ───────────────────
        Schema::create('escalas_dias', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->date('data')->index();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('versao')->default(1);
            $table->boolean('is_fechada')->default(false);   // versão finalizada

            // Snapshots de nomes — compatibilidade total com legado (texto livre)
            $table->string('escrivao', 100)->nullable();
            $table->string('operacional', 100)->nullable();
            $table->string('fechar_nome', 100)->nullable();  // quem vai "fechar"
            $table->string('delegada', 100)->nullable();
            $table->text('plantao_externo')->nullable();     // "Laura (PLD), Marina (PLN)"

            // Auditoria
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Referência ao legado (para sync idempotente)
            $table->unsignedInteger('legacy_id')->nullable()->unique();

            $table->unique(['data', 'versao'], 'ux_escala_data_versao');
            $table->index(['ano', 'mes', 'versao'], 'idx_escala_ano_mes_versao');
        });

        // ─── 3. Plantões por funcionário/data ───────────────────────────────
        Schema::create('escalas_plantoes_funcionarios', function (Blueprint $table): void {
            $table->id();
            $table->date('data')->index();
            $table->foreignUuid('funcionario_id')
                  ->constrained('rh_funcionarios')
                  ->nullOnDelete();
            $table->foreignId('plantao_externo_id')
                  ->constrained('escalas_plantoes_externos')
                  ->cascadeOnDelete();

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            // Referência ao legado
            $table->unsignedInteger('legacy_id')->nullable()->unique();

            $table->unique(
                ['funcionario_id', 'plantao_externo_id', 'data'],
                'ux_plantao_func_data',
            );
            $table->index(['data', 'plantao_externo_id'], 'idx_plantao_data_tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalas_plantoes_funcionarios');
        Schema::dropIfExists('escalas_dias');
        Schema::dropIfExists('escalas_plantoes_externos');
    }
};
