<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalas_versoes', function (Blueprint $table): void {
            $table->timestamp('conferida_em')->nullable()->after('fechada_em');
        });
    }

    public function down(): void
    {
        Schema::table('escalas_versoes', function (Blueprint $table): void {
            $table->dropColumn('conferida_em');
        });
    }
};
