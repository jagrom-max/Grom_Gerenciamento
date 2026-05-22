<?php

namespace App\Http\Controllers\Produtividade;

use App\Enums\LavradoUnidade;
use App\Http\Controllers\Controller;
use App\Models\Cartorio;
use App\Models\ProductivityBoletim;
use App\Support\Produtividade\BoletimQueryFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BoletimRelatorioController extends Controller
{
    public function index(Request $request): View
    {
        return $this->__invoke($request);
    }

    public function __invoke(Request $request): View
    {
        $filters = $request->validate(BoletimQueryFilters::validatedRules());

        $user = $request->user();
        $now = Carbon::now();

        $year = (int) ($filters['year'] ?? $now->year);
        $month = (int) ($filters['month'] ?? 0);

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

        $porCartorio = $boletins
            ->groupBy('cartorio_id')
            ->map(function ($group): array {
                $first = $group->first();

                $total = $group->count();
                $totalFlagrantes = $group->where('is_flagrante', true)->count();
                $ddmFlagrantes = $group
                    ->where('is_flagrante', true)
                    ->where('lavrado_unidade.value', LavradoUnidade::Ddm->value)
                    ->count();
                $mpuSemIp = $group
                    ->filter(fn (ProductivityBoletim $boletim): bool => filled($boletim->mpu_numero) && blank($boletim->num_ip))
                    ->count();

                return [
                    'cartorio' => $first->cartorio,
                    'total' => $total,
                    'flagrantes' => $totalFlagrantes,
                    'nao_flagrantes' => max($total - $totalFlagrantes, 0),
                    'ddm' => $ddmFlagrantes,
                    'outras' => max($totalFlagrantes - $ddmFlagrantes, 0),
                    'mpu_sem_ip' => $mpuSemIp,
                    'boletins' => $group,
                ];
            })
            ->values();

        $totalGeral = $boletins->count();
        $totalFlagrantes = $boletins->where('is_flagrante', true)->count();
        $totalDdm = $boletins
            ->where('is_flagrante', true)
            ->where('lavrado_unidade.value', LavradoUnidade::Ddm->value)
            ->count();
        $totalComMpu = $boletins->filter(fn (ProductivityBoletim $boletim): bool => filled($boletim->mpu_numero))->count();
        $totalSemIp = $boletins->filter(fn (ProductivityBoletim $boletim): bool => blank($boletim->num_ip))->count();
        $totalMpuSemIp = $boletins->filter(fn (ProductivityBoletim $boletim): bool => filled($boletim->mpu_numero) && blank($boletim->num_ip))->count();
        $pendenciasCriticas = $boletins
            ->filter(fn (ProductivityBoletim $boletim): bool =>
                filled($boletim->mpu_numero)
                && blank($boletim->num_ip)
                && ! $boletim->encaminhado_outra_unidade
                && $boletim->mpu_decisao !== 'INDEFERIDA'
                && ! $boletim->despacho_fundamentado
            )
            ->sortByDesc(fn (ProductivityBoletim $boletim): int => $boletim->updated_at?->getTimestamp() ?? 0)
            ->take(120)
            ->values();

        $periodoLabel = $month > 0
            ? Carbon::create($year, $month, 1)->translatedFormat('F \\d\\e Y')
            : (string) $year;

        return view('produtividade.boletins.relatorio', compact(
            'filters',
            'cartorios',
            'cartorio',
            'year',
            'month',
            'periodoLabel',
            'porCartorio',
            'totalGeral',
            'totalFlagrantes',
            'totalDdm',
            'totalComMpu',
            'totalSemIp',
            'totalMpuSemIp',
            'pendenciasCriticas',
        ));
    }
}
