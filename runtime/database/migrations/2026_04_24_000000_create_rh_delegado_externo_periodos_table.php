<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_delegado_externo_periodos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('delegado_externo_id')
                ->constrained('rh_delegados_externos')
                ->cascadeOnDelete();
            $table->string('motivo', 255);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(
                ['delegado_externo_id', 'is_active', 'start_date'],
                'idx_rh_del_ext_periodos_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_delegado_externo_periodos');
    }
};
