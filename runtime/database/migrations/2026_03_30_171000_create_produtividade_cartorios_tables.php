<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartorios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('number')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('designacao')->nullable();
            $table->string('manager_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cartorio_status_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('cartorio_id');
            $table->string('status');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('cartorio_id')->references('id')->on('cartorios')->cascadeOnDelete();
        });

        Schema::create('cartorio_manager_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('cartorio_id');
            $table->string('manager_name');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('cartorio_id')->references('id')->on('cartorios')->cascadeOnDelete();
        });

        Schema::create('productivity_stats_monthly', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cartorio_id');
            $table->unsignedInteger('reference_year');
            $table->unsignedTinyInteger('reference_month');
            $table->unsignedInteger('ip_instaurados')->default(0);
            $table->unsignedInteger('ip_relatados')->default(0);
            $table->unsignedInteger('cotas')->default(0);
            $table->unsignedInteger('despachos')->default(0);
            $table->unsignedInteger('concluidos')->default(0);
            $table->unsignedInteger('registros')->default(0);
            $table->unsignedInteger('ips_andamento')->default(0);
            $table->unsignedInteger('flagrantes_total')->default(0);
            $table->unsignedInteger('flagrantes_ddm')->default(0);
            $table->unsignedInteger('flagrantes_outras')->default(0);
            $table->string('source_mode')->default('AUTO');
            $table->text('manual_notes')->nullable();
            $table->timestamps();

            $table->unique(['cartorio_id', 'reference_year', 'reference_month'], 'ux_prod_stats_month');
            $table->foreign('cartorio_id')->references('id')->on('cartorios')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productivity_stats_monthly');
        Schema::dropIfExists('cartorio_manager_history');
        Schema::dropIfExists('cartorio_status_history');
        Schema::dropIfExists('cartorios');
    }
};
