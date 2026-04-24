<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtividade_boletins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cartorio_id');
            $table->uuid('import_batch_id')->nullable();
            $table->unsignedInteger('reference_year');
            $table->unsignedTinyInteger('reference_month');
            $table->date('data_fato')->nullable();
            $table->string('spj')->nullable();
            $table->text('naturezas')->nullable();
            $table->string('lavrado_unidade')->default('OUTRAS_UNIDADES');
            $table->boolean('is_flagrante')->default(false);
            $table->string('mpu_numero')->nullable();
            $table->string('num_ip')->nullable();
            $table->string('num_ipe')->nullable();
            $table->string('num_cnj')->nullable();
            $table->uuid('productivity_flagrante_id')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('cartorio_id')->references('id')->on('cartorios')->restrictOnDelete();
            $table->foreign('import_batch_id')->references('id')->on('import_batches')->nullOnDelete();
            $table->foreign('productivity_flagrante_id')->references('id')->on('produtividade_flagrantes')->nullOnDelete();

            $table->index(['cartorio_id', 'reference_year', 'reference_month'], 'idx_boletins_period');
            $table->index(['cartorio_id', 'is_flagrante'], 'idx_boletins_flagrante');
            $table->index(['data_fato'], 'idx_boletins_data_fato');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE produtividade_boletins ADD CONSTRAINT chk_boletins_month CHECK (reference_month BETWEEN 1 AND 12)");
            DB::statement("ALTER TABLE produtividade_boletins ADD CONSTRAINT chk_boletins_lavrado CHECK (lavrado_unidade IN ('DDM', 'OUTRAS_UNIDADES'))");
            DB::statement("CREATE UNIQUE INDEX ux_boletins_cartorio_spj ON produtividade_boletins (cartorio_id, lower(spj)) WHERE spj IS NOT NULL AND btrim(spj) <> ''");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('produtividade_boletins');
    }
};
