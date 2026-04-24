<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_afastamentos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('funcionario_id');
            $table->string('reason');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('funcionario_id')->references('id')->on('rh_funcionarios')->restrictOnDelete();
            $table->index(['funcionario_id', 'is_active', 'start_date'], 'idx_rh_afastamentos_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_afastamentos');
    }
};
