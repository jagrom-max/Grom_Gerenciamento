<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtividade_boletins', function (Blueprint $table): void {
            $table->string('mpu_decisao', 20)->nullable()->after('mpu_numero');
            $table->boolean('despacho_fundamentado')->default(false)->after('mpu_decisao');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE produtividade_boletins ADD CONSTRAINT chk_boletins_mpu_decisao CHECK (mpu_decisao IS NULL OR mpu_decisao IN ('DEFERIDA', 'INDEFERIDA'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE produtividade_boletins DROP CONSTRAINT IF EXISTS chk_boletins_mpu_decisao');
        }

        Schema::table('produtividade_boletins', function (Blueprint $table): void {
            $table->dropColumn(['mpu_decisao', 'despacho_fundamentado']);
        });
    }
};
