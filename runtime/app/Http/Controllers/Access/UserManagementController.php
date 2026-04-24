<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Enums\LavradoUnidade;
use App\Models\Cartorio;
use App\Models\RhFuncionario;
use App\Models\UserScope;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessCredentialPolicy;
use App\Support\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with(['roles', 'scopes', 'funcionario'])->orderBy('name')->get();

        return view('access.users.index', [
            'users'              => $users,
            'servidores'         => $users->where('tipo_usuario', 'servidor'),
            'visitantes'         => $users->where('tipo_usuario', 'visitante'),
            'roles'              => Role::query()->orderBy('name')->get(),
            'funcionariosSemAcesso' => RhFuncionario::query()
                ->whereDoesntHave('user')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'cartorios' => Cartorio::query()->orderBy('number')->get(),
            'cartorioLabels' => Cartorio::query()
                ->orderBy('number')
                ->get()
                ->mapWithKeys(fn (Cartorio $cartorio): array => [
                    $cartorio->id => str_pad((string) $cartorio->number, 3, '0', STR_PAD_LEFT).' - '.$cartorio->name,
                ]),
            'lavradoUnidades' => collect(LavradoUnidade::cases())
                ->map(fn (LavradoUnidade $unit): array => [
                    'value' => $unit->value,
                    'label' => $unit->label(),
                ])
                ->values(),
            'lavradoUnidadeLabels' => collect(LavradoUnidade::cases())
                ->mapWithKeys(fn (LavradoUnidade $unit): array => [
                    $unit->value => $unit->label(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperAdminCanManageAccess();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'cpf'      => ['required', 'string', 'regex:/^\d{11}$/', 'unique:users,cpf'],
            'rg'       => ['nullable', 'string', 'max:50'],
            'username' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:users,username'],
            'email'    => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:50'],
            'notes'    => ['nullable', 'string', 'max:2000'],
            'roles'    => ['array'],
            'roles.*'  => ['integer', 'exists:roles,id'],
        ], [
            'cpf.required' => 'O CPF é obrigatório.',
            'cpf.regex'    => 'O CPF deve conter exatamente 11 dígitos numéricos (sem pontuação).',
            'cpf.unique'   => 'Este CPF já está cadastrado.',
        ]);

        $user = User::query()->create([
            'name'                 => $data['name'],
            'cpf'                  => $data['cpf'],
            'rg'                   => $this->cleanNullable($data['rg'] ?? null),
            'username'             => $data['username'] ?? null,
            'email'                => $this->cleanNullable($data['email'] ?? null),
            'phone'                => $this->cleanNullable($data['phone'] ?? null),
            'password'             => $this->firstAccessPasswordFromCpf($data['cpf']),
            'is_active'            => true,
            'must_change_password' => true,
            'tipo_usuario'         => 'visitante',
            'notes'                => $this->cleanNullable($data['notes'] ?? null),
        ]);

        $this->syncRoles($user, $data['roles'] ?? [], Auth::id());

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'users.create',
            entityType: 'user',
            entityId: $user->id,
            description: 'Visitante criado pelo administrador.',
            metadata: ['cpf' => $user->cpf, 'name' => $user->name]
        );

        return redirect()->route('access.users.index', ['tab' => 'visitantes'])
            ->with('status', 'Visitante criado com sucesso. Login: CPF informado. Senha inicial: DDM + CPF (troca obrigatoria no primeiro acesso).');
    }

    // ---------------------------------------------------------------
    // Criar acesso a partir de funcionário do RH
    // ---------------------------------------------------------------
    public function storeFromFuncionario(Request $request, RhFuncionario $funcionario): RedirectResponse
    {
        $this->ensureSuperAdminCanManageAccess();

        abort_if($funcionario->user()->exists(), 422, 'Este servidor já possui acesso ao sistema.');

        $data = $request->validate([
            'roles'    => ['array'],
            'roles.*'  => ['integer', 'exists:roles,id'],
        ]);

        abort_if(
            blank($funcionario->cpf),
            422,
            'O servidor não possui CPF cadastrado. Preencha o CPF na ficha do funcionário antes de criar o acesso.'
        );

        $cpf = AccessCredentialPolicy::normalizeCpf($funcionario->cpf);
        abort_if(
            strlen($cpf) !== 11,
            422,
            'O CPF do servidor deve conter exatamente 11 digitos para criar o acesso.'
        );

        $user = User::query()->create([
            'name'                 => $funcionario->name,
            'cpf'                  => $cpf,
            'rg'                   => $this->cleanNullable($funcionario->rg),
            'email'                => $this->cleanNullable($funcionario->email),
            'phone'                => $this->cleanNullable($funcionario->phone),
            'password'             => $this->firstAccessPasswordFromCpf($cpf),
            'is_active'            => $funcionario->is_active,
            'must_change_password' => true,
            'funcionario_id'       => $funcionario->id,
            'tipo_usuario'         => 'servidor',
            'notes'                => $this->cleanNullable($funcionario->notes),
        ]);

        $this->syncRoles($user, $data['roles'] ?? [], Auth::id());

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'users.create_from_funcionario',
            entityType: 'user',
            entityId: $user->id,
            description: 'Acesso criado a partir de servidor RH.',
            metadata: [
                'cpf'           => $user->cpf,
                'funcionario_id'=> $funcionario->id,
                'name'          => $user->name,
            ]
        );

        return redirect()
            ->route('access.users.index', ['tab' => 'servidores'])
            ->with('status', "Acesso criado para {$funcionario->name}. Login: CPF ({$cpf}). Senha inicial: DDM + CPF.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureSuperAdminCanManageAccess();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'cpf'      => ['nullable', 'string', 'regex:/^\d{11}$/', Rule::unique('users', 'cpf')->ignore($user->id)],
            'rg'       => ['nullable', 'string', 'max:50'],
            'username' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email'    => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'    => ['nullable', 'string', 'max:50'],
            'notes'    => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles'    => ['array'],
            'roles.*'  => ['integer', 'exists:roles,id'],
        ]);

        $payload = [
            'name'     => $data['name'],
            'cpf'      => $data['cpf'] ?? $user->cpf,
            'rg'       => $this->cleanNullable($data['rg'] ?? null),
            'username' => $data['username'] ?? $user->username,
            'email'    => $this->cleanNullable($data['email'] ?? null),
            'phone'    => $this->cleanNullable($data['phone'] ?? null),
            'notes'    => $this->cleanNullable($data['notes'] ?? null),
        ];

        if (! empty($data['password'])) {
            $payload['password']             = $data['password'];
            $payload['must_change_password'] = true;
        }

        $user->update($payload);
        $this->syncRoles($user, $data['roles'] ?? [], Auth::id());

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'users.update',
            entityType: 'user',
            entityId: $user->id,
            description: 'Usuario atualizado pelo administrador.',
            metadata: ['cpf' => $user->cpf, 'name' => $user->name]
        );

        return redirect()->route('access.users.index')->with('status', 'Usuário atualizado com sucesso.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->ensureSuperAdminCanManageAccess();

        abort_if(Auth::id() === $user->id, 422, 'Nao e permitido inativar o proprio usuario nesta tela.');

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'users.toggle_active',
            entityType: 'user',
            entityId: $user->id,
            description: $user->is_active ? 'Usuario ativado.' : 'Usuario inativado.',
            metadata: ['username' => $user->username]
        );

        return redirect()->route('access.users.index')->with('status', 'Status do usuario atualizado.');
    }

    public function storeScope(Request $request, User $user): RedirectResponse
    {
        $this->ensureSuperAdminCanManageAccess();

        $data = $request->validate([
            'scope_type' => ['required', Rule::in(['cartorio', 'lavrado_unidade'])],
            'scope_key' => ['required', 'string', 'max:255'],
        ]);

        $this->ensureValidScopeKey($data['scope_type'], $data['scope_key']);

        UserScope::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'scope_type' => $data['scope_type'],
                'scope_key' => $data['scope_key'],
            ],
            [
                'created_by' => Auth::id(),
            ]
        );

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'users.scope_create',
            entityType: 'user_scope',
            entityId: $user->id,
            description: 'Escopo de acesso atribuido ao usuario.',
            metadata: [
                'username' => $user->username,
                'scope_type' => $data['scope_type'],
                'scope_key' => $data['scope_key'],
            ]
        );

        return redirect()->route('access.users.index')->with('status', 'Escopo atribuido com sucesso.');
    }

    public function destroyScope(User $user, UserScope $scope): RedirectResponse
    {
        $this->ensureSuperAdminCanManageAccess();

        abort_unless($scope->user_id === $user->id, 404);

        $scopeData = [
            'scope_type' => $scope->scope_type,
            'scope_key' => $scope->scope_key,
        ];

        $scope->delete();

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'users.scope_delete',
            entityType: 'user_scope',
            entityId: $user->id,
            description: 'Escopo de acesso removido do usuario.',
            metadata: [
                'username' => $user->username,
                'scope_type' => $scopeData['scope_type'],
                'scope_key' => $scopeData['scope_key'],
            ]
        );

        return redirect()->route('access.users.index')->with('status', 'Escopo removido com sucesso.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->ensureSuperAdminCanManageAccess();

        $cpf = AccessCredentialPolicy::normalizeCpf($user->cpf);
        abort_if(strlen($cpf) !== 11, 422, 'Nao foi possivel redefinir: CPF invalido para gerar senha padrao.');

        $user->update([
            'password'             => $this->firstAccessPasswordFromCpf($cpf),
            'must_change_password' => true,
        ]);

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'users.password_reset',
            entityType: 'user',
            entityId: $user->id,
            description: 'Senha do usuario redefinida pelo administrador.',
            metadata: ['username' => $user->username, 'reset_by' => Auth::user()?->username]
        );

        return redirect()->route('access.users.index')->with('status', 'Senha redefinida para o padrao DDM + CPF. O usuario devera altera-la no proximo acesso.');
    }

    private function firstAccessPasswordFromCpf(string $cpf): string
    {
        return AccessCredentialPolicy::firstAccessPassword($cpf);
    }

    private function cleanNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureSuperAdminCanManageAccess(): void
    {
        $currentUser = Auth::user();

        if (! $currentUser instanceof User || ! $currentUser->isSuperAdmin()) {
            throw new AuthorizationException('Somente o super_admin pode definir ou alterar tipos de acesso e permissoes.');
        }
    }

    private function syncRoles(User $user, array $roleIds, ?int $assignedBy): void
    {
        if ($roleIds === []) {
            $user->roles()->sync([]);

            return;
        }

        $user->roles()->sync(
            collect($roleIds)->mapWithKeys(
                fn (int $roleId): array => [$roleId => ['assigned_by' => $assignedBy]]
            )->all()
        );
    }

    private function ensureValidScopeKey(string $scopeType, string $scopeKey): void
    {
        if ($scopeType === 'cartorio') {
            Cartorio::query()->whereKey($scopeKey)->firstOrFail();

            return;
        }

        if ($scopeType === 'lavrado_unidade') {
            $allowed = collect(LavradoUnidade::cases())->map(fn (LavradoUnidade $unit): string => $unit->value)->all();
            abort_unless(in_array($scopeKey, $allowed, true), 422, 'Unidade lavrada invalida.');
        }
    }
}
