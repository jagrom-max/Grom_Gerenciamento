<?php

namespace App\Http\Controllers\Escalas;

use App\Http\Controllers\Controller;
use App\Models\EscalaDia;
use App\Models\EscalaDelegadoExterno;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\EscalaSubstituicaoDdm;
use App\Models\EscalaVersao;
use App\Models\RhFuncionario;
use App\Models\RhHoliday;
use App\Support\Pdf\HeadlessBrowserPdfRenderer;
use App\Support\ReportAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EscalasPrintPdfController extends Controller
{
    public function __construct(
        private readonly HeadlessBrowserPdfRenderer $pdfRenderer,
    ) {
    }

    public function index(Request $request): BinaryFileResponse
    {
        return $this->__invoke($request);
    }

    public function __invoke(Request $request): BinaryFileResponse
    {
        $filters = [
            'ano' => max((int) $request->integer('ano', (int) now()->format('Y')), 2020),
            'mes' => max(min((int) $request->integer('mes', (int) now()->format('n')), 12), 1),
            'versao' => null,
        ];

        // SEMPRE força leitura da versão mais recente
        $phpDias = EscalaDia::diasDoMes($filters['ano'], $filters['mes']);
        $phpVersao = $phpDias->max('versao');

        // Marca conferência obrigatória ao visualizar PDF
        if ($phpDias->isNotEmpty() && $phpVersao) {
            $escalaVersao = EscalaVersao::maisRecente($filters['ano'], $filters['mes']);
            if ($escalaVersao && empty($escalaVersao->conferida_em)) {
                $escalaVersao->marcarConferida();
            }
        }

        $snapshot = [
            'month' => $filters['mes'],
            'year' => $filters['ano'],
            'scale_rows' => [],
            'holidays' => [],
            'afastamentos_mes' => [],
            'plantoes' => [],
            'legacy_snapshot_at' => null,
            'version' => null,
        ];

        $inicio = Carbon::create($filters['ano'], $filters['mes'], 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth();
        $feriados = RhHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', '>=', $inicio->toDateString())
            ->whereDate('holiday_date', '<=', $fim->toDateString())
            ->orderBy('holiday_date')
            ->get()
            ->map(function (RhHoliday $holiday): array {
                return [
                    'date' => $holiday->holiday_date?->toDateString() ?? '',
                    'date_label' => $holiday->holiday_date?->format('d/m') ?? '',
                    'descricao' => $holiday->name,
                    'tipo' => $holiday->scope,
                ];
            })
            ->values()
            ->all();
        $snapshot['holidays'] = $feriados;

        $phpFuncionarios = RhFuncionario::query()
            ->with(['cargo', 'afastamentos' => fn ($q) => $q->where('is_active', true)->orderBy('start_date')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $plantoesMes = [];
        $ids = DB::table('escalas_plantoes_funcionarios')
            ->where('data', 'like', sprintf('%04d-%02d-%%', $filters['ano'], $filters['mes']))
            ->orderBy('data')
            ->pluck('id')
            ->all();
        $atribs = EscalaPlantaoFuncionario::query()
            ->with(['funcionario.cargo', 'plantaoExterno'])
            ->whereIn('id', $ids)
            ->orderBy('data')
            ->get();

        foreach ($atribs as $a) {
            $plantoesMes[$a->data->toDateString()][] = $a;
        }

        $substituicoesDdm = $this->getSubstituicoesDdm($filters);

        $pdfPath = $this->pdfRenderer->renderBlade(
            'escalas.print',
            [
                'filters' => $filters,
                'snapshot' => $snapshot,
                'phpDias' => $phpDias,
                'phpVersao' => $phpVersao,
                'phpFuncionarios' => $phpFuncionarios,
                'feriados' => $feriados,
                'plantoesMes' => $plantoesMes,
                'escalaVersao' => $phpDias->isNotEmpty()
                    ? EscalaVersao::maisRecente($filters['ano'], $filters['mes'])
                    : null,
                'substituicoesDdm' => $substituicoesDdm,
                'delegadosExternos' => EscalaDelegadoExterno::ativos()->get(),
                'brasaoSrc' => ReportAsset::dataUri('assets/brasao.png'),
                'logoSrc' => ReportAsset::dataUri('assets/logo_grom.png'),
                'watermarkSrc' => ReportAsset::dataUri('assets/marca_dagua.png'),
            ],
            sprintf('escala-mensal-%d-%02d', $filters['ano'], $filters['mes'])
        );

        return response()
            ->download($pdfPath, sprintf('escala-mensal-%d-%02d.pdf', $filters['ano'], $filters['mes']), [
                'Content-Type' => 'application/pdf',
            ])
            ->deleteFileAfterSend(true);
    }

    private function getSubstituicoesDdm(array $filters)
    {
        $inicioMes = sprintf('%04d-%02d-01', $filters['ano'], $filters['mes']);
        $fimMes = date('Y-m-t', strtotime($inicioMes));

        return EscalaSubstituicaoDdm::query()
            ->where(function ($q) use ($inicioMes, $fimMes): void {
                $q->whereBetween('data_inicio', [$inicioMes, $fimMes])
                    ->orWhereBetween('data_fim', [$inicioMes, $fimMes])
                    ->orWhere(function ($q2) use ($inicioMes, $fimMes): void {
                        $q2->where('data_inicio', '<=', $inicioMes)
                            ->where('data_fim', '>=', $fimMes);
                    });
            })
            ->orderBy('data_inicio')
            ->get();
    }
}
