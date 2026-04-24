<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_delegados_externos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('registration_code')->nullable()->unique();
            $table->string('name');
            $table->string('origin_unit');
            $table->string('role_title');
            $table->string('contact')->nullable();
            $table->string('email')->nullable()->unique();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['origin_unit', 'is_active'], 'idx_rh_delegados_origem_status');
            $table->index(['role_title', 'is_active'], 'idx_rh_delegados_role_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_delegados_externos');
    }
};
