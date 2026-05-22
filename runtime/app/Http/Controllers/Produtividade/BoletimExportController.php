<?php

namespace App\Http\Controllers\Produtividade;

use App\Enums\LavradoUnidade;
use App\Http\Controllers\Controller;
use App\Models\Cartorio;
use App\Models\ProductivityBoletim;
use App\Support\Produtividade\BoletimQueryFilters;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BoletimExportController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->__invoke($request);
    }

    public function __invoke(Request $request): Response
    {
        $filters = $request->validate(BoletimQueryFilters::validatedRules());

        $user = $request->user();
        $year = (int) ($filters['year'] ?? now()->year);
        $month = array_key_exists('month', $filters) ? (int) $filters['month'] : 0;

        $cartorios = Cartorio::query()
            ->visibleTo($user)
            ->orderBy('number')
            ->get();

        $cartorio = isset($filters['cartorio_id'])
            ? $cartorios->firstWhere('id', $filters['cartorio_id'])
            : null;

        $scopeCartorioIds = $cartorio ? [$cartorio->id] : $cartorios->pluck('id')->all();

        $query = BoletimQueryFilters::apply(
            ProductivityBoletim::query()->with('cartorio'),
            $filters,
            $scopeCartorioIds,
        )
            ->orderBy('reference_month')
            ->orderBy('data_fato')
            ->orderBy('spj');

        $boletins = $query->get();
        $periodLabel = $month > 0
            ? now()->setDate($year, $month, 1)->translatedFormat('F \\d\\e Y')
            : (string) $year;

        return $this->csvResponse(
            sprintf('produtividade-boletins-%d-%02d.csv', $year, max($month, 1)),
            sprintf('Boletins de ocorrencia %s', $periodLabel),
            [[
                'cartorio',
                'codigo_cartorio',
                'mes',
                'data_fato',
                'spj',
                'tipo',
                'lavrado_unidade',
                'mpu_numero',
                'mpu_decisao',
                'despacho_fundamentado',
                'encaminhado_outra_unidade',
                'encaminhado_para_unidade',
                'num_ip',
                'num_ipe',
                'num_cnj',
                'mpu_sem_ip',
                'pendencia_critica_mpu_sem_ip',
                'flagrante_vinculado',
            ]],
            $boletins->map(function (ProductivityBoletim $boletim): array {
                $mpuSemIp = filled($boletim->mpu_numero) && blank($boletim->num_ip);
                $pendenciaCritica = $mpuSemIp
                    && ! $boletim->encaminhado_outra_unidade
                    && $boletim->mpu_decisao !== 'INDEFERIDA'
                    && ! $boletim->despacho_fundamentado;

                return [
                    $boletim->cartorio?->name,
                    $boletim->cartorio?->code,
                    sprintf('%02d/%04d', (int) $boletim->reference_month, (int) $boletim->reference_year),
                    $boletim->data_fato?->format('Y-m-d'),
                    $boletim->spj,
                    $boletim->is_flagrante ? 'Flagrante' : 'Nao-flagrante',
                    $boletim->lavrado_unidade === LavradoUnidade::Ddm ? 'DDM' : 'OUTRAS_UNIDADES',
                    $boletim->mpu_numero,
                    $boletim->mpu_decisao,
                    $boletim->despacho_fundamentado ? 'Sim' : 'Nao',
                    $boletim->encaminhado_outra_unidade ? 'Sim' : 'Nao',
                    $boletim->encaminhado_para_unidade,
                    $boletim->num_ip,
                    $boletim->num_ipe,
                    $boletim->num_cnj,
                    $mpuSemIp ? 'Sim' : 'Nao',
                    $pendenciaCritica ? 'Sim' : 'Nao',
                    $boletim->productivity_flagrante_id ? 'Sim' : 'Nao',
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
