<?php

use App\Http\Controllers\Access\UserManagementController;
use App\Http\Controllers\Access\RoleManagementController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\AuditTrailExportController;
use App\Http\Controllers\Backup\BackupController;
use App\Http\Controllers\HomologacaoController;
use App\Http\Controllers\Calendarios\CalendariosController;
use App\Http\Controllers\Operacional\MandadosController;
use App\Http\Controllers\Operacional\MandadosRelatorioPdfController;
use App\Http\Controllers\Operacional\ObjetosController;
use App\Http\Controllers\Operacional\OperacionalController;
use App\Http\Controllers\Rh\RhController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Analise\AnaliseController;
use App\Http\Controllers\Analise\AnaliseBatchController;
use App\Http\Controllers\Analise\AnaliseExportController;
use App\Http\Controllers\Analise\AnaliseBoImportController;
use App\Http\Controllers\Analise\AnaliseBoSearchController;
use App\Http\Controllers\Analise\AnaliseEstatisticasController;
use App\Http\Controllers\Analise\AnaliseRelatorioDadosController;
use App\Http\Controllers\Analise\AnaliseFlagrantePendenciaController;
use App\Http\Controllers\Produtividade\CartorioController;
use App\Http\Controllers\Produtividade\CartorioFlagranteController;
use App\Http\Controllers\Produtividade\FlagranteController;
use App\Http\Controllers\Produtividade\BoletimController;
use App\Http\Controllers\Produtividade\BoletimExportController;
use App\Http\Controllers\Produtividade\BoletimRelatorioController;
use App\Http\Controllers\Produtividade\ProdutividadeHubController;
use App\Http\Controllers\Produtividade\StatsEntryController;
use App\Http\Controllers\Produtividade\StatsController;
use App\Http\Controllers\Produtividade\StatsExportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Escalas\EscalasController;
use App\Http\Controllers\Escalas\EscalasPrintPdfController;
use App\Http\Controllers\RelatoriosController;
use App\Http\Controllers\RelatoriosAcompanhamentoOperacionalController;
use App\Http\Controllers\RelatoriosAcompanhamentoOperacionalPdfController;
use App\Http\Controllers\RelatoriosProdutividadeA4Controller;
use App\Http\Controllers\RelatoriosProdutividadeA4PdfController;
use App\Http\Controllers\Produtividade\FlagrantesRelatorioController;
use App\Http\Controllers\Rh\FichaFuncionarioController;
use App\Http\Controllers\Escalas\PlantaoRelatorioController;
use App\Http\Controllers\Operacional\MandadosStatsController;
use App\Http\Controllers\Operacional\MandadosRelatorioController;
use App\Http\Controllers\Operacional\OrdemServicoController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    // Route legacy removida: acesso-teste (PilotAccessController)
});

Route::get('/homologacao', [HomologacaoController::class, 'index'])->name('homologacao');
Route::get('/evolucao', [HomologacaoController::class, 'evolucao'])->name('evolucao');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/password/change', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password/change', [PasswordController::class, 'update'])->name('password.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('password.change.required')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/escala', fn () => redirect()->route('escalas.index'))->name('escala.alias');
        Route::get('/plantoes', fn () => redirect()->route('escalas.plantoes'))->name('plantoes.alias');

        Route::get('/produtividade', [ProdutividadeHubController::class, 'index'])
            ->middleware('permission:produtividade.cartorios.view')
            ->name('produtividade.hub');

        Route::prefix('produtividade/cartorios')->name('produtividade.cartorios.')->middleware('permission:produtividade.cartorios.view')->group(function (): void {
            Route::get('/', [CartorioController::class, 'index'])->name('index');
            Route::post('/sincronizar-legado', [CartorioController::class, 'syncLegacy'])
                ->middleware('permission:produtividade.cartorios.manage')
                ->name('sync-legacy');
            Route::post('/', [CartorioController::class, 'store'])
                ->middleware('permission:produtividade.cartorios.manage')
                ->name('store');
            Route::put('/{cartorio}', [CartorioController::class, 'update'])
                ->middleware('permission:produtividade.cartorios.manage')
                ->name('update');
            Route::patch('/{cartorio}/toggle-active', [CartorioController::class, 'toggleActive'])
                ->middleware('permission:produtividade.cartorios.manage')
                ->name('toggle-active');
            Route::post('/{cartorio}/designacoes', [CartorioController::class, 'storeDesignacao'])
                ->middleware('permission:produtividade.cartorios.manage')
                ->name('designacoes.store');
            Route::patch('/{cartorio}/designacoes/{history}', [CartorioController::class, 'updateDesignacao'])
                ->middleware('permission:produtividade.cartorios.manage')
                ->name('designacoes.update');

            // ─── Fechamento mensal por cartório ─────────────────────────────────
            Route::prefix('/{cartorio}/fechamento')->name('fechamento.')->middleware('permission:produtividade.cartorios.manage')->group(function (): void {
                Route::get('/', [StatsEntryController::class, 'create'])->name('create');
                Route::post('/', [StatsEntryController::class, 'store'])->name('store');
            });

            // ─── Flagrantes por cartório (ações com binding de {cartorio}) ──────
            Route::prefix('/{cartorio}/flagrantes')->name('flagrantes.')->middleware('permission:produtividade.flagrantes.view')->group(function (): void {
                Route::get('/', [CartorioFlagranteController::class, 'index'])->name('index');
                Route::post('/manual', [CartorioFlagranteController::class, 'storeManual'])
                    ->middleware('permission:produtividade.flagrantes.manage')
                    ->name('manual');
                Route::post('/enfileirar', [CartorioFlagranteController::class, 'enqueueSuggestion'])
                    ->middleware('permission:produtividade.flagrantes.manage')
                    ->name('enqueue');
                Route::post('/import-items/{item}/confirmar', [CartorioFlagranteController::class, 'confirmImport'])
                    ->middleware('permission:produtividade.flagrantes.confirm')
                    ->name('confirm');
                Route::post('/import-items/{item}/rejeitar', [CartorioFlagranteController::class, 'rejectImport'])
                    ->middleware('permission:produtividade.flagrantes.confirm')
                    ->name('reject');
                Route::patch('/{flagrante}/inativar', [CartorioFlagranteController::class, 'deactivate'])
                    ->middleware('permission:produtividade.flagrantes.manage')
                    ->name('deactivate');
            });
        });

        Route::prefix('produtividade/flagrantes')->name('produtividade.flagrantes.')->middleware('permission:produtividade.flagrantes.view')->group(function (): void {
            Route::get('/', [FlagranteController::class, 'index'])->name('index');
            Route::post('/importar', [FlagranteController::class, 'importSpreadsheet'])
                ->middleware(['permission:produtividade.flagrantes.manage', 'throttle:10,1'])
                ->name('import');
            Route::post('/sincronizar-legado', [FlagranteController::class, 'syncLegacyAnalise'])
                ->middleware(['permission:produtividade.flagrantes.manage', 'throttle:6,1'])
                ->name('sync-legacy');
            Route::post('/import-items/{item}/assign-cartorio', [FlagranteController::class, 'assignImportItemCartorio'])
                ->middleware('permission:produtividade.flagrantes.manage')
                ->name('assign-cartorio');
            Route::post('/manual', [FlagranteController::class, 'storeManual'])
                ->middleware('permission:produtividade.flagrantes.manage')
                ->name('store-manual');
            Route::post('/import-items/{item}/confirm', [FlagranteController::class, 'confirmImportItem'])
                ->middleware('permission:produtividade.flagrantes.confirm')
                ->name('confirm');
            Route::post('/import-items/{item}/reject', [FlagranteController::class, 'rejectImportItem'])
                ->middleware('permission:produtividade.flagrantes.confirm')
                ->name('reject');
            Route::get('/relatorio', [FlagrantesRelatorioController::class, 'index'])->name('relatorio');
        });

        Route::prefix('produtividade/boletins')->name('produtividade.boletins.')->middleware('permission:produtividade.boletins.view')->group(function (): void {
            Route::get('/', [BoletimController::class, 'index'])->name('index');
            Route::post('/importar', [BoletimController::class, 'import'])
                ->middleware(['permission:produtividade.boletins.manage', 'throttle:10,1'])
                ->name('import');
            Route::get('/relatorio', [BoletimRelatorioController::class, 'index'])->name('relatorio');
            Route::get('/exportar', [BoletimExportController::class, 'index'])->name('export');
            Route::get('/{boletim}/editar', [BoletimController::class, 'edit'])
                ->middleware('permission:produtividade.boletins.manage')
                ->name('edit');
            Route::patch('/{boletim}', [BoletimController::class, 'update'])
                ->middleware('permission:produtividade.boletins.manage')
                ->name('update');
        });

        Route::prefix('produtividade/estatisticas')->name('produtividade.stats.')->middleware('permission:produtividade.stats.view')->group(function (): void {
            Route::get('/', [StatsController::class, 'index'])->name('index');
            Route::get('/exportar', [StatsExportController::class, 'index'])->name('export');
        });

        Route::prefix('operacional')->name('operacional.')->middleware('permission:operacional.view')->group(function (): void {
            Route::get('/', [OperacionalController::class, 'index'])->name('index');

            Route::prefix('mandados')->name('mandados.')->middleware('permission:operacional.mandados.view')->group(function (): void {
                Route::get('/', [MandadosController::class, 'index'])->name('index');
                Route::post('/', [MandadosController::class, 'store'])
                    ->middleware('permission:operacional.mandados.manage')
                    ->name('store');
                Route::put('/{mandado}', [MandadosController::class, 'update'])
                    ->middleware('permission:operacional.mandados.manage')
                    ->name('update');
                Route::delete('/{mandado}', [MandadosController::class, 'destroy'])
                    ->middleware('permission:operacional.mandados.manage')
                    ->name('destroy');
                Route::post('/sincronizar-legado', [MandadosController::class, 'syncLegacy'])
                    ->middleware('permission:operacional.mandados.manage')
                    ->name('sync-legacy');
                Route::get('/estatisticas', [MandadosStatsController::class, 'index'])->name('stats');
                Route::get('/relatorio', [MandadosRelatorioController::class, 'index'])->name('relatorio');
                Route::get('/relatorio/pdf', [MandadosRelatorioPdfController::class, 'index'])->name('relatorio.pdf');
            });

            Route::prefix('ordens')->name('ordens.')->middleware('permission:operacional.ordens.view')->group(function (): void {
                Route::get('/', [OrdemServicoController::class, 'index'])->name('index');
                Route::post('/', [OrdemServicoController::class, 'store'])
                    ->middleware('permission:operacional.ordens.manage')
                    ->name('store');
                Route::put('/{ordem}', [OrdemServicoController::class, 'update'])
                    ->middleware('permission:operacional.ordens.manage')
                    ->name('update');
                Route::delete('/{ordem}', [OrdemServicoController::class, 'destroy'])
                    ->middleware('permission:operacional.ordens.manage')
                    ->name('destroy');
            });

            Route::prefix('objetos')->name('objetos.')->middleware('permission:operacional.objetos.view')->group(function (): void {
                Route::get('/', [ObjetosController::class, 'index'])->name('index');
                Route::post('/', [ObjetosController::class, 'store'])
                    ->middleware('permission:operacional.objetos.manage')
                    ->name('store');
                Route::put('/{objeto}', [ObjetosController::class, 'update'])
                    ->middleware('permission:operacional.objetos.manage')
                    ->name('update');
                Route::delete('/{objeto}', [ObjetosController::class, 'destroy'])
                    ->middleware('permission:operacional.objetos.manage')
                    ->name('destroy');
                Route::patch('/{objeto}/situacao', [ObjetosController::class, 'toggleSituacao'])
                    ->middleware('permission:operacional.objetos.manage')
                    ->name('situacao');
                Route::post('/locais', [ObjetosController::class, 'storeLocal'])
                    ->middleware('permission:operacional.objetos.manage')
                    ->name('locais.store');
                Route::patch('/locais/{local}/toggle', [ObjetosController::class, 'toggleLocal'])
                    ->middleware('permission:operacional.objetos.manage')
                    ->name('locais.toggle');
            });
        });

        Route::prefix('escalas')->name('escalas.')->middleware('permission:escalas.view')->group(function (): void {
            Route::get('/', [EscalasController::class, 'index'])->name('index');
            Route::get('/plantoes', [EscalasController::class, 'plantoes'])->name('plantoes');
            Route::get('/plantoes/relatorio', [PlantaoRelatorioController::class, 'index'])->name('plantoes.relatorio');
            Route::get('/prova', [EscalasController::class, 'proofView'])->name('prova');
            Route::get('/imprimir', [EscalasController::class, 'printView'])->name('print');
            Route::get('/imprimir/pdf', [EscalasPrintPdfController::class, 'index'])->name('print.pdf');

            // CRUD — requer manage
            Route::post('/sincronizar-legado', [EscalasController::class, 'syncLegado'])
                ->middleware('permission:escalas.manage')
                ->name('sync-legado');
            Route::post('/gerar', [EscalasController::class, 'gerarProvisoria'])
                ->middleware('permission:escalas.manage')
                ->name('gerar');
            Route::post('/dias', [EscalasController::class, 'storeDia'])
                ->middleware('permission:escalas.manage')
                ->name('dias.store');
            Route::delete('/dias/{dia}', [EscalasController::class, 'destroyDia'])
                ->middleware('permission:escalas.manage')
                ->name('dias.destroy');
            Route::patch('/dias/{dia}/delegado', [EscalasController::class, 'updateDiaDelegado'])
                ->middleware('permission:escalas.manage')
                ->name('dias.delegado');
            Route::patch('/dias/{dia}', [EscalasController::class, 'updateDia'])
                ->middleware('permission:escalas.manage')
                ->name('dias.update');
            Route::post('/fechar', [EscalasController::class, 'fecharVersao'])
                ->middleware('permission:escalas.manage')
                ->name('fechar');
            Route::post('/nova-versao', [EscalasController::class, 'novaVersao'])
                ->middleware('permission:escalas.manage')
                ->name('nova-versao');
            Route::post('/plantoes-funcionarios', [EscalasController::class, 'storePlantaoFuncionario'])
                ->middleware('permission:escalas.manage')
                ->name('plantoes-funcionarios.store');
            Route::delete('/plantoes-funcionarios/{plantao}', [EscalasController::class, 'destroyPlantaoFuncionario'])
                ->middleware('permission:escalas.manage')
                ->name('plantoes-funcionarios.destroy');
            Route::post('/plantoes-externos', [EscalasController::class, 'storePlantaoExterno'])
                ->middleware('permission:escalas.manage')
                ->name('plantoes-externos.store');
            Route::patch('/plantoes-externos/{plantao}/toggle', [EscalasController::class, 'togglePlantaoExterno'])
                ->middleware('permission:escalas.manage')
                ->name('plantoes-externos.toggle');
            Route::put('/plantoes-externos/{plantao}', [EscalasController::class, 'updatePlantaoExterno'])
                ->middleware('permission:escalas.manage')
                ->name('plantoes-externos.update');
            Route::post('/substituicao-ddm', [EscalasController::class, 'storeSubstituicaoDdm'])
                ->middleware('permission:escalas.manage')
                ->name('substituicao-ddm.store');
        });

        Route::prefix('calendarios')->name('calendarios.')->middleware('permission:calendarios.view')->group(function (): void {
            Route::get('/', [CalendariosController::class, 'index'])->name('index');
            Route::post('/feriados', [CalendariosController::class, 'store'])
                ->middleware('permission:calendarios.manage')
                ->name('feriados.store');
            Route::patch('/feriados/{holiday}/toggle-active', [CalendariosController::class, 'toggleHolidayActive'])
                ->middleware('permission:calendarios.manage')
                ->name('feriados.toggle-active');
            Route::post('/importar-legado', [CalendariosController::class, 'syncLegacy'])
                ->middleware('permission:calendarios.manage')
                ->name('legacy.sync');
        });

        Route::prefix('backup')->name('backup.')->middleware('permission:backup.view')->group(function (): void {
            Route::get('/', [BackupController::class, 'index'])->name('index');
        });

        Route::prefix('funcionarios')->name('funcionarios.')->middleware('permission:rh.view')->group(function (): void {
            Route::get('/', [RhController::class, 'index'])->name('index');
        });

        Route::prefix('access/users')->name('access.users.')->middleware('permission:access.users.view')->group(function (): void {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::post('/', [UserManagementController::class, 'store'])
                ->middleware('permission:access.users.create')
                ->name('store');
            Route::put('/{user}', [UserManagementController::class, 'update'])
                ->middleware('permission:access.users.update')
                ->name('update');
            Route::patch('/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])
                ->middleware('permission:access.users.toggle')
                ->name('toggle-active');
            Route::post('/{user}/reset-password', [UserManagementController::class, 'resetPassword'])
                ->middleware('permission:access.users.update')
                ->name('reset-password');
            Route::post('/{user}/scopes', [UserManagementController::class, 'storeScope'])
                ->middleware('permission:access.users.update')
                ->name('scopes.store');
            Route::delete('/{user}/scopes/{scope}', [UserManagementController::class, 'destroyScope'])
                ->middleware('permission:access.users.update')
                ->name('scopes.destroy');
            Route::post('/from-funcionario/{funcionario}', [UserManagementController::class, 'storeFromFuncionario'])
                ->middleware('permission:access.users.create')
                ->name('from-funcionario');
        });

        Route::prefix('access/roles')->name('access.roles.')->middleware('permission:access.roles.view')->group(function (): void {
            Route::get('/', [RoleManagementController::class, 'index'])->name('index');
            Route::post('/', [RoleManagementController::class, 'store'])
                ->middleware('permission:access.roles.manage')
                ->name('store');
            Route::put('/{role}', [RoleManagementController::class, 'update'])
                ->middleware('permission:access.roles.manage')
                ->name('update');
        });

        Route::prefix('relatorios')->name('relatorios.')->middleware('permission:relatorios.emit')->group(function (): void {
            Route::get('/', RelatoriosController::class)->name('index');
            Route::get('/produtividade/a4', RelatoriosProdutividadeA4Controller::class)->name('produtividade.a4');
            Route::get('/produtividade/a4/pdf', RelatoriosProdutividadeA4PdfController::class)->name('produtividade.a4.pdf');
            Route::get('/acompanhamento-operacional', RelatoriosAcompanhamentoOperacionalController::class)->name('operacional.integrado');
            Route::get('/acompanhamento-operacional/pdf', RelatoriosAcompanhamentoOperacionalPdfController::class)->name('operacional.integrado.pdf');
        });

        Route::get('/auditoria', AuditTrailController::class)
            ->middleware('permission:auditoria.view')
            ->name('auditoria.index');
        Route::get('/auditoria/exportar', AuditTrailExportController::class)
            ->middleware('permission:auditoria.view')
            ->name('auditoria.export');

        Route::prefix('analise')->name('analise.')->middleware('permission:analise.view')->group(function (): void {
            Route::get('/', AnaliseController::class)->name('index');
            Route::get('/lotes/{batch}', [AnaliseBatchController::class, 'show'])->name('batches.show');
            Route::get('/exportar/pendencias', [AnaliseExportController::class, 'pending'])->name('exports.pending');
            Route::get('/lotes/{batch}/exportar', [AnaliseExportController::class, 'batch'])->name('exports.batch');

            // ─── BOs: upload e pesquisa nominal ────────────────────────────
            Route::get('/bos/importar', [AnaliseBoImportController::class, 'create'])
                ->name('bos.import');
            Route::post('/bos/importar', [AnaliseBoImportController::class, 'store'])
                ->middleware('permission:analise.manage')
                ->name('bos.import.store');
            Route::get('/bos/importar/resultado', [AnaliseBoImportController::class, 'resultado'])
                ->name('bos.import.resultado');
            Route::get('/bos/pesquisar', AnaliseBoSearchController::class)
                ->name('bos.search');
            Route::get('/estatisticas', AnaliseEstatisticasController::class)
                ->name('estatisticas');

            // ─── Relatórios de Análise de Dados ─────────────────────────
            Route::get('/relatorios', [AnaliseRelatorioDadosController::class, 'index'])
                ->name('relatorios.index');
            Route::get('/relatorios/{tipo}', [AnaliseRelatorioDadosController::class, 'show'])
                ->name('relatorios.dados');
            Route::get('/relatorios/{tipo}/pdf', [AnaliseRelatorioDadosController::class, 'downloadPdf'])
                ->name('relatorios.pdf');

            // ─── Auditoria de flagrantes sem cartório ───────────────────
            Route::get('/bos/auditoria-flagrantes', [AnaliseFlagrantePendenciaController::class, 'index'])
                ->name('bos.auditoria-flagrantes');
            Route::patch('/bos/auditoria-flagrantes/{pendencia}', [AnaliseFlagrantePendenciaController::class, 'update'])
                ->middleware('permission:analise.manage')
                ->name('bos.auditoria-flagrantes.update');
            Route::patch('/bos/auditoria-flagrantes/bulk', [AnaliseFlagrantePendenciaController::class, 'bulkUpdate'])
                ->middleware('permission:analise.manage')
                ->name('bos.auditoria-flagrantes.bulk');
        });

        Route::prefix('rh')->name('rh.')->middleware('permission:rh.view')->group(function (): void {
            Route::get('/', [RhController::class, 'index'])->name('index');
            Route::post('/sincronizar-legado', [RhController::class, 'syncLegacy'])
                ->middleware('permission:rh.manage')
                ->name('legacy.sync');

            // Confronto de afastamentos
            Route::get('/confronto', [RhController::class, 'confrontoAfastamentos'])->name('confronto');
            Route::get('/confronto/imprimir', [RhController::class, 'confrontoAfastamentosImprimir'])->name('confronto.print');

            // Composição dos cartórios
            Route::get('/composicao', [RhController::class, 'composicaoCartorios'])->name('composicao');
            Route::get('/composicao/imprimir', [RhController::class, 'composicaoCartoriosImprimir'])->name('composicao.print');

            // Estatísticas RH
            Route::get('/stats', [RhController::class, 'stats'])
                ->middleware('permission:rh.view')
                ->name('stats');
            Route::get('/stats/imprimir', [RhController::class, 'statsPrint'])
                ->middleware('permission:rh.view')
                ->name('stats.print');

            // CRUD de Cargos
            Route::post('/cargos', [RhController::class, 'storeCargo'])
                ->middleware('permission:rh.manage')
                ->name('cargos.store');
            Route::patch('/cargos/{cargo}/toggle-active', [RhController::class, 'toggleCargoActive'])
                ->middleware('permission:rh.manage')
                ->name('cargos.toggle-active');

            // CRUD de Funcionários
            Route::post('/funcionarios', [RhController::class, 'storeFuncionario'])
                ->middleware('permission:rh.manage')
                ->name('funcionarios.store');
            Route::put('/funcionarios/{funcionario}', [RhController::class, 'updateFuncionario'])
                ->middleware('permission:rh.manage')
                ->name('funcionarios.update');
            Route::patch('/funcionarios/{funcionario}/toggle-active', [RhController::class, 'toggleFuncionarioActive'])
                ->middleware('permission:rh.manage')
                ->name('funcionarios.toggle-active');
            Route::delete('/funcionarios/{funcionario}', [RhController::class, 'destroyFuncionario'])
                ->middleware('permission:rh.manage')
                ->name('funcionarios.destroy');
            Route::get('/funcionarios/{funcionario}/ficha', FichaFuncionarioController::class)
                ->name('funcionarios.ficha');
            Route::get('/funcionarios/{funcionario}', [RhController::class, 'showFuncionario'])
                ->name('funcionarios.show');

            // Relatório de afastamentos (imprimir)
            Route::get('/afastamentos/relatorio', [RhController::class, 'relatorioAfastamentos'])
                ->name('afastamentos.relatorio');

            // CRUD de Afastamentos
            Route::post('/afastamentos', [RhController::class, 'storeAfastamento'])
                ->middleware('permission:rh.manage')
                ->name('afastamentos.store');
            Route::put('/afastamentos/{afastamento}', [RhController::class, 'updateAfastamento'])
                ->middleware('permission:rh.manage')
                ->name('afastamentos.update');
            Route::patch('/afastamentos/{afastamento}/toggle-active', [RhController::class, 'toggleAfastamentoActive'])
                ->middleware('permission:rh.manage')
                ->name('afastamentos.toggle-active');

            // CRUD de Feriados
            Route::post('/feriados', [RhController::class, 'storeHoliday'])
                ->middleware('permission:rh.manage')
                ->name('holidays.store');
            Route::patch('/feriados/{holiday}/toggle-active', [RhController::class, 'toggleHolidayActive'])
                ->middleware('permission:rh.manage')
                ->name('holidays.toggle-active');

            // CRUD de Delegados Externos
            Route::post('/delegados-externos', [RhController::class, 'storeDelegadoExterno'])
                ->middleware('permission:rh.manage')
                ->name('delegados-externos.store');
            Route::patch('/delegados-externos/{delegadoExterno}/toggle-active', [RhController::class, 'toggleDelegadoExternoActive'])
                ->middleware('permission:rh.manage')
                ->name('delegados-externos.toggle-active');
            Route::get('/delegados-externos/{delegadoExterno}', [RhController::class, 'showDelegadoExterno'])
                ->name('delegados-externos.show');
            Route::put('/delegados-externos/{delegadoExterno}', [RhController::class, 'updateDelegadoExterno'])
                ->middleware('permission:rh.manage')
                ->name('delegados-externos.update');
            Route::post('/delegados-externos/{delegadoExterno}/periodos', [RhController::class, 'storeDelegadoExternoPeriodo'])
                ->middleware('permission:rh.manage')
                ->name('delegados-externos.periodos.store');
            Route::patch('/delegados-externos/{delegadoExterno}/periodos/{periodo}/toggle-active', [RhController::class, 'toggleDelegadoExternoPeriodoActive'])
                ->middleware('permission:rh.manage')
                ->name('delegados-externos.periodos.toggle-active');
        });
    });
});
