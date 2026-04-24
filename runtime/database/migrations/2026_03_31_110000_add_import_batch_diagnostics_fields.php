<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table): void {
            $table->string('source_type')->nullable()->after('source_name');
            $table->string('sheet_name')->nullable()->after('source_hash');
            $table->unsignedInteger('header_row')->nullable()->after('sheet_name');
            $table->timestamp('processed_at')->nullable()->after('imported_at');
            $table->unsignedInteger('rows_staged')->default(0)->after('total_rows');
            $table->unsignedInteger('rows_updated')->default(0)->after('rows_staged');
            $table->unsignedInteger('rows_skipped')->default(0)->after('rows_updated');
            $table->unsignedInteger('error_count')->default(0)->after('rows_skipped');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table): void {
            $table->dropColumn([
                'source_type',
                'sheet_name',
                'header_row',
                'processed_at',
                'rows_staged',
                'rows_updated',
                'rows_skipped',
                'error_count',
            ]);
        });
    }
};
