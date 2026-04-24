<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_funcionarios', function (Blueprint $table): void {
            $table->unsignedInteger('legacy_id')->nullable()->unique('uniq_rh_funcionarios_legacy_id')->after('id');
            $table->string('short_name')->nullable()->after('name');
            $table->string('sector')->nullable()->after('cargo_id');
            $table->string('phone')->nullable()->after('sector');
            $table->string('rg')->nullable()->after('phone');
            $table->string('cpf')->nullable()->after('rg');
            $table->date('birth_date')->nullable()->after('cpf');
            $table->date('designation_date')->nullable()->after('admission_date');
            $table->date('removal_date')->nullable()->after('departure_date');
            $table->boolean('concorre_escala')->default(false)->after('removal_date');
        });
    }

    public function down(): void
    {
        Schema::table('rh_funcionarios', function (Blueprint $table): void {
            $table->dropUnique('uniq_rh_funcionarios_legacy_id');
            $table->dropColumn([
                'legacy_id',
                'short_name',
                'sector',
                'phone',
                'rg',
                'cpf',
                'birth_date',
                'designation_date',
                'removal_date',
                'concorre_escala',
            ]);
        });
    }
};
