<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\RhAfastamento;
use App\Models\RhCargo;
use App\Models\RhDelegadoExterno;
use App\Models\RhDelegadoExternoPeriodo;
use App\Models\RhFuncionario;
use App\Models\RhHoliday;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessCredentialPolicy;
use App\Support\AuditLogger;
use App\Support\SqliteDatabaseBackup;
use App\Services\Rh\LegacyFuncionariosReader;
use App\Services\Rh\LegacyFuncionariosSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RhController extends Controller
{
    public function index(Request $request, LegacyFuncionariosReader $legacyFuncionariosReader): View
    {
        $filters = $request->validate([
            'cargo_id' => ['nullable', 'exists:rh_cargos,id'],
            'status' => ['nullable', 'in:all,active,inactive'],
        ]);

        $today = Carbon::today();
        $cargoId = $filters['cargo_id'] ?? null;
        $status = $filters['status'] ?? 'all';

        $cargosQuery = RhCargo::query()
            ->withCount('funcionarios')
            ->orderByDesc('is_active')
            ->orderBy('name');

        $funcionariosQuery = RhFuncionario::query()
            ->with(['cargo', 'user', 'afastamentos' => fn ($query) => $query->orderByDesc('start_date')])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->when($cargoId, fn ($query, string $cargoId) => $query->where('cargo_id', $cargoId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false));

        $afastamentosQuery = RhAfastamento::query()
            ->with('funcionario')
            ->orderByDesc('start_date')
            ->orderByDesc('created_at');

        $delegadosQuery = RhDelegadoExterno::query()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->orderBy('name');

        $holidayQuery = RhHoliday::query()
            ->orderByDesc('is_active')
            ->orderBy('holiday_date');
        try {
            $legacySnapshot = $legacyFuncionariosReader->snapshot();
        } catch (\Throwable) {
            $legacySnapshot = ['employees' => [], 'warning' => 'Base legada indisponivel.'];
        }
        $legacyEmployees = $legacySnapshot['employees'] ?? [];
        $legacyPreview = $legacyEmployees;
        $afastamentosResumoBase = (clone $afastamentosQuery)->get();
        $afastamentosSummary = [
            'ferias_dias' => 0,
            'ferias_registros' => 0,
            'outros_dias' => 0,
            'outros_registros' => 0,
            'registros_em_aberto' => 0,
        ];

        foreach ($afastamentosResumoBase as $afastamentoResumo) {
            $reason = mb_strtolower((string) $afastamentoResumo->reason);
            $isFerias = str_contains($reason, 'ferias') || str_contains($reason, 'férias');
            $registroKey = $isFerias ? 'ferias_registros' : 'outros_registros';
            $diasKey = $isFerias ? 'ferias_dias' : 'outros_dias';

            $afastamentosSummary[$registroKey]++;

            if (! $afastamentoResumo->start_date || ! $afastamentoResumo->end_date) {
                $afastamentosSummary['registros_em_aberto']++;
                continue;
            }

            $afastamentosSummary[$diasKey] += $afastamentoResumo->start_date
                ->startOfDay()
                ->diffInDays($afastamentoResumo->end_date->startOfDay()) + 1;
        }

        return view('rh.index', [
            'filters' => $filters,
            'roles' => Role::query()->orderBy('name')->get(),
            'cargos' => (clone $cargosQuery)->get(),
            'funcionarios' => (clone $funcionariosQuery)->get(),
            'afastamentos' => (clone $afastamentosQuery)->get(),
            'delegadosExternos' => (clone $delegadosQuery)->get(),
            'holidays' => (clone $holidayQuery)->get(),
            'upcomingHolidays' => RhHoliday::query()
                ->where('is_active', true)
                ->whereDate('holiday_date', '>=', $today)
                ->orderBy('holiday_date')
                ->limit(8)
                ->get(),
            'legacySnapshot' => $legacySnapshot,
            'legacyPreview' => $legacyPreview,
            'afastamentosSummary' => $afastamentosSummary,
            'recentHistory' => AuditEvent::query()
                ->with('actor')
                ->where('module_code', 'rh')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(),
            'summary' => [
                'cargos_total' => (clone $cargosQuery)->count(),
                'cargos_ativos' => (clone $cargosQuery)->where('is_active', true)->count(),
                'funcionarios_total' => (clone $funcionariosQuery)->count(),
                'funcionarios_ativos' => (clone $funcionariosQuery)->where('is_active', true)->count(),
                'funcionarios_exibidos' => (clone $funcionariosQuery)->count(),
                'afastamentos_ativos' => (clone $afastamentosQuery)->where('is_active', true)->count(),
                'afastamentos_em_vigor' => RhAfastamento::query()
                    ->where('is_active', true)
                    ->whereDate('start_date', '<=', $today)
                    ->where(function ($query) use ($today): void {
                        $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
                    })
                    ->count(),
                'afastamentos_agendados' => RhAfastamento::query()
                    ->where('is_active', true)
                    ->whereDate('start_date', '>', $today)
                    ->count(),
                'delegados_externos_total' => (clone $delegadosQuery)->count(),
                'delegados_externos_ativos' => (clone $delegadosQuery)->where('is_active', true)->count(),
                'delegados_externos_em_vigor' => RhDelegadoExterno::query()
                    ->where('is_active', true)
                    ->whereDate('start_date', '<=', $today)
                    ->where(function ($query) use ($today): void {
                        $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
                    })
                    ->count(),
                'feriados_ativos' => RhHoliday::query()->where('is_active', true)->count(),
                'feriados_proximos' => RhHoliday::query()
                    ->where('is_active', true)
                    ->whereDate('holiday_date', '>=', $today)
                    ->count(),
                'legacy_funcionarios_total' => $legacySnapshot['summary']['total'] ?? 0,
                'legacy_funcionarios_ativos' => $legacySnapshot['summary']['ativos'] ?? 0,
                'legacy_funcionarios_concorrem' => $legacySnapshot['summary']['concorrem_escala'] ?? 0,
                'legacy_funcionarios_em_afastamento' => $legacySnapshot['summary']['em_afastamento'] ?? 0,
                'legacy_cargos_total' => $legacySnapshot['summary']['cargos_total'] ?? 0,
                'legacy_afastamentos_total' => $legacySnapshot['summary']['afastamentos_total'] ?? 0,
                'legacy_afastamentos_em_vigor' => $legacySnapshot['summary']['afastamentos_em_vigor'] ?? 0,
            ],
        ]);
    }

    public function storeCargo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:rh_cargos,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $cargo = RhCargo::query()->create([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'description' => $this->cleanNullable($data['description'] ?? null),
            'is_active' => (bool) $data['is_active'],
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'cargos.create',
            entityType: 'rh_cargo',
            entityId: $cargo->id,
            description: 'Cargo criado no modulo de RH.',
            metadata: ['code' => $cargo->code]
        );

        return redirect()->route('rh.index')->with('status', 'Cargo criado com sucesso.');
    }

    public function toggleCargoActive(RhCargo $cargo): RedirectResponse
    {
        $cargo->update([
            'is_active' => ! $cargo->is_active,
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'cargos.toggle_active',
            entityType: 'rh_cargo',
            entityId: $cargo->id,
            description: $cargo->is_active ? 'Cargo reativado.' : 'Cargo inativado.',
            metadata: ['code' => $cargo->code]
        );

        return redirect()->route('rh.index')->with('status', 'Status do cargo atualizado.');
    }

    public function storeFuncionario(Request $request): RedirectResponse
    {

        $data = $request->validate([
            'matricula' => ['required', 'string', 'max:50', 'unique:rh_funcionarios,matricula'],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:rh_funcionarios,email'],
            'cargo_id' => ['required', 'exists:rh_cargos,id'],
            'sector' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'rg' => ['nullable', 'string', 'max:50'],
            'cpf' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
            'admission_date' => ['required', 'date'],
            'designation_date' => ['nullable', 'date'],
            'departure_date' => ['nullable', 'date', 'after_or_equal:admission_date'],
            'removal_date' => ['nullable', 'date'],
            'concorre_escala' => ['required', 'boolean'],
            'is_delegado_externo' => ['nullable', 'boolean'],
            'senha_spj' => ['nullable', 'string', 'max:255'],
            'senha_ipe' => ['nullable', 'string', 'max:255'],
            'observacoes_operacionais' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'create_access' => ['nullable', 'boolean'],
            'access_roles' => ['array'],
            'access_roles.*' => ['integer', 'exists:roles,id'],
        ]);

        // Se for delegado externo, nunca concorre à escala
        $isDelegadoExterno = (bool) ($data['is_delegado_externo'] ?? false);
        $concorreEscala = $isDelegadoExterno ? false : (bool) $data['concorre_escala'];

        SqliteDatabaseBackup::backup('rh-funcionario-create');

        $funcionario = RhFuncionario::query()->create([
            'legacy_id' => null,
            'matricula' => strtoupper(trim($data['matricula'])),
            'name' => trim($data['name']),
            'short_name' => $this->cleanNullable($data['short_name'] ?? null),
            'email' => $this->cleanNullable($data['email'] ?? null),
            'cargo_id' => $data['cargo_id'],
            'sector' => $this->cleanNullable($data['sector'] ?? null),
            'phone' => $this->cleanNullable($data['phone'] ?? null),
            'rg' => $this->cleanNullable($data['rg'] ?? null),
            'cpf' => $this->cleanNullable($data['cpf'] ?? null),
            'birth_date' => $data['birth_date'] ?? null,
            'admission_date' => $data['admission_date'],
            'designation_date' => $data['designation_date'] ?? null,
            'departure_date' => $data['departure_date'] ?? null,
            'removal_date' => $data['removal_date'] ?? null,
            'concorre_escala' => $concorreEscala,
            'is_delegado_externo' => $isDelegadoExterno,
            'senha_spj' => $this->cleanNullable($data['senha_spj'] ?? null),
            'senha_ipe' => $this->cleanNullable($data['senha_ipe'] ?? null),
            'observacoes_operacionais' => $this->cleanNullable($data['observacoes_operacionais'] ?? null),
            'notes' => $this->cleanNullable($data['notes'] ?? null),
            'is_active' => (bool) $data['is_active'],
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'funcionarios.create',
            entityType: 'rh_funcionario',
            entityId: $funcionario->id,
            description: 'Funcionario criado no modulo de RH.',
            metadata: [
                'matricula' => $funcionario->matricula,
                'cargo_id' => $funcionario->cargo_id,
            ]
        );

        if ((bool) ($data['create_access'] ?? false)) {
            $this->createOrUpdateAccessForFuncionario($funcionario, $data['access_roles'] ?? []);
        }

        return redirect()->route('rh.index')->with('status', 'Funcionario criado com sucesso.');
    }

    public function updateFuncionario(Request $request, RhFuncionario $funcionario): RedirectResponse
    {

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'short_name'       => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255', 'unique:rh_funcionarios,email,'.$funcionario->id],
            'cargo_id'         => ['required', 'exists:rh_cargos,id'],
            'sector'           => ['nullable', 'string', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'rg'               => ['nullable', 'string', 'max:50'],
            'cpf'              => ['nullable', 'string', 'max:50'],
            'birth_date'       => ['nullable', 'date'],
            'admission_date'   => ['required', 'date'],
            'designation_date' => ['nullable', 'date'],
            'departure_date'   => ['nullable', 'date', 'after_or_equal:admission_date'],
            'removal_date'     => ['nullable', 'date'],
            'concorre_escala'  => ['required', 'boolean'],
            'is_delegado_externo' => ['nullable', 'boolean'],
            'senha_spj' => ['nullable', 'string', 'max:255'],
            'senha_ipe' => ['nullable', 'string', 'max:255'],
            'observacoes_operacionais' => ['nullable', 'string', 'max:2000'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            'is_active'        => ['required', 'boolean'],
            'create_access' => ['nullable', 'boolean'],
            'access_roles' => ['array'],
            'access_roles.*' => ['integer', 'exists:roles,id'],
        ]);

        // Se for delegado externo, nunca concorre à escala
        $isDelegadoExterno = (bool) ($data['is_delegado_externo'] ?? false);
        $concorreEscala = $isDelegadoExterno ? false : (bool) $data['concorre_escala'];

        SqliteDatabaseBackup::backup('rh-funcionario-update');

        $funcionario->update([
            'name'             => trim($data['name']),
            'short_name'       => $this->cleanNullable($data['short_name'] ?? null),
            'email'            => $this->cleanNullable($data['email'] ?? null),
            'cargo_id'         => $data['cargo_id'],
            'sector'           => $this->cleanNullable($data['sector'] ?? null),
            'phone'            => $this->cleanNullable($data['phone'] ?? null),
            'rg'               => $this->cleanNullable($data['rg'] ?? null),
            'cpf'              => $this->cleanNullable($data['cpf'] ?? null),
            'birth_date'       => $data['birth_date'] ?? null,
            'admission_date'   => $data['admission_date'],
            'designation_date' => $data['designation_date'] ?? null,
            'departure_date'   => $data['departure_date'] ?? null,
            'removal_date'     => $data['removal_date'] ?? null,
            'concorre_escala'  => $concorreEscala,
            'is_delegado_externo' => $isDelegadoExterno,
            'senha_spj' => $this->cleanNullable($data['senha_spj'] ?? null),
            'senha_ipe' => $this->cleanNullable($data['senha_ipe'] ?? null),
            'observacoes_operacionais' => $this->cleanNullable($data['observacoes_operacionais'] ?? null),
            'notes'            => $this->cleanNullable($data['notes'] ?? null),
            'is_active'        => (bool) $data['is_active'],
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'funcionarios.update',
            entityType: 'rh_funcionario',
            entityId: $funcionario->id,
            description: 'Dados do funcionario atualizados.',
            metadata: ['matricula' => $funcionario->matricula, 'cargo_id' => $funcionario->cargo_id]
        );

        if ((bool) ($data['create_access'] ?? false)) {
            $this->createOrUpdateAccessForFuncionario($funcionario, $data['access_roles'] ?? []);
        } elseif ($funcionario->user) {
            $funcionario->user->update(['is_active' => false]);
        }

        return redirect()->route('rh.index')->with('status', 'Funcionario atualizado com sucesso.');
    }

    public function toggleFuncionarioActive(RhFuncionario $funcionario): RedirectResponse
    {
        SqliteDatabaseBackup::backup('rh-funcionario-toggle');

        $funcionario->update([
            'is_active' => ! $funcionario->is_active,
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'funcionarios.toggle_active',
            entityType: 'rh_funcionario',
            entityId: $funcionario->id,
            description: $funcionario->is_active ? 'Funcionario reativado.' : 'Funcionario inativado.',
            metadata: [
                'matricula' => $funcionario->matricula,
                'cargo_id' => $funcionario->cargo_id,
            ]
        );

        return redirect()->route('rh.index')->with('status', 'Status do funcionario atualizado.');
    }

    public function destroyFuncionario(RhFuncionario $funcionario): RedirectResponse
    {
        $name = $funcionario->name;
        $matricula = $funcionario->matricula;
        $id = $funcionario->id;

        SqliteDatabaseBackup::backup('rh-funcionario-archive');

        $funcionario->update([
            'is_active' => false,
            'concorre_escala' => false,
            'departure_date' => $funcionario->departure_date ?? Carbon::today(),
        ]);

        if ($funcionario->user) {
            $funcionario->user->update(['is_active' => false]);
        }

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'funcionarios.archive',
            entityType: 'rh_funcionario',
            entityId: $id,
            description: "Funcionario '{$name}' (matricula: {$matricula}) arquivado sem exclusao permanente.",
            metadata: ['matricula' => $matricula, 'name' => $name]
        );

        return redirect()->route('rh.index')->with('status', "Funcionario '{$name}' arquivado. O historico foi preservado.");
    }

    public function storeAfastamento(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'funcionario_id' => ['required', 'exists:rh_funcionarios,id'],
            'reason' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'redirect_to_show' => ['nullable', 'boolean'],
        ]);

        // Validação anti-sobreposição
        $afastamentoSobreposto = RhAfastamento::query()
            ->where('funcionario_id', $data['funcionario_id'])
            ->where('is_active', true)
            ->where(function ($q) use ($data) {
                $q->whereNull('end_date')
                    ->orWhere(function ($q2) use ($data) {
                        $q2->where('start_date', '<=', $data['end_date'] ?? $data['start_date'])
                            ->where(function ($q3) use ($data) {
                                $q3->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $data['start_date']);
                            });
                    });
            })
            ->exists();

        if ($afastamentoSobreposto) {
            return redirect()->back()->withErrors(['start_date' => 'Sobreposicao de afastamento detectada para este funcionario.'])->withInput();
        }

        SqliteDatabaseBackup::backup('rh-afastamento-create');

        $afastamento = RhAfastamento::query()->create([
            'funcionario_id' => $data['funcionario_id'],
            'reason' => trim($data['reason']),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => true,
            'notes' => $this->cleanNullable($data['notes'] ?? null),
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'afastamentos.create',
            entityType: 'rh_afastamento',
            entityId: $afastamento->id,
            description: 'Afastamento registrado no modulo de RH.',
            metadata: [
                'funcionario_id' => $afastamento->funcionario_id,
                'reason' => $afastamento->reason,
            ]
        );

        if ($data['redirect_to_show'] ?? false) {
            return redirect()
                ->route('rh.funcionarios.show', $afastamento->funcionario_id)
                ->with('status', 'Afastamento registrado com sucesso.');
        }

        return redirect()->route('rh.index')->with('status', 'Afastamento registrado com sucesso.');
    }

    public function updateAfastamento(Request $request, RhAfastamento $afastamento): RedirectResponse
    {
        $data = $request->validate([
            'reason'             => ['required', 'string', 'max:255'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'redirect_to_show'   => ['nullable', 'boolean'],
        ]);

        // Validação anti-sobreposição
        $afastamentoSobreposto = RhAfastamento::query()
            ->where('funcionario_id', $afastamento->funcionario_id)
            ->where('id', '!=', $afastamento->id)
            ->where('is_active', true)
            ->where(function ($q) use ($data) {
                $q->whereNull('end_date')
                    ->orWhere(function ($q2) use ($data) {
                        $q2->where('start_date', '<=', $data['end_date'] ?? $data['start_date'])
                            ->where(function ($q3) use ($data) {
                                $q3->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $data['start_date']);
                            });
                    });
            })
            ->exists();

        if ($afastamentoSobreposto) {
            return redirect()->back()->withErrors(['start_date' => 'Sobreposicao de afastamento detectada para este funcionario.'])->withInput();
        }

        SqliteDatabaseBackup::backup('rh-afastamento-update');

        $afastamento->update([
            'reason'     => trim($data['reason']),
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'] ?? null,
            'notes'      => $this->cleanNullable($data['notes'] ?? null),
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'afastamentos.update',
            entityType: 'rh_afastamento',
            entityId: $afastamento->id,
            description: 'Dados do afastamento atualizados.',
            metadata: [
                'funcionario_id' => $afastamento->funcionario_id,
                'reason'         => $afastamento->reason,
                'start_date'     => $afastamento->start_date?->toDateString(),
                'end_date'       => $afastamento->end_date?->toDateString(),
            ]
        );

        if ($data['redirect_to_show'] ?? false) {
            return redirect()
                ->route('rh.funcionarios.show', $afastamento->funcionario_id)
                ->with('status', 'Afastamento atualizado com sucesso.');
        }

        return redirect()->route('rh.index')->with('status', 'Afastamento atualizado com sucesso.');
    }

    public function toggleAfastamentoActive(Request $request, RhAfastamento $afastamento): RedirectResponse
    {
        SqliteDatabaseBackup::backup('rh-afastamento-toggle');

        $afastamento->update([
            'is_active' => ! $afastamento->is_active,
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'afastamentos.toggle_active',
            entityType: 'rh_afastamento',
            entityId: $afastamento->id,
            description: $afastamento->is_active ? 'Afastamento reativado.' : 'Afastamento inativado.',
            metadata: [
                'funcionario_id' => $afastamento->funcionario_id,
                'reason' => $afastamento->reason,
            ]
        );

        if ($request->boolean('redirect_to_show')) {
            return redirect()
                ->route('rh.funcionarios.show', $afastamento->funcionario_id)
                ->with('status', 'Status do afastamento atualizado.');
        }

        return redirect()->route('rh.index')->with('status', 'Status do afastamento atualizado.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (RhHoliday::query()->whereDate('holiday_date', $data['holiday_date'])->exists()) {
            return redirect()
                ->back()
                ->withErrors(['holiday_date' => 'Ja existe feriado cadastrado nesta data.'])
                ->withInput();
        }

        SqliteDatabaseBackup::backup('rh-holiday-create');

        $holiday = RhHoliday::query()->create([
            'holiday_date' => $data['holiday_date'],
            'name' => trim($data['name']),
            'scope' => $this->cleanNullable($data['scope'] ?? null) ?: 'nacional',
            'is_active' => (bool) $data['is_active'],
            'notes' => $this->cleanNullable($data['notes'] ?? null),
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'holidays.create',
            entityType: 'rh_holiday',
            entityId: $holiday->id,
            description: 'Feriado cadastrado no modulo de RH.',
            metadata: [
                'holiday_date' => $holiday->holiday_date?->toDateString(),
                'name' => $holiday->name,
                'scope' => $holiday->scope,
            ]
        );

        return redirect()->route('rh.index')->with('status', 'Feriado cadastrado com sucesso.');
    }

    public function toggleHolidayActive(RhHoliday $holiday): RedirectResponse
    {
        SqliteDatabaseBackup::backup('rh-holiday-toggle');

        $holiday->update([
            'is_active' => ! $holiday->is_active,
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'holidays.toggle_active',
            entityType: 'rh_holiday',
            entityId: $holiday->id,
            description: $holiday->is_active ? 'Feriado reativado.' : 'Feriado inativado.',
            metadata: [
                'holiday_date' => $holiday->holiday_date?->toDateString(),
                'name' => $holiday->name,
                'scope' => $holiday->scope,
            ]
        );

        return redirect()->route('rh.index')->with('status', 'Status do feriado atualizado.');
    }

    // --- Métodos restaurados para Delegados Externos ---
    public function storeDelegadoExterno(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registration_code' => ['nullable', 'string', 'max:100', 'unique:rh_delegados_externos,registration_code'],
            'name' => ['required', 'string', 'max:255'],
            'origin_unit' => ['required', 'string', 'max:255'],
            'role_title' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', 'unique:rh_delegados_externos,email'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $delegadoExterno = RhDelegadoExterno::query()->create([
            'registration_code' => $this->cleanNullable($data['registration_code'] ?? null),
            'name' => trim($data['name']),
            'origin_unit' => trim($data['origin_unit']),
            'role_title' => trim($data['role_title']),
            'contact' => $this->cleanNullable($data['contact'] ?? null),
            'email' => $this->cleanNullable($data['email'] ?? null),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'notes' => $this->cleanNullable($data['notes'] ?? null),
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'delegados_externos.create',
            entityType: 'rh_delegado_externo',
            entityId: $delegadoExterno->id,
            description: 'Delegado externo cadastrado.',
            metadata: [
                'registration_code' => $delegadoExterno->registration_code,
                'origin_unit' => $delegadoExterno->origin_unit,
                'role_title' => $delegadoExterno->role_title,
            ]
        );

        return redirect()->route('rh.index')->with('status', 'Delegado externo cadastrado com sucesso.');
    }

    public function toggleDelegadoExternoActive(RhDelegadoExterno $delegadoExterno): RedirectResponse
    {
        $delegadoExterno->update([
            'is_active' => ! $delegadoExterno->is_active,
        ]);

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'delegados_externos.toggle_active',
            entityType: 'rh_delegado_externo',
            entityId: $delegadoExterno->id,
            description: $delegadoExterno->is_active ? 'Delegado externo reativado.' : 'Delegado externo inativado.',
            metadata: [
                'registration_code' => $delegadoExterno->registration_code,
                'origin_unit' => $delegadoExterno->origin_unit,
            ]
        );

        return redirect()->route('rh.index')->with('status', 'Status do delegado externo atualizado.');
    }

    public function showDelegadoExterno(RhDelegadoExterno $delegadoExterno): View
    {
        $delegadoExterno->load('periodos');
        $currentPeriodo = $delegadoExterno->currentPeriodo();
        $periodosSummary = [
            'total_dias' => $delegadoExterno->periodos->sum(fn($p) => $p->durationInDays() ?? 0),
            'total_registros' => $delegadoExterno->periodos->count(),
            'em_vigor' => $delegadoExterno->periodos->where(fn($p) => $p->statusLabel() === 'Em vigor')->count(),
            'agendados' => $delegadoExterno->periodos->where(fn($p) => $p->statusLabel() === 'Agendado')->count(),
            'em_aberto' => $delegadoExterno->periodos->whereNull('end_date')->count(),
        ];
        return view('rh.delegados-externos.show', [
            'delegadoExterno' => $delegadoExterno,
            'currentPeriodo' => $currentPeriodo,
            'periodosSummary' => $periodosSummary,
        ]);
    }

    public function updateDelegadoExterno(Request $request, RhDelegadoExterno $delegadoExterno): RedirectResponse
    {
        $data = $request->validate([
            'registration_code' => ['nullable', 'string', 'max:100', 'unique:rh_delegados_externos,registration_code,'.$delegadoExterno->id],
            'name' => ['required', 'string', 'max:255'],
            'origin_unit' => ['required', 'string', 'max:255'],
            'role_title' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', 'unique:rh_delegados_externos,email,'.$delegadoExterno->id],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $delegadoExterno->update([
            'registration_code' => $this->cleanNullable($data['registration_code'] ?? null),
            'name' => trim($data['name']),
            'origin_unit' => trim($data['origin_unit']),
            'role_title' => trim($data['role_title']),
            'contact' => $this->cleanNullable($data['contact'] ?? null),
            'email' => $this->cleanNullable($data['email'] ?? null),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'notes' => $this->cleanNullable($data['notes'] ?? null),
        ]);
        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'delegados_externos.update',
            entityType: 'rh_delegado_externo',
            entityId: $delegadoExterno->id,
            description: 'Delegado externo atualizado.',
            metadata: [
                'registration_code' => $delegadoExterno->registration_code,
                'origin_unit' => $delegadoExterno->origin_unit,
                'role_title' => $delegadoExterno->role_title,
            ]
        );
        return redirect()->route('rh.delegados-externos.show', $delegadoExterno)->with('status', 'Delegado externo atualizado com sucesso.');
    }

    public function storeDelegadoExternoPeriodo(Request $request, RhDelegadoExterno $delegadoExterno): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $delegadoExterno->periodos()->create([
            'motivo' => $data['motivo'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => true,
            'notes' => $this->cleanNullable($data['notes'] ?? null),
        ]);
        return redirect()->route('rh.delegados-externos.show', $delegadoExterno)->with('status', 'Período registrado com sucesso.');
    }

    public function toggleDelegadoExternoPeriodoActive(RhDelegadoExterno $delegadoExterno, $periodoId): RedirectResponse
    {
        $periodo = $delegadoExterno->periodos()->findOrFail($periodoId);
        $periodo->update(['is_active' => ! $periodo->is_active]);
        return redirect()->route('rh.delegados-externos.show', $delegadoExterno)->with('status', 'Status do período atualizado.');
    }

    public function confrontoAfastamentosImprimir(Request $request): View
    {
        // Reutiliza mesma lógica mas retorna view de impressão
        $filters = $request->validate([
            'funcionario_id' => ['nullable', 'exists:rh_funcionarios,id'],
            'ano'            => ['nullable', 'integer', 'min:2000', 'max:2099'],
            'mes'            => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $hoje = Carbon::today();
        $ano  = (int) ($filters['ano'] ?? $hoje->year);
        $mes  = (int) ($filters['mes'] ?? $hoje->month);

        $periodoInicio = Carbon::create($ano, $mes, 1)->startOfMonth();
        $periodoFim    = $periodoInicio->copy()->endOfMonth();

        $afastamentosQuery = RhAfastamento::query()
            ->with('funcionario.cargo')
            ->where('is_active', true)
            ->whereHas('funcionario', fn ($q) => $q->where('is_active', true))
            ->where(function ($q) use ($periodoInicio, $periodoFim) {
                $q->whereDate('start_date', '<=', $periodoFim)
                  ->where(function ($q2) use ($periodoInicio) {
                      $q2->whereNull('end_date')
                         ->orWhereDate('end_date', '>=', $periodoInicio);
                  });
            })
            ->when($filters['funcionario_id'] ?? null, fn ($q, $id) => $q->where('funcionario_id', $id))
            ->orderBy('start_date')
            ->get();

        $diasDoMes = $periodoInicio->daysInMonth;
        $calendarioDias = [];
        for ($d = 1; $d <= $diasDoMes; $d++) {
            $dataAtual = Carbon::create($ano, $mes, $d);
            $afastamentosNoDia = $afastamentosQuery->filter(function ($af) use ($dataAtual) {
                $inicio = Carbon::parse($af->start_date);
                $fim    = $af->end_date ? Carbon::parse($af->end_date) : null;
                return $dataAtual->gte($inicio) && ($fim === null || $dataAtual->lte($fim));
            });
            $calendarioDias[$d] = ['data' => $dataAtual, 'afastamentos' => $afastamentosNoDia->values()];
        }

        $cargosCriticos = ['delegado', 'escrivao', 'delegada'];
        $colisoesCriticas = collect($calendarioDias)->filter(function ($dia) use ($cargosCriticos) {
            return $dia['afastamentos']->filter(function ($af) use ($cargosCriticos) {
                $cargoNome = mb_strtolower($af->funcionario?->cargo?->name ?? '');
                foreach ($cargosCriticos as $c) { if (str_contains($cargoNome, $c)) { return true; } }
                return false;
            })->count() >= 2;
        })->keys();

        $meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
                  7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

        $funcionariosAtivos = RhFuncionario::where('is_active', true)
            ->with('cargo')
            ->orderBy('name')
            ->get();

        return view('rh.confronto-print', [
            'filters'            => $filters,
            'ano'                => $ano,
            'mes'                => $mes,
            'meses'              => $meses,
            'periodoInicio'      => $periodoInicio,
            'diasDoMes'          => $diasDoMes,
            'calendarioDias'     => $calendarioDias,
            'afastamentos'       => $afastamentosQuery,
            'colisoesCriticas'   => $colisoesCriticas,
            'funcionariosAtivos' => $funcionariosAtivos,
        ]);
    }

    public function composicaoCartoriosImprimir(): View
    {
        $hoje = Carbon::today();
        $funcionarios = RhFuncionario::query()
            ->with(['cargo', 'afastamentos' => function ($q) use ($hoje) {
                $q->where('is_active', true)
                  ->whereDate('start_date', '<=', $hoje)
                  ->where(function ($q2) use ($hoje) {
                      $q2->whereNull('end_date')->orWhereDate('end_date', '>=', $hoje);
                  })
                  ->orderByDesc('start_date');
            }])
            ->where('is_active', true)
            ->orderBy('sector')
            ->orderBy('name')
            ->get();

        $porSetor = $funcionarios->groupBy(fn ($f) => $f->sector ?: 'Sem setor definido');
        $estatisticas = [
            'total_ativos'     => $funcionarios->count(),
            'concorrem_escala' => $funcionarios->where('concorre_escala', true)->count(),
            'em_afastamento'   => $funcionarios->filter(fn ($f) => $f->afastamentos->isNotEmpty())->count(),
            'setores'          => $porSetor->count(),
        ];

        return view('rh.composicao-print', [
            'porSetor'     => $porSetor,
            'hoje'         => $hoje,
            'estatisticas' => $estatisticas,
        ]);
    }

    public function confrontoAfastamentos(Request $request): View
    {
        $filters = $request->validate([
            'funcionario_id' => ['nullable', 'exists:rh_funcionarios,id'],
            'ano'            => ['nullable', 'integer', 'min:2000', 'max:2099'],
            'mes'            => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $hoje = Carbon::today();
        $ano  = (int) ($filters['ano'] ?? $hoje->year);
        $mes  = (int) ($filters['mes'] ?? $hoje->month);

        $periodoInicio = Carbon::create($ano, $mes, 1)->startOfMonth();
        $periodoFim    = $periodoInicio->copy()->endOfMonth();

        $afastamentosQuery = RhAfastamento::query()
            ->with('funcionario.cargo')
            ->where('is_active', true)
            ->where(function ($q) use ($periodoInicio, $periodoFim) {
                $q->whereDate('start_date', '<=', $periodoFim)
                  ->where(function ($q2) use ($periodoInicio) {
                      $q2->whereNull('end_date')
                         ->orWhereDate('end_date', '>=', $periodoInicio);
                  });
            })
            ->when($filters['funcionario_id'] ?? null, fn ($q, $id) => $q->where('funcionario_id', $id))
            ->orderBy('start_date')
            ->get();

        // Monta estrutura calendário: [dia => [afastamento, ...]]
        $diasDoMes = $periodoInicio->daysInMonth;
        $calendarioDias = [];
        for ($d = 1; $d <= $diasDoMes; $d++) {
            $dataAtual = Carbon::create($ano, $mes, $d);
            $afastamentosNoDia = $afastamentosQuery->filter(function ($af) use ($dataAtual) {
                $inicio = $af->start_date instanceof Carbon ? $af->start_date : Carbon::parse($af->start_date);
                $fim    = $af->end_date
                    ? ($af->end_date instanceof Carbon ? $af->end_date : Carbon::parse($af->end_date))
                    : null;
                return $dataAtual->gte($inicio) && ($fim === null || $dataAtual->lte($fim));
            });
            $calendarioDias[$d] = ['data' => $dataAtual, 'afastamentos' => $afastamentosNoDia->values()];
        }

        // Detecta colisões: dias com mais de 1 afastamento de cargos críticos (Delegado/Escrivao)
        $cargosCriticos = ['delegado', 'escrivao', 'delegada'];
        $colisoesCriticas = collect($calendarioDias)->filter(function ($dia) use ($cargosCriticos) {
            return $dia['afastamentos']->filter(function ($af) use ($cargosCriticos) {
                $cargoNome = mb_strtolower($af->funcionario?->cargo?->name ?? '');
                foreach ($cargosCriticos as $critico) {
                    if (str_contains($cargoNome, $critico)) { return true; }
                }
                return false;
            })->count() >= 2;
        })->keys();

        $funcionarios = RhFuncionario::query()->where('is_active', true)->orderBy('name')->get();

        return view('rh.confronto', [
            'filters'           => $filters,
            'ano'               => $ano,
            'mes'               => $mes,
            'periodoInicio'     => $periodoInicio,
            'diasDoMes'         => $diasDoMes,
            'calendarioDias'    => $calendarioDias,
            'afastamentos'      => $afastamentosQuery,
            'colisoesCriticas'  => $colisoesCriticas,
            'funcionarios'      => $funcionarios,
        ]);
    }

    public function composicaoCartorios(): View
    {
        $hoje = Carbon::today();

        $funcionarios = RhFuncionario::query()
            ->with(['cargo', 'afastamentos' => function ($q) use ($hoje) {
                $q->where('is_active', true)
                  ->whereDate('start_date', '<=', $hoje)
                  ->where(function ($q2) use ($hoje) {
                      $q2->whereNull('end_date')->orWhereDate('end_date', '>=', $hoje);
                  })
                  ->orderByDesc('start_date');
            }])
            ->where('is_active', true)
            ->orderBy('sector')
            ->orderBy('name')
            ->get();

        // Agrupa por setor
        $porSetor = $funcionarios->groupBy(fn ($f) => $f->sector ?: 'Sem setor definido');

        $estatisticas = [
            'total_ativos'       => $funcionarios->count(),
            'concorrem_escala'   => $funcionarios->where('concorre_escala', true)->count(),
            'em_afastamento'     => $funcionarios->filter(fn ($f) => $f->afastamentos->isNotEmpty())->count(),
            'setores'            => $porSetor->count(),
        ];

        return view('rh.composicao', [
            'porSetor'     => $porSetor,
            'hoje'         => $hoje,
            'estatisticas' => $estatisticas,
        ]);
    }

    public function syncLegacy(LegacyFuncionariosSyncService $syncService): RedirectResponse
    {
        $result = $syncService->sync();

        $message = ($result['synced'] ?? false)
            ? sprintf(
                'Legado sincronizado: %d cargos, %d funcionarios e %d afastamentos.',
                $result['cargos']['created'] ?? 0,
                $result['funcionarios']['created'] ?? 0,
                $result['afastamentos']['created'] ?? 0,
            )
            : ($result['warnings'][0] ?? 'Nao foi possivel sincronizar a base legada.');

        AuditLogger::log(
            moduleCode: 'rh',
            eventType: 'legacy.sync',
            entityType: 'rh_sync',
            entityId: 'legacy',
            description: 'Sincronizacao da base legada executada no modulo de RH.',
            metadata: $result
        );

        return redirect()
            ->route('rh.index')
            ->with('status', $message);
    }

    // ─── Estatísticas RH ─────────────────────────────────────────────────────

    public function showFuncionario(RhFuncionario $funcionario): View
    {
        $funcionario->load([
            'cargo',
            'user',
            'afastamentos' => fn ($q) => $q->orderByDesc('start_date'),
        ]);

        $hoje = Carbon::today();
        $currentAfastamento = $funcionario->currentAfastamento();

        return view('rh.funcionarios.show', [
            'funcionario' => $funcionario,
            'hoje' => $hoje,
            'currentAfastamento' => $currentAfastamento,
            'cargos' => RhCargo::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function relatorioAfastamentos(Request $request): View
    {
        $filters = $request->validate([
            'funcionario_id' => ['nullable', 'exists:rh_funcionarios,id'],
            'year'           => ['nullable', 'integer', 'min:2000', 'max:2099'],
            'month'          => ['nullable', 'integer', 'min:0', 'max:12'],
            'modo'           => ['nullable', 'in:todos,por-funcionario'],
            'reason'         => ['nullable', 'string', 'max:255'],
        ]);

        $hoje = Carbon::today();
        $year  = (int) ($filters['year']  ?? $hoje->year);
        $month = (int) ($filters['month'] ?? 0); // 0 = ano inteiro
        $modo  = $filters['modo'] ?? 'todos';

        if ($month > 0) {
            $periodoInicio = Carbon::create($year, $month, 1)->startOfMonth();
            $periodoFim    = $periodoInicio->copy()->endOfMonth();
            $periodoLabel  = $periodoInicio->translatedFormat('F \\d\\e Y');
        } else {
            $periodoInicio = Carbon::create($year, 1, 1)->startOfYear();
            $periodoFim    = Carbon::create($year, 12, 31)->endOfYear();
            $periodoLabel  = (string) $year;
        }

        $query = RhAfastamento::query()
            ->with('funcionario.cargo')
            ->where('is_active', true)
            ->where(function ($q) use ($periodoInicio, $periodoFim): void {
                $q->whereDate('start_date', '<=', $periodoFim)
                  ->where(function ($q2) use ($periodoInicio): void {
                      $q2->whereNull('end_date')->orWhereDate('end_date', '>=', $periodoInicio);
                  });
            })
            ->when($filters['funcionario_id'] ?? null, fn ($q, $id) => $q->where('funcionario_id', $id))
            ->when($filters['reason'] ?? null, fn ($q, $r) => $q->where('reason', 'like', '%'.$r.'%'))
            ->orderBy('start_date')
            ->orderBy(
                RhFuncionario::select('name')
                    ->whereColumn('rh_funcionarios.id', 'rh_afastamentos.funcionario_id')
                    ->limit(1)
            );

        $afastamentos = $query->get();

        // Agrupa por funcionário para exibição por-funcionario
        $porFuncionario = $afastamentos
            ->groupBy('funcionario_id')
            ->map(fn ($group) => [
                'funcionario' => $group->first()->funcionario,
                'afastamentos' => $group,
            ])
            ->sortBy(fn ($item) => $item['funcionario']?->name ?? '');

        $selectedFuncionario = isset($filters['funcionario_id'])
            ? RhFuncionario::find($filters['funcionario_id'])
            : null;

        // ── Detecção de conflitos ──────────────────────────────────────────
        // Identifica afastamentos de funcionários DISTINTOS com datas sobrepostas.
        // Um conflito existe quando dois afastamentos de servidores diferentes
        // se intersectam no tempo: start_i <= end_j AND start_j <= end_i.
        $conflictIds = collect();
        if ($afastamentos->count() > 1) {
            $arr = $afastamentos->values();
            $n   = $arr->count();
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $ai = $arr[$i];
                    $aj = $arr[$j];
                    if ($ai->funcionario_id === $aj->funcionario_id) {
                        continue;
                    }
                    // Tratar end_date nulo como o último dia do período consultado
                    $aiEnd = $ai->end_date ?? $periodoFim;
                    $ajEnd = $aj->end_date ?? $periodoFim;
                    if ($ai->start_date->lte($ajEnd) && $aj->start_date->lte($aiEnd)) {
                        $conflictIds->push($ai->id);
                        $conflictIds->push($aj->id);
                    }
                }
            }
            $conflictIds = $conflictIds->unique()->values();
        }

        return view('rh.afastamentos.relatorio', [
            'filters'            => $filters,
            'year'               => $year,
            'month'              => $month,
            'modo'               => $modo,
            'periodoInicio'      => $periodoInicio,
            'periodoFim'         => $periodoFim,
            'periodoLabel'       => $periodoLabel,
            'afastamentos'       => $afastamentos,
            'porFuncionario'     => $porFuncionario,
            'selectedFuncionario' => $selectedFuncionario,
            'hoje'               => $hoje,
            'conflictIds'        => $conflictIds,
        ]);
    }

    public function stats(): View
    {
        return view('rh.stats', $this->buildStatsData());
    }

    public function statsPrint(): View
    {
        return view('rh.stats-print', $this->buildStatsData());
    }

    private function buildStatsData(): array
    {
        $hoje = Carbon::today();

        // Headcount global
        $totalFuncionarios    = RhFuncionario::where('is_active', true)->count();
        $concorremEscala      = RhFuncionario::where('is_active', true)->where('concorre_escala', true)->count();
        $emAfastamentoHoje    = RhAfastamento::where('is_active', true)
            ->whereDate('start_date', '<=', $hoje)
            ->where(function ($q) use ($hoje) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $hoje);
            })->count();

        // Headcount por cargo
        $headcountPorCargo = RhFuncionario::query()
            ->with('cargo')
            ->where('is_active', true)
            ->get()
            ->groupBy(fn ($f) => $f->cargo?->name ?? 'Sem cargo')
            ->map(fn ($group, $cargo) => [
                'cargo'     => $cargo,
                'total'     => $group->count(),
                'escala'    => $group->where('concorre_escala', true)->count(),
            ])
            ->sortByDesc('total')
            ->values();

        // Afastamentos ativos hoje por motivo
        $afastados = RhAfastamento::with('funcionario.cargo')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $hoje)
            ->where(function ($q) use ($hoje) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $hoje);
            })->get();

        $porMotivo = $afastados
            ->groupBy('reason')
            ->map(fn ($g, $reason) => ['reason' => $reason, 'count' => $g->count()])
            ->sortByDesc('count')
            ->values();

        // Afastamentos próximos — agendados (início > hoje) nos próximos 60 dias
        $agendados = RhAfastamento::with('funcionario.cargo')
            ->where('is_active', true)
            ->whereDate('start_date', '>', $hoje)
            ->whereDate('start_date', '<=', $hoje->copy()->addDays(60))
            ->orderBy('start_date')
            ->get();

        // Afastamentos dos últimos 12 meses por mês (trend)
        $trend = [];
        for ($i = 11; $i >= 0; $i--) {
            $mes   = $hoje->copy()->subMonths($i)->startOfMonth();
            $label = $mes->locale('pt_BR')->isoFormat('MMM/YY');
            $count = RhAfastamento::where('is_active', true)
                ->whereDate('start_date', '<=', $mes->copy()->endOfMonth())
                ->where(function ($q) use ($mes) {
                    $q->whereNull('end_date')->orWhereDate('end_date', '>=', $mes);
                })->count();
            $trend[] = ['label' => $label, 'count' => $count];
        }

        // Feriados nos próximos 90 dias
        $feriadosProximos = RhHoliday::where('is_active', true)
            ->whereDate('holiday_date', '>=', $hoje)
            ->whereDate('holiday_date', '<=', $hoje->copy()->addDays(90))
            ->orderBy('holiday_date')
            ->get();

        // Setores com mais afastamentos hoje
        $setoresAfastados = $afastados
            ->groupBy(fn ($a) => $a->funcionario?->sector ?? 'Sem setor')
            ->map(fn ($g, $s) => ['setor' => $s, 'count' => $g->count()])
            ->sortByDesc('count')
            ->values();

        return compact(
            'hoje',
            'totalFuncionarios',
            'concorremEscala',
            'emAfastamentoHoje',
            'headcountPorCargo',
            'porMotivo',
            'agendados',
            'trend',
            'feriadosProximos',
            'setoresAfastados',
            'afastados',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function createOrUpdateAccessForFuncionario(RhFuncionario $funcionario, array $roleIds): User
    {
        $cpf = AccessCredentialPolicy::normalizeCpf((string) $funcionario->cpf);

        abort_if(strlen($cpf) !== 11, 422, 'CPF invalido para criar acesso do funcionario.');

        $user = User::query()->updateOrCreate(
            ['funcionario_id' => $funcionario->id],
            [
                'name' => $funcionario->name,
                'cpf' => $cpf,
                'rg' => $this->cleanNullable($funcionario->rg),
                'email' => $this->cleanNullable($funcionario->email),
                'phone' => $this->cleanNullable($funcionario->phone),
                'password' => AccessCredentialPolicy::firstAccessPassword($cpf),
                'is_active' => (bool) $funcionario->is_active,
                'must_change_password' => true,
                'tipo_usuario' => 'servidor',
                'notes' => $this->cleanNullable($funcionario->notes),
            ]
        );

        $user->roles()->sync(
            collect($roleIds)->mapWithKeys(
                fn (int $roleId): array => [$roleId => ['assigned_by' => Auth::id()]]
            )->all()
        );

        return $user;
    }

    private function cleanNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
