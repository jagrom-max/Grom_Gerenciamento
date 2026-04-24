<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacional_objetos', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // --- Identificação ---
            $table->string('rdo_num', 30)->nullable();
            $table->unsignedSmallInteger('ano')->nullable();
            $table->string('lacre', 40)->nullable();

            // --- Referência de IP/TC ---
            $table->string('ip_tc_ddm', 60)->nullable()->comment('IP ou TC da DDM');
            $table->string('ip_externo', 60)->nullable()->comment('IP externo (campo ip_e do legado)');

            // --- Descrição do objeto ---
            $table->string('tipo_objeto', 120)->nullable();
            $table->text('objeto');
            $table->unsignedSmallInteger('quantidade')->default(1);
            $table->string('unidade', 30)->nullable();
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('cor', 50)->nullable();
            $table->string('numero_serie', 80)->nullable();

            // --- IC ---
            $table->string('ic_remessa', 60)->nullable()->comment('Número ou data de remessa ao IC');
            $table->string('ic_retorno', 60)->nullable()->comment('Número ou data de retorno do IC');
            $table->string('lacre_ic', 40)->nullable();
            $table->string('laudo', 80)->nullable()->comment('Número do laudo pericial');

            // --- Custódia ---
            $table->foreignId('local_custodia_id')->nullable()->constrained('operacional_objetos_locais')->nullOnDelete();
            $table->string('caixa', 30)->nullable();

            // --- Situação ---
            $table->string('situacao', 40)->default('Em Custódia')->index();

            // --- Destinação ---
            $table->string('dest_solicitado', 80)->nullable();
            $table->date('dest_data_solicitado')->nullable();
            $table->string('dest_autorizado', 80)->nullable();
            $table->date('dest_data_autorizado')->nullable();
            $table->string('dest_status', 40)->nullable();
            $table->date('dest_data')->nullable();

            $table->text('observacoes')->nullable();

            // --- Auditoria ---
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->text('deleted_motivo')->nullable();

            // --- Legado ---
            $table->unsignedInteger('legacy_id')->nullable()->unique();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['rdo_num', 'ano'], 'idx_obj_rdo');
            $table->index('situacao', 'idx_obj_situacao');
            $table->index('local_custodia_id', 'idx_obj_local');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacional_objetos');
    }
};
