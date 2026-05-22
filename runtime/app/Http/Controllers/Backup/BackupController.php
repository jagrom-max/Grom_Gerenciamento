<?php

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    /**
     * Compatibilidade com rotas explícitas: delega para __invoke().
     */
    public function index(): View
    {
        return $this->__invoke();
    }
    public function __invoke(): View
    {
        $storagePath = storage_path('app');
        $reportPath = storage_path('app/reports');
        $legacyPath = base_path('../main/grom_database.sqlite3');
        $localDbFiles = collect(File::glob($storagePath . DIRECTORY_SEPARATOR . '*.sqlite*'))
            ->sortByDesc(fn (string $path): int => File::lastModified($path))
            ->values()
            ->map(fn (string $path): array => $this->fileSummary($path));

        $recentReports = collect(File::glob($reportPath . DIRECTORY_SEPARATOR . '*.pdf'))
            ->sortByDesc(fn (string $path): int => File::lastModified($path))
            ->take(8)
            ->values()
            ->map(fn (string $path): array => $this->fileSummary($path));

        return view('backup.index', [
            'metrics' => [
                'sqlite_files' => $localDbFiles->count(),
                'report_pdfs' => $recentReports->count(),
                'legacy_exists' => File::exists($legacyPath),
            ],
            'localDbFiles' => $localDbFiles,
            'recentReports' => $recentReports,
            'paths' => [
                'storage' => $storagePath,
                'reports' => $reportPath,
                'legacy' => $legacyPath,
            ],
            'generatedAt' => Carbon::now(),
        ]);
    }

    private function fileSummary(string $path): array
    {
        return [
            'name' => basename($path),
            'path' => $path,
            'size' => File::exists($path) ? File::size($path) : 0,
            'modified_at' => Carbon::createFromTimestampUTC(File::lastModified($path))->setTimezone(config('app.timezone', 'UTC')),
        ];
    }
}
