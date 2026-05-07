<?php

namespace App\Http\Controllers\Produtividade;

use App\Http\Controllers\Controller;
use App\Models\Cartorio;
use App\Models\ProductivityFlagrante;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Relatório imprimível de flagrantes por cartório e período.
 * Rota: GET /produtividade/flagrantes/relatorio
 */
class FlagrantesRelatorioController extends Controller
    public function index(Request $request): View
    {
        return $this->__invoke($request);
    }
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'cartorio_id' => ['nullable', 'exists:cartorios,id'],
            'year'        => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month'       => ['nullable', 'integer', 'min:0', 'max:12'],
        ]);

        $user = $request->user();
        $now  = Carbon::now();

        $year  = (int) ($filters['year']  ?? $now->year);
        $month = (int) ($filters['month'] ?? $now->month);

        $cartorios = Cartorio::query()
            ->visibleTo($user)
            ->orderBy('number')
            ->get();

        $cartorio = isset($filters['cartorio_id'])
            ? $cartorios->firstWhere('id', $filters['cartorio_id'])
            : null;

        $query = ProductivityFlagrante::query()
            ->with('cartorio')
            ->where('is_active', true)
            ->when($cartorio, fn ($q) => $q->where('cartorio_id', $cartorio->id))
            ->when(! $cartorio, fn ($q) => $q->whereIn('cartorio_id', $cartorios->pluck('id')))
            ->where('reference_year', $year)
            ->when($month > 0, fn ($q) => $q->where('reference_month', $month))
            ->orderBy('reference_month')
            ->orderBy('data_fato')
            ->orderBy('spj');

        $flagrantes = $query->get();

        // Agrupar por cartório para totais
        $porCartorio = $flagrantes->groupBy('cartorio_id')->map(function ($group): array {
            /** @var ProductivityFlagrante $first */
            $first = $group->first();
            return [
                'cartorio'  => $first->cartorio,
                'total'     => $group->count(),
                'ddm'       => $group->filter(fn ($f) => $f->lavrado_unidade?->value === 'DDM')->count(),
                'outras'    => $group->filter(fn ($f) => $f->lavrado_unidade?->value !== 'DDM')->count(),
                'flagrantes'=> $group,
            ];
        })->values();

        $totalGeral = $flagrantes->count();
        $totalDdm   = $flagrantes->filter(fn ($f) => $f->lavrado_unidade?->value === 'DDM')->count();

        $periodoLabel = $month > 0
            ? Carbon::create($year, $month, 1)->translatedFormat('F \d\e Y')
            : (string) $year;

        return view('produtividade.flagrantes.relatorio', compact(
            'filters', 'cartorios', 'cartorio',
            'porCartorio', 'totalGeral', 'totalDdm',
            'year', 'month', 'periodoLabel',
        ));
    }
}
