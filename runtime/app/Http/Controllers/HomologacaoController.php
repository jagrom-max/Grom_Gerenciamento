<?php

namespace App\Http\Controllers;

use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ProductivityFlagrante;
use App\Services\Escalas\LegacyEscalasReader;
use App\Models\RhHoliday;
use App\Models\RhAfastamento;
use App\Models\RhDelegadoExterno;
use App\Models\RhFuncionario;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;

class HomologacaoController extends Controller
{
    public function __invoke(LegacyEscalasReader $legacyEscalasReader): View
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        try {
            $escalaSnapshot = $legacyEscalasReader->snapshotForMonth(null);
        } catch (\RuntimeException) {
            $escalaSnapshot = ['summary' => ['dias_total' => 0, 'plantoes_atribuicoes' => 0]];
        }
        $backupSqliteFiles = collect(File::glob(storage_path('app') . DIRECTORY_SEPARATOR . '*.sqlite*'))->count();

        $metrics = [
            'usuarios' => User::query()->count(),
            'roles' => Role::query()->count(),
            'cartorios' => Cartorio::query()->count(),
            'lotes' => ImportBatch::query()->count(),
            'flagrantes' => ProductivityFlagrante::query()->where('is_active', true)->count(),
            'escalas_dias' => $escalaSnapshot['summary']['dias_total'] ?? 0,
            'escalas_plantoes' => $escalaSnapshot['summary']['plantoes_atribuicoes'] ?? 0,
            'calendarios_feriados' => RhHoliday::query()->where('is_active', true)->count(),
            'backup_sqlite_files' => $backupSqliteFiles,
            'rh_funcionarios' => RhFuncionario::query()->count(),
            'rh_afastamentos' => RhAfastamento::query()->where('is_active', true)->count(),
            'rh_delegados_externos' => RhDelegadoExterno::query()->where('is_active', true)->count(),
        ];

        return view('homologacao.index', [
            'metrics' => $metrics,
            'modules' => [
                [
                    'title' => 'Acesso e seguranca',
                    'status' => 'Pronto para aprovacao',
                    'description' => 'RBAC, usuarios, perfis, escopos e auditoria estao integrados ao fluxo principal.',
                    'link' => route('access.users.index'),
                ],
                [
                    'title' => 'Operacional',
                    'status' => 'Painel consolidado',
                    'description' => 'A entrada operacional agrega cartorios, flagrantes, lote e ranking sem duplicar regras do piloto.',
                    'link' => route('operacional.index'),
                ],
                [
                    'title' => 'Produtividade',
                    'status' => 'Em validacao final',
                    'description' => 'Cartorios, fila de flagrantes, importacao e escopos operacionais estao aplicados de ponta a ponta.',
                    'link' => route('produtividade.flagrantes.index'),
                ],
                [
                    'title' => 'Relatorios',
                    'status' => 'Padronizado',
                    'description' => 'O shell A4 e unico para toda a plataforma, com cabecalho, rodape e identidade consistentes.',
                    'link' => route('relatorios.index'),
                ],
                [
                    'title' => 'Funcionarios',
                    'status' => 'Base pronta',
                    'description' => 'Cargos, funcionarios, afastamentos, feriados e delegados externos ja estao disponiveis para evolucao controlada.',
                    'link' => route('funcionarios.index'),
                ],
                [
                    'title' => 'Escalas',
                    'status' => 'Leitura legada',
                    'description' => 'A escala mensal e os plantoes externos ja podem ser consultados a partir da base SQLite consolidada.',
                    'link' => route('escalas.index'),
                ],
                [
                    'title' => 'Agenda',
                    'status' => 'Base pronta',
                    'description' => 'A agenda de afastamentos do RH e os feriados de contexto ja aparecem em um painel unico de consulta.',
                    'link' => route('calendarios.index'),
                ],
                [
                    'title' => 'Backup',
                    'status' => 'Somente leitura',
                    'description' => 'Arquivos SQLite locais e PDFs recentes ficam visiveis para conferencia segura do ambiente.',
                    'link' => route('backup.index'),
                ],
                [
                    'title' => 'Analise de Dados',
                    'status' => 'Entrada estruturada',
                    'description' => 'A consolidacao legada, a fila de itens e a trilha de lotes ja funcionam como ponte de migracao.',
                    'link' => route('analise.index'),
                ],
            ],
            'reviewLinks' => [
                [
                    'label' => 'Dashboard',
                    'route' => route('dashboard'),
                    'permission' => null,
                ],
                [
                    'label' => 'Operacional',
                    'route' => route('operacional.index'),
                    'permission' => 'operacional.view',
                ],
                [
                    'label' => 'Produtividade',
                    'route' => route('produtividade.flagrantes.index'),
                    'permission' => 'produtividade.flagrantes.view',
                ],
                [
                    'label' => 'Estatisticas',
                    'route' => route('produtividade.stats.index'),
                    'permission' => 'produtividade.stats.view',
                ],
                [
                    'label' => 'Funcionarios',
                    'route' => route('funcionarios.index'),
                    'permission' => 'rh.view',
                ],
                [
                    'label' => 'Escalas',
                    'route' => route('escalas.index'),
                    'permission' => 'escalas.view',
                ],
                [
                    'label' => 'Agenda de afastamentos',
                    'route' => route('calendarios.index'),
                    'permission' => 'calendarios.view',
                ],
                [
                    'label' => 'Backup',
                    'route' => route('backup.index'),
                    'permission' => 'backup.view',
                ],
                [
                    'label' => 'Relatorios',
                    'route' => route('relatorios.index'),
                    'permission' => 'relatorios.emit',
                ],
                [
                    'label' => 'Analise',
                    'route' => route('analise.index'),
                    'permission' => 'analise.view',
                ],
                [
                    'label' => 'Auditoria',
                    'route' => route('auditoria.index'),
                    'permission' => 'auditoria.view',
                ],
                [
                    'label' => 'Usuarios',
                    'route' => route('access.users.index'),
                    'permission' => 'access.users.view',
                ],
                [
                    'label' => 'Perfis',
                    'route' => route('access.roles.index'),
                    'permission' => 'access.roles.view',
                ],
            ],
        ]);
    }
}
