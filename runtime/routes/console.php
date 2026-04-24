<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Services\Analise\LegacyBosSyncService;
use App\Services\Escalas\LegacyEscalasSyncService;
use App\Services\Operacional\LegacyMandadosSyncService;
use App\Services\Produtividade\LegacyAnaliseSyncService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('grom:sync-legacy-analise-flagrantes {--actor=admin}', function () {
    $actorKey = (string) $this->option('actor');

    $user = User::query()
        ->where('username', $actorKey)
        ->orWhere('email', $actorKey)
        ->when(is_numeric($actorKey), fn ($query) => $query->orWhereKey((int) $actorKey))
        ->first();

    if (! $user) {
        $this->error('Usuario ator nao encontrado para a sincronizacao.');

        return 1;
    }

    $result = app(LegacyAnaliseSyncService::class)->syncFlagrantes($user);
    $summary = $result['summary'];

    $this->info(sprintf(
        'Sincronizacao concluida. %d staged, %d atualizados, %d ignorados, %d erros.',
        (int) ($summary['rows_staged'] ?? 0),
        (int) ($summary['rows_updated'] ?? 0),
        (int) ($summary['rows_skipped'] ?? 0),
        (int) ($summary['error_count'] ?? 0),
    ));

    return 0;
})->purpose('Sincroniza flagrantes da base legada da Analise de Dados para a fila web');

// ─────────────────────────────────────────────────────────────────────────────
// Migração única: BOs de Análise (legado → PHP)
// ─────────────────────────────────────────────────────────────────────────────
Artisan::command('grom:import-legado-bos', function () {
    $this->info('Importando BOs do banco legado...');

    $result = app(LegacyBosSyncService::class)->syncAll();

    foreach ($result['messages'] as $msg) {
        $this->line('  ' . $msg);
    }

    if ($result['errors'] > 0) {
        $this->warn("Concluído com {$result['errors']} erro(s).");
        return 1;
    }

    $this->info(sprintf(
        'Concluído: %d inseridos, %d atualizados, %d ignorados.',
        $result['inserted'],
        $result['updated'],
        $result['skipped'],
    ));

    return 0;
})->purpose('Copia todos os BOs do banco SQLite legado para as tabelas PHP (analise_bos + filhas)');

// ─────────────────────────────────────────────────────────────────────────────
// Migração única: Mandados de Prisão (legado → PHP)
// ─────────────────────────────────────────────────────────────────────────────
Artisan::command('grom:import-legado-mandados {--actor=admin}', function () {
    $actorKey = (string) $this->option('actor');

    $user = User::query()
        ->where('username', $actorKey)
        ->orWhere('email', $actorKey)
        ->when(is_numeric($actorKey), fn ($q) => $q->orWhereKey((int) $actorKey))
        ->first();

    if (! $user) {
        $this->error("Usuário '{$actorKey}' não encontrado.");
        return 1;
    }

    $this->info('Importando mandados do banco legado...');

    $result = app(LegacyMandadosSyncService::class)->sync((string) $user->id);

    foreach ($result['messages'] as $msg) {
        $this->line('  ' . $msg);
    }

    if ($result['errors'] > 0) {
        $this->warn("Concluído com {$result['errors']} erro(s).");
        return 1;
    }

    $this->info(sprintf(
        'Concluído: %d inseridos, %d atualizados, %d ignorados.',
        $result['inserted'],
        $result['updated'],
        $result['skipped'],
    ));

    return 0;
})->purpose('Copia todos os mandados do banco SQLite legado para a tabela PHP operacional_mandados');

// ─────────────────────────────────────────────────────────────────────────────
// Migração única: Escalas (legado → PHP)
// ─────────────────────────────────────────────────────────────────────────────
Artisan::command('grom:import-legado-escalas {--actor=admin}', function () {
    $actorKey = (string) $this->option('actor');

    $user = User::query()
        ->where('username', $actorKey)
        ->orWhere('email', $actorKey)
        ->when(is_numeric($actorKey), fn ($q) => $q->orWhereKey((int) $actorKey))
        ->first();

    if (! $user) {
        $this->error("Usuário '{$actorKey}' não encontrado.");
        return 1;
    }

    $this->info('Importando escalas do banco legado...');

    $result = app(LegacyEscalasSyncService::class)->syncAll((string) $user->id);

    $dias    = $result['dias'];
    $ext     = $result['plantoes_externos'];
    $func    = $result['plantoes_funcionarios'];
    $erros   = $result['errors'];

    foreach ($erros as $msg) {
        $this->warn('  ' . $msg);
    }

    $this->info(sprintf(
        'Dias: %d inseridos, %d atualizados | Externos: %d ins, %d atu | Funcionários: %d ins, %d atu | Erros: %d',
        $dias['inserted'],  $dias['updated'],
        $ext['inserted'],   $ext['updated'],
        $func['inserted'],  $func['updated'],
        count($erros),
    ));

    return count($erros) > 0 ? 1 : 0;
})->purpose('Copia escalas e plantões do banco SQLite legado para as tabelas PHP');

