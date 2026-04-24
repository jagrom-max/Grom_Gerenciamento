<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // CPF como identificador de login (11 dígitos, sem formatação)
            $table->string('cpf', 11)->nullable()->unique()->after('username');

            // Vínculo opcional com um servidor do RH
            $table->uuid('funcionario_id')->nullable()->unique()->after('cpf');
            $table->foreign('funcionario_id')
                  ->references('id')
                  ->on('rh_funcionarios')
                  ->nullOnDelete();

            // Tipo de usuário para facilitar filtros
            // 'servidor' = vinculado a funcionário | 'visitante' = externo
            $table->string('tipo_usuario', 20)->default('visitante')->after('funcionario_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['funcionario_id']);
            $table->dropColumn(['cpf', 'funcionario_id', 'tipo_usuario']);
        });
    }
};
