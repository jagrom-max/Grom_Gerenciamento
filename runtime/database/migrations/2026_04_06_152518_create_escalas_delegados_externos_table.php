<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('escalas_delegados_externos', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo', 150);
            $table->string('nome_simplificado', 60);
            $table->string('unidade', 120)->nullable();
            $table->string('contato', 80)->nullable();
            $table->string('telefone', 40)->nullable();
            $table->string('obs', 400)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escalas_delegados_externos');
    }
};
