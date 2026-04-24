<?php

namespace App\Http\Controllers\Produtividade;

use App\Http\Controllers\Controller;
use App\Support\Produtividade\ProdutividadeStatsDashboardData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatsExportController extends Controller
{
    public function __construct(private readonly ProdutividadeStatsDashboardData $dashboardData)
    {
    }

    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:0', 'max:12'],
            'cartorio_id' => ['nullable', 'exists:cartorios,id'],
        ]);

        $latestPeriod = $this->dashboardData->latestAvailablePeriod();
        $year = max((int) ($filters['year'] ?? $latestPeriod['year']), 2020);
        $month = array_key_exists('month', $filters)
            ? max((int) $filters['month'], 0)
            : $latestPeriod['month'];

        $data = $this->dashboardData->build(
            $request->user(),
            $year,
            $month,
            $filters['cartorio_id'] ?? null,
        );

        return $this->csvResponse(
            sprintf('produtividade-estatisticas-%d-%02d.csv', $year, max($month, 1)),
            sprintf('Estatisticas de produtividade %s', $data['periodLabel']),
            [[
                'cartorio',
                'code',
                'ip_instaurados',
                'ip_relatados',
                'concluidos',
                'registros',
                'ips_andamento',
                'flagrantes_total',
                'flagrantes_ddm',
                'flagrantes_outras',
                'pendencias_abertas',
                'last_updated_at',
            ]],
            $data['ranking']->map(function (array $row): array {
                return [
                    $row['cartorio']?->name,
                    $row['cartorio']?->code,
                    $row['ip_instaurados'],
                    $row['ip_relatados'],
                    $row['concluidos'],
                    $row['registros'],
                    $row['ips_andamento'],
                    $row['flagrantes_total'],
                    $row['flagrantes_ddm'],
                    $row['flagrantes_outras'],
                    $row['pending_items'],
                    $row['last_updated_at']?->format('Y-m-d H:i:s'),
                ];
            })->all(),
        );
    }

    private function csvResponse(string $fileName, string $title, array $headers, array $rows): Response
    {
        $handle = fopen('php://temp', 'wb+');

        if ($handle === false) {
            abort(500, 'Nao foi possivel preparar a exportacao.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [$title], ';');
        fputcsv($handle, [], ';');

        foreach ($headers as $headerRow) {
            fputcsv($handle, $headerRow, ';');
        }

        foreach ($rows as $row) {
            fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row), ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
        ]);
    }

    /**
     * Previne CSV Injection (OWASP): valores iniciados com =, +, -, @, tab ou CR
     * sao prefixados com apostrofo para que Excel/Sheets nao os interprete como formula.
     */
    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
