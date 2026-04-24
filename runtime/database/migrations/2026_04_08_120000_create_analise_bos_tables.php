<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas de análise de BOs importadas via web.
 *
 * Equivalente PHP das tabelas legadas do Python:
 *   analise_ocorrencias  → analise_bos
 *   analise_naturezas    → analise_bo_naturezas
 *   analise_vitimas      → analise_bo_vitimas
 *   analise_autores      → analise_bo_autores
 *
 * Os dados aqui são escritos APENAS pelo PHP (upload web).
 * Os dados legados (escritos pelo Python) continuam sendo lidos
 * via LegacyDatabaseService / LegacyAnaliseReader.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── BOs principais ────────────────────────────────────────────────
        Schema::create('analise_bos', function (Blueprint $table): void {
            $table->id();

            // Identificação do BO
            $table->string('spj', 30)->unique();           // "TB4615/2025"
            $table->string('spj_prefix', 6)->nullable();    // "TB"
            $table->unsignedInteger('spj_seq')->nullable(); // 4615
            $table->unsignedSmallInteger('spj_year')->nullable(); // 2025
            $table->string('spj_fmt', 30)->nullable();      // "TB4615/2025" (exibição)

            // Dados da ocorrência
            $table->string('data_ocorrencia', 10)->nullable(); // "d/m/Y" (string como no legado)
            $table->string('lavrado', 50)->nullable();         // DDM, DP etc.
            $table->string('area_fato', 60)->nullable();       // "1º DP"
            $table->boolean('flagrante')->default(false);
            $table->boolean('ato_infracional')->default(false);

            // Referências MPU / IP
            $table->string('mpu_numero', 50)->nullable();
            $table->string('cnj_mpu', 50)->nullable();
            $table->string('cartorio_designado', 60)->nullable();
            $table->string('num_ip', 30)->nullable();
            $table->string('cartorio_ip', 60)->nullable();
            $table->string('cnj_ip', 50)->nullable();

            // Auditoria de importação
            $table->string('import_source', 100)->nullable();  // nome do arquivo
            $table->char('import_hash', 64)->nullable();       // sha256 do xlsx
            $table->timestamps();
        });

        // ─── Naturezas (1..N por BO) ───────────────────────────────────────
        Schema::create('analise_bo_naturezas', function (Blueprint $table): void {
            $table->id();
            $table->string('spj', 30)->index();
            $table->tinyInteger('slot');                   // 1..6
            $table->string('natureza', 120)->nullable();   // texto original
            $table->string('natureza_label', 80)->nullable(); // normalizado
            $table->string('tentado_consumado', 20)->nullable();
            $table->timestamps();

            $table->unique(['spj', 'slot']);
            $table->foreign('spj')->references('spj')->on('analise_bos')->cascadeOnDelete();
        });

        // ─── Vítimas ────────────────────────────────────────────────────────
        Schema::create('analise_bo_vitimas', function (Blueprint $table): void {
            $table->id();
            $table->string('spj', 30)->index();
            $table->tinyInteger('slot');
            $table->string('nome', 120)->nullable();
            $table->string('nome_key', 120)->nullable();   // normalizado sem acento, lower
            $table->string('tipo', 40)->nullable();        // Física, Jurídica etc.
            $table->timestamps();

            $table->unique(['spj', 'slot']);
            $table->foreign('spj')->references('spj')->on('analise_bos')->cascadeOnDelete();
        });

        // ─── Autores ────────────────────────────────────────────────────────
        Schema::create('analise_bo_autores', function (Blueprint $table): void {
            $table->id();
            $table->string('spj', 30)->index();
            $table->tinyInteger('slot');
            $table->string('nome', 120)->nullable();
            $table->string('nome_key', 120)->nullable();
            $table->timestamps();

            $table->unique(['spj', 'slot']);
            $table->foreign('spj')->references('spj')->on('analise_bos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analise_bo_autores');
        Schema::dropIfExists('analise_bo_vitimas');
        Schema::dropIfExists('analise_bo_naturezas');
        Schema::dropIfExists('analise_bos');
    }
};
