<?php

namespace App\Http\Controllers\Produtividade;

use App\Http\Controllers\Controller;
use App\Support\Produtividade\ProdutividadeStatsDashboardData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function index(Request $request): View
    {
        return $this->__invoke($request);
    }
    public function __construct(private readonly ProdutividadeStatsDashboardData $dashboardData)
    {
    }

    public function __invoke(Request $request): View
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

        return view('produtividade.stats.index', $this->dashboardData->build(
            $request->user(),
            $year,
            $month,
            $filters['cartorio_id'] ?? null,
        ) + [
            'filters' => $filters,
        ]);
    }
}
