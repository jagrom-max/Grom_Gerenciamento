<?php

namespace App\Support\Pdf;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class HeadlessBrowserPdfRenderer
{
    public function renderBlade(string $view, array $data, ?string $filePrefix = null): string
    {
        $html = view($view, $data)->render();

        $outputDirectory = storage_path('app/reports');
        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException('Nao foi possivel preparar a pasta temporaria de relatorios.');
        }

        $token = Str::uuid()->toString();
        $prefix = $filePrefix ? Str::slug($filePrefix) : 'relatorio';
        $htmlPath = $outputDirectory . DIRECTORY_SEPARATOR . $prefix . '-' . $token . '.html';
        $pdfPath = $outputDirectory . DIRECTORY_SEPARATOR . $prefix . '-' . $token . '.pdf';
        $profileDirectory = $outputDirectory . DIRECTORY_SEPARATOR . 'profile-' . $token;

        $this->cleanupTemporaryProfiles($outputDirectory);

        if (! is_dir($profileDirectory) && ! mkdir($profileDirectory, 0775, true) && ! is_dir($profileDirectory)) {
            throw new RuntimeException('Nao foi possivel preparar o perfil temporario do navegador.');
        }

        file_put_contents($htmlPath, $html);

        try {
            $browserBinary = $this->resolveBrowserBinary();

            $process = new Process([
                $browserBinary,
                '--headless=new',
                '--disable-gpu',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--allow-file-access-from-files',
                '--no-pdf-header-footer',
                '--user-data-dir=' . $profileDirectory,
                '--print-to-pdf=' . $pdfPath,
                'file:///' . str_replace('\\', '/', $htmlPath),
            ], base_path());

            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Falha desconhecida ao gerar PDF.');
            }

            if (! is_file($pdfPath) || filesize($pdfPath) === 0) {
                throw new RuntimeException('O navegador nao produziu o PDF esperado.');
            }

            return $pdfPath;
        } finally {
            if (is_file($htmlPath)) {
                @unlink($htmlPath);
            }

            $this->deleteDirectoryIfExists($profileDirectory);
        }
    }

    private function resolveBrowserBinary(): string
    {
        $candidates = array_filter([
            env('GROM_PDF_BROWSER_PATH'),
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Nenhum navegador compatível foi encontrado para gerar o PDF.');
    }

    private function cleanupTemporaryProfiles(string $outputDirectory): void
    {
        $cutoff = now()->subDay()->getTimestamp();

        foreach (File::directories($outputDirectory) as $directory) {
            if (! str_starts_with(basename($directory), 'profile-')) {
                continue;
            }

            $modifiedAt = @filemtime($directory);

            if ($modifiedAt !== false && $modifiedAt < $cutoff) {
                $this->deleteDirectoryIfExists($directory);
            }
        }
    }

    private function deleteDirectoryIfExists(string $directory): void
    {
        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }
    }
}
