<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('escalas_substituicoes_ddm', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delegado_externo_id');
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->string('motivo', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalas_substituicoes_ddm');
    }
};
