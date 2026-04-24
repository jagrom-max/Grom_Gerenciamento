<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_holidays', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('holiday_date')->unique();
            $table->string('name');
            $table->string('scope', 50)->default('nacional');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'holiday_date'], 'idx_rh_holidays_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_holidays');
    }
};
