<?php

namespace App\Support\Reports;

use App\Models\Cartorio;

class ProdutividadeA4ReportData
{
    public function build(int $year, int $month): array
    {
        $cartorios = Cartorio::query()
            ->with([
                'monthlyStats' => fn ($query) => $query
                    ->where('reference_year', $year)
                    ->where('reference_month', $month),
            ])
            ->orderBy('number')
            ->get();

        $rows = $cartorios->map(function (Cartorio $cartorio): array {
            $stats = $cartorio->monthlyStats->first();

            return [
                'cartorio' => $cartorio,
                'ip_instaurados' => (int) ($stats?->ip_instaurados ?? 0),
                'ip_relatados' => (int) ($stats?->ip_relatados ?? 0),
                'cotas' => (int) ($stats?->cotas ?? 0),
                'despachos' => (int) ($stats?->despachos ?? 0),
                'concluidos' => (int) ($stats?->concluidos ?? 0),
                'registros' => (int) ($stats?->registros ?? 0),
                'ips_andamento' => (int) ($stats?->ips_andamento ?? 0),
                'flagrantes_ddm' => (int) ($stats?->flagrantes_ddm ?? 0),
                'flagrantes_outras' => (int) ($stats?->flagrantes_outras ?? 0),
                'flagrantes_total' => (int) ($stats?->flagrantes_total ?? 0),
                'last_updated_at' => $stats?->updated_at,
            ];
        });

        $grandTotal = max((int) $rows->sum('flagrantes_total'), 0);

        return [
            'year' => $year,
            'month' => $month,
            'monthLabel' => now()->copy()->setYear($year)->setMonth($month)->translatedFormat('F \\d\\e Y'),
            'generatedAt' => now(),
            'rows' => $rows,
            'grandTotal' => $grandTotal,
            'grandIpInstaurados' => max((int) $rows->sum('ip_instaurados'), 0),
            'grandIpRelatados' => max((int) $rows->sum('ip_relatados'), 0),
            'grandCotas' => max((int) $rows->sum('cotas'), 0),
            'grandDespachos' => max((int) $rows->sum('despachos'), 0),
            'grandConcluidos' => max((int) $rows->sum('concluidos'), 0),
            'grandRegistros' => max((int) $rows->sum('registros'), 0),
            'grandIpsAndamento' => max((int) $rows->sum('ips_andamento'), 0),
            'grandDdm' => max((int) $rows->sum('flagrantes_ddm'), 0),
            'grandOutras' => max((int) $rows->sum('flagrantes_outras'), 0),
        ];
    }
}
