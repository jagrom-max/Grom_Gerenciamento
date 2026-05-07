<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_funcionarios', function (Blueprint $table): void {
            $table->boolean('is_delegado_externo')->default(false)->after('concorre_escala');
            $table->string('senha_spj')->nullable()->after('is_delegado_externo');
            $table->string('senha_ipe')->nullable()->after('senha_spj');
            $table->text('observacoes_operacionais')->nullable()->after('senha_ipe');
        });
    }

    public function down(): void
    {
        Schema::table('rh_funcionarios', function (Blueprint $table): void {
            $table->dropColumn([
                'is_delegado_externo',
                'senha_spj',
                'senha_ipe',
                'observacoes_operacionais',
            ]);
        });
    }
};
