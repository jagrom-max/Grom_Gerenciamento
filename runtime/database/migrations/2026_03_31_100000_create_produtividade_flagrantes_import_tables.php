<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_name');
            $table->string('source_hash')->nullable();
            $table->date('source_period_start')->nullable();
            $table->date('source_period_end')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('import_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->string('source_process_key');
            $table->uuid('cartorio_id')->nullable();
            $table->string('cartorio_hint')->nullable();
            $table->unsignedInteger('reference_year')->nullable();
            $table->unsignedTinyInteger('reference_month')->nullable();
            $table->string('spj')->nullable();
            $table->text('naturezas')->nullable();
            $table->string('num_ip')->nullable();
            $table->string('num_ipe')->nullable();
            $table->string('num_cnj')->nullable();
            $table->date('data_fato')->nullable();
            $table->string('status_origem')->nullable();
            $table->string('lavrado_unidade')->default('OUTRAS_UNIDADES');
            $table->json('payload')->nullable();
            $table->string('import_status')->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->uuid('productivity_flagrante_id')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'source_process_key']);
            $table->foreign('batch_id')->references('id')->on('import_batches')->cascadeOnDelete();
            $table->foreign('cartorio_id')->references('id')->on('cartorios')->nullOnDelete();
            $table->index(['cartorio_id', 'import_status', 'reference_year', 'reference_month'], 'idx_import_items_queue');
        });

        Schema::create('produtividade_flagrantes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cartorio_id');
            $table->uuid('source_item_id')->nullable()->unique();
            $table->unsignedInteger('reference_year');
            $table->unsignedTinyInteger('reference_month');
            $table->string('spj')->nullable();
            $table->text('naturezas')->nullable();
            $table->string('num_ip')->nullable();
            $table->string('num_ipe')->nullable();
            $table->string('num_cnj')->nullable();
            $table->date('data_fato')->nullable();
            $table->string('lavrado_unidade');
            $table->boolean('manually_confirmed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('cartorio_id')->references('id')->on('cartorios')->restrictOnDelete();
            $table->foreign('source_item_id')->references('id')->on('import_items')->nullOnDelete();
            $table->index(['cartorio_id', 'reference_year', 'reference_month', 'is_active'], 'idx_prod_flagrantes_period');
        });

        DB::statement("CREATE INDEX idx_prod_flagrantes_cartorio_data ON produtividade_flagrantes(cartorio_id, data_fato)");
        DB::statement("CREATE INDEX idx_prod_flagrantes_spj ON produtividade_flagrantes(cartorio_id, spj)");
        DB::statement("CREATE INDEX idx_prod_flagrantes_num_ip ON produtividade_flagrantes(cartorio_id, num_ip)");
        DB::statement("CREATE INDEX idx_prod_flagrantes_num_cnj ON produtividade_flagrantes(cartorio_id, num_cnj)");

        Schema::table('import_items', function (Blueprint $table): void {
            $table->foreign('productivity_flagrante_id')
                ->references('id')
                ->on('produtividade_flagrantes')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE import_items ADD CONSTRAINT chk_import_items_status CHECK (import_status IN ('pending', 'confirmed', 'rejected'))");
            DB::statement("ALTER TABLE import_items ADD CONSTRAINT chk_import_items_lavrado CHECK (lavrado_unidade IN ('DDM', 'OUTRAS_UNIDADES'))");
            DB::statement("ALTER TABLE produtividade_flagrantes ADD CONSTRAINT chk_prod_flagrantes_lavrado CHECK (lavrado_unidade IN ('DDM', 'OUTRAS_UNIDADES'))");
            DB::statement("CREATE UNIQUE INDEX ux_flagrantes_cartorio_spj ON produtividade_flagrantes (cartorio_id, lower(spj)) WHERE spj IS NOT NULL AND btrim(spj) <> ''");
            DB::statement("CREATE UNIQUE INDEX ux_flagrantes_cartorio_ip ON produtividade_flagrantes (cartorio_id, lower(num_ip)) WHERE num_ip IS NOT NULL AND btrim(num_ip) <> ''");
            DB::statement("CREATE UNIQUE INDEX ux_flagrantes_cartorio_cnj ON produtividade_flagrantes (cartorio_id, lower(num_cnj)) WHERE num_cnj IS NOT NULL AND btrim(num_cnj) <> ''");
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('produtividade_flagrantes');
        Schema::dropIfExists('import_items');
        Schema::dropIfExists('import_batches');
        Schema::enableForeignKeyConstraints();
    }
};
