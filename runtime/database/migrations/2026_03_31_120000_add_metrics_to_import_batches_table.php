<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('import_batches', 'source_type')) {
            Schema::table('import_batches', function (Blueprint $table): void {
                $table->string('source_type')->nullable()->after('source_name');
            });
        }

        if (! Schema::hasColumn('import_batches', 'sheet_name')) {
            Schema::table('import_batches', function (Blueprint $table): void {
                $table->string('sheet_name')->nullable()->after('source_hash');
            });
        }

        if (! Schema::hasColumn('import_batches', 'header_row')) {
            Schema::table('import_batches', function (Blueprint $table): void {
                $table->unsignedInteger('header_row')->nullable()->after('sheet_name');
            });
        }

        if (! Schema::hasColumn('import_batches', 'processed_at')) {
            Schema::table('import_batches', function (Blueprint $table): void {
                $table->timestamp('processed_at')->nullable()->after('imported_at');
            });
        }

        if (! Schema::hasColumn('import_batches', 'rows_staged')) {
            Schema::table('import_batches', function (Blueprint $table): void {
                $table->unsignedInteger('rows_staged')->default(0)->after('total_rows');
            });
        }

        if (! Schema::hasColumn('import_batches', 'rows_updated')) {
            Schema::table('import_batches', function (Blueprint $table): void {
                $table->unsignedInteger('rows_updated')->default(0)->after('rows_staged');
            });
        }

        if (! Schema::hasColumn('import_batches', 'rows_skipped')) {
            Schema::table('import_batches', function (Blueprint $table): void {
                $table->unsignedInteger('rows_skipped')->default(0)->after('rows_updated');
            });
        }

        if (! Schema::hasColumn('import_batches', 'error_count')) {
            Schema::table('import_batches', function (Blueprint $table): void {
                $table->unsignedInteger('error_count')->default(0)->after('rows_skipped');
            });
        }
    }

    public function down(): void
    {
        // Mantido sem rollback destrutivo para preservar historico de lotes.
    }
};
