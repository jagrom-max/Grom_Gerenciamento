<?php

namespace App\Http\Controllers;

use App\Models\Cartorio;
use App\Models\EscalaDia;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\ImportBatch;
use App\Models\ProductivityFlagrante;
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
    public function __invoke(): View
    {
        // ...existing code...
        return $this->index();
    }

    public function index(): View
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $backupSqliteFiles = collect(File::glob(storage_path('app') . DIRECTORY_SEPARATOR . '*.sqlite*'))->count();

        $metrics = [
            'usuarios' => User::query()->count(),
            'roles' => Role::query()->count(),
            'cartorios' => Cartorio::query()->count(),
            'lotes' => ImportBatch::query()->count(),
            'flagrantes' => ProductivityFlagrante::query()->where('is_active', true)->count(),
            'escalas_dias' => EscalaDia::query()->count(),
            'escalas_plantoes' => EscalaPlantaoFuncionario::query()->count(),
            'calendarios_feriados' => RhHoliday::query()->where('is_active', true)->count(),
            'backup_sqlite_files' => $backupSqliteFiles,
            'rh_funcionarios' => RhFuncionario::query()->count(),
            'rh_afastamentos' => RhAfastamento::query()->where('is_active', true)->count(),
            'rh_delegados_externos' => RhDelegadoExterno::query()->where('is_active', true)->count(),
        ];

        return view('homologacao.index', [
            'metrics' => $metrics,
            'modules' => [
                // ...existing code...
            ],
            'reviewLinks' => [
                // ...existing code...
            ],
        ]);
    }

    public function evolucao(): View
    {
        // Implementação inicial para evitar erro de rota
        return view('homologacao.evolucao');
    }
}
