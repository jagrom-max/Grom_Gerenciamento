<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ProductivityFlagrante;
use App\Models\RhFuncionario;
use Illuminate\Contracts\View\View;

class RelatoriosController extends Controller
{
    public function __invoke(): View
    {
        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');

        return view('relatorios.index', [
            'metrics' => [
                'cartorios_ativos' => Cartorio::query()->where('is_active', true)->count(),
                'flagrantes_mes' => ProductivityFlagrante::query()
                    ->where('is_active', true)
                    ->where('reference_year', $currentYear)
                    ->where('reference_month', $currentMonth)
                    ->count(),
                'funcionarios_rh' => RhFuncionario::query()->count(),
                'lotes_importados' => ImportBatch::query()->count(),
                'eventos_auditoria_30d' => AuditEvent::query()
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
            ],
            'templates' => [
                [
                    'name' => 'Produtividade mensal A4',
                    'status' => 'Pronto para consolidacao visual',
                    'scope' => 'Fechamento por cartorio, DDM e outras unidades.',
                    'output' => 'Preview HTML e PDF timbrado em formato institucional com cartorios reais espelhados.',
                    'route' => route('relatorios.produtividade.a4', ['year' => $currentYear, 'month' => $currentMonth]),
                    'pdf_route' => route('relatorios.produtividade.a4.pdf', ['year' => $currentYear, 'month' => $currentMonth]),
                ],
                [
                    'name' => 'Fila de flagrantes',
                    'status' => 'Base funcional ja modelada',
                    'scope' => 'Pendencias, confirmacoes e saneamento sem cartorio.',
                    'output' => 'Visao operacional e exportacao futura.',
                    'route' => null,
                ],
                [
                    'name' => 'Auditoria de acesso',
                    'status' => 'Trilha ativa',
                    'scope' => 'Login, logout, criacao e alteracao de usuarios.',
                    'output' => 'Historico consultavel e rastreavel.',
                    'route' => route('auditoria.index'),
                ],
                [
                    'name' => 'Produtividade operacional',
                    'status' => 'Painel pronto para uso',
                    'scope' => 'Ranking, pendencias e evolucao mensal.',
                    'output' => 'Visao consolidada para gestao e saneamento.',
                    'route' => route('produtividade.stats.index'),
                ],
                [
                    'name' => 'Acompanhamento operacional integrado',
                    'status' => 'Pronto para validacao',
                    'scope' => 'Produtividade, RH, escalas e cartorios reais em uma leitura unica.',
                    'output' => 'Relatorio institucional com confronto entre espelho PHP e base legada.',
                    'route' => route('relatorios.operacional.integrado', ['year' => $currentYear, 'month' => $currentMonth]),
                    'pdf_route' => route('relatorios.operacional.integrado.pdf', ['year' => $currentYear, 'month' => $currentMonth]),
                ],
            ],
        ]);
    }
}
