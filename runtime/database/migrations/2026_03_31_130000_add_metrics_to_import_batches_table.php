<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table): void {
            if (! Schema::hasColumn('import_batches', 'source_type')) {
                $table->string('source_type')->nullable()->after('source_name');
            }
            if (! Schema::hasColumn('import_batches', 'sheet_name')) {
                $table->string('sheet_name')->nullable()->after('source_hash');
            }
            if (! Schema::hasColumn('import_batches', 'header_row')) {
                $table->unsignedInteger('header_row')->nullable()->after('sheet_name');
            }
            if (! Schema::hasColumn('import_batches', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('imported_at');
            }
            if (! Schema::hasColumn('import_batches', 'rows_staged')) {
                $table->unsignedInteger('rows_staged')->default(0)->after('total_rows');
            }
            if (! Schema::hasColumn('import_batches', 'rows_updated')) {
                $table->unsignedInteger('rows_updated')->default(0)->after('rows_staged');
            }
            if (! Schema::hasColumn('import_batches', 'rows_skipped')) {
                $table->unsignedInteger('rows_skipped')->default(0)->after('rows_updated');
            }
            if (! Schema::hasColumn('import_batches', 'error_count')) {
                $table->unsignedInteger('error_count')->default(0)->after('rows_skipped');
            }
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table): void {
            foreach (['error_count', 'rows_skipped', 'rows_updated', 'rows_staged', 'processed_at', 'header_row', 'sheet_name', 'source_type'] as $column) {
                if (Schema::hasColumn('import_batches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
