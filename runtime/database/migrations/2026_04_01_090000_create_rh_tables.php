<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_cargos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_funcionarios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('matricula')->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->uuid('cargo_id');
            $table->date('admission_date');
            $table->date('departure_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('cargo_id')->references('id')->on('rh_cargos')->restrictOnDelete();
            $table->index(['cargo_id', 'is_active'], 'idx_rh_funcionarios_cargo_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_funcionarios');
        Schema::dropIfExists('rh_cargos');
    }
};
