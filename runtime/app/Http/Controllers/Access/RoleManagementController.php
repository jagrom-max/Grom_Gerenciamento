<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function index(): View
    {
        return view('access.roles.index', [
            'roles' => Role::query()
                ->with(['permissions' => fn ($query) => $query->orderBy('module_code')->orderBy('name')])
                ->withCount('users')
                ->orderBy('name')
                ->get(),
            'permissionsByModule' => Permission::query()
                ->orderBy('module_code')
                ->orderBy('name')
                ->get()
                ->groupBy('module_code'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPayload($request);

        $role = Role::query()->create([
            'code' => strtolower($data['code']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'roles.create',
            entityType: 'role',
            entityId: $role->id,
            description: 'Perfil de acesso criado pelo administrador.',
            metadata: [
                'code' => $role->code,
                'name' => $role->name,
                'permissions' => $role->permissions()->count(),
            ]
        );

        return redirect()->route('access.roles.index')->with('status', 'Perfil criado com sucesso.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validatedPayload($request, $role);

        $role->update([
            'code' => strtolower($data['code']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'roles.update',
            entityType: 'role',
            entityId: $role->id,
            description: 'Perfil de acesso atualizado pelo administrador.',
            metadata: [
                'code' => $role->code,
                'name' => $role->name,
                'permissions' => $role->permissions()->count(),
            ]
        );

        return redirect()->route('access.roles.index')->with('status', 'Perfil atualizado com sucesso.');
    }

    private function validatedPayload(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('roles', 'code')->ignore($role?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
    }

    private function syncPermissions(Role $role, array $permissionIds): void
    {
        $normalized = collect($permissionIds)
            ->map(fn (mixed $permissionId): int => (int) $permissionId)
            ->filter(fn (int $permissionId): bool => $permissionId > 0)
            ->unique()
            ->values()
            ->all();

        $role->permissions()->sync($normalized);
    }
}
