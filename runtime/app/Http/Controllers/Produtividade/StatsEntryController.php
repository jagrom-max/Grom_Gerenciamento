<?php

namespace App\Http\Controllers\Produtividade;

use App\Http\Controllers\Controller;
use App\Models\Cartorio;
use App\Models\ProductivityStatMonthly;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatsEntryController extends Controller
{
    private const MONTHS = [
        1  => 'Janeiro',
        2  => 'Fevereiro',
        3  => 'Março',
        4  => 'Abril',
        5  => 'Maio',
        6  => 'Junho',
        7  => 'Julho',
        8  => 'Agosto',
        9  => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];

    public function create(Cartorio $cartorio, Request $request): View
    {
        $this->ensureCanAccessCartorio($request->user(), $cartorio);

        $year  = max((int) $request->integer('year', (int) now()->format('Y')), 2020);
        $month = min(max((int) $request->integer('month', (int) now()->format('n')), 1), 12);

        $existing = ProductivityStatMonthly::query()
            ->where('cartorio_id', $cartorio->id)
            ->where('reference_year', $year)
            ->where('reference_month', $month)
            ->first();

        // Evolução anual para referência
        $yearBreakdown = ProductivityStatMonthly::query()
            ->where('cartorio_id', $cartorio->id)
            ->where('reference_year', $year)
            ->orderBy('reference_month')
            ->get()
            ->keyBy('reference_month');

        // Mês anterior para comparação
        $prevYear  = $month === 1 ? $year - 1 : $year;
        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevRecord = ProductivityStatMonthly::query()
            ->where('cartorio_id', $cartorio->id)
            ->where('reference_year', $prevYear)
            ->where('reference_month', $prevMonth)
            ->first();

        return view('produtividade.cartorios.fechamento', [
            'cartorio'      => $cartorio,
            'year'          => $year,
            'month'         => $month,
            'months'        => self::MONTHS,
            'existing'      => $existing,
            'prevRecord'    => $prevRecord,
            'prevYear'      => $prevYear,
            'prevMonth'     => $prevMonth,
            'yearBreakdown' => $yearBreakdown,
        ]);
    }

    public function store(Cartorio $cartorio, Request $request): RedirectResponse
    {
        $this->ensureCanAccessCartorio($request->user(), $cartorio);

        $data = $request->validate([
            'year'           => ['required', 'integer', 'min:2020', 'max:2100'],
            'month'          => ['required', 'integer', 'min:1', 'max:12'],
            'ip_instaurados' => ['required', 'integer', 'min:0'],
            'ip_relatados'   => ['required', 'integer', 'min:0'],
            'cotas'          => ['required', 'integer', 'min:0'],
            'despachos'      => ['required', 'integer', 'min:0'],
            'concluidos'     => ['required', 'integer', 'min:0'],
            'registros'      => ['required', 'integer', 'min:0'],
            'ips_andamento'  => ['required', 'integer', 'min:0'],
            'manual_notes'   => ['nullable', 'string', 'max:2000'],
        ]);

        $isNew = ! ProductivityStatMonthly::query()
            ->where('cartorio_id', $cartorio->id)
            ->where('reference_year', $data['year'])
            ->where('reference_month', $data['month'])
            ->exists();

        $record = ProductivityStatMonthly::updateOrCreate(
            [
                'cartorio_id'     => $cartorio->id,
                'reference_year'  => $data['year'],
                'reference_month' => $data['month'],
            ],
            [
                'ip_instaurados' => $data['ip_instaurados'],
                'ip_relatados'   => $data['ip_relatados'],
                'cotas'          => $data['cotas'],
                'despachos'      => $data['despachos'],
                'concluidos'     => $data['concluidos'],
                'registros'      => $data['registros'],
                'ips_andamento'  => $data['ips_andamento'],
                'manual_notes'   => $data['manual_notes'] ?? null,
                'source_mode'    => 'MANUAL',
            ]
        );

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: $isNew ? 'stats.fechamento_created' : 'stats.fechamento_updated',
            entityType: 'productivity_stat_monthly',
            entityId: $record->id,
            description: sprintf(
                'Fechamento mensal %s %s/%d — %s via formulário.',
                $isNew ? 'criado' : 'atualizado',
                self::MONTHS[$data['month']],
                $data['year'],
                $cartorio->name
            ),
            metadata: [
                'cartorio_id' => $cartorio->id,
                'year'        => $data['year'],
                'month'       => $data['month'],
            ]
        );

        $monthLabel = self::MONTHS[$data['month']] . ' ' . $data['year'];

        return redirect()
            ->route('produtividade.cartorios.fechamento.create', [
                'cartorio' => $cartorio,
                'year'     => $data['year'],
                'month'    => $data['month'],
            ])
            ->with('status', "Fechamento de {$monthLabel} salvo com sucesso.");
    }

    private function ensureCanAccessCartorio(?User $user, Cartorio $cartorio): void
    {
        abort_unless($user !== null, 403);

        $scopedIds = $user->scopeKeys('cartorio_id')->all();
        if ($scopedIds === []) {
            return; // sem restricao de escopo: acesso irrestrito
        }

        abort_unless(in_array((string) $cartorio->id, $scopedIds, true), 403);
    }
}
