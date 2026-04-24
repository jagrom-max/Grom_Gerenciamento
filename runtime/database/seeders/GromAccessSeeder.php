<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RhFuncionario;
use App\Models\User;
use App\Support\AccessCredentialPolicy;
use Illuminate\Database\Seeder;

class GromAccessSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('grom_access.permissions') as $code => $permission) {
            Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'module_code' => $permission['module'],
                    'name' => $permission['name'],
                ]
            );
        }

        $allPermissionIds = Permission::query()->pluck('id', 'code');

        foreach (config('grom_access.roles') as $code => $roleConfig) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $roleConfig['name']]
            );

            $permissionIds = $roleConfig['permissions'] === ['*']
                ? $allPermissionIds->values()->all()
                : $allPermissionIds->only($roleConfig['permissions'])->values()->all();

            $role->permissions()->sync($permissionIds);
        }

        $bootstrapAdmin = config('grom_access.bootstrap_admin');

        $configuredCpf = AccessCredentialPolicy::normalizeCpf($bootstrapAdmin['cpf'] ?? '');
        $cpf = $configuredCpf !== '' ? $configuredCpf : AccessCredentialPolicy::superManagerCpf();
        $bootstrapPassword = (string) ($bootstrapAdmin['password'] ?? '');
        if ($bootstrapPassword === '' && $cpf !== '') {
            $bootstrapPassword = AccessCredentialPolicy::firstAccessPassword($cpf);
        }

        $adminData = [
            'name' => $bootstrapAdmin['name'],
            'username' => $bootstrapAdmin['username'],
            'email' => $bootstrapAdmin['email'],
            'is_active' => true,
        ];

        if ($cpf !== '') {
            $adminData['cpf'] = $cpf;

            $funcionario = RhFuncionario::query()
                ->where('cpf', 'like', '%' . substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2) . '%')
                ->orWhere('cpf', $cpf)
                ->first();

            if ($funcionario) {
                $adminData['funcionario_id'] = $funcionario->id;
                $adminData['tipo_usuario'] = 'servidor';
            }
        }

        $lookup = $cpf !== ''
            ? ['cpf' => $cpf]
            : ['username' => $bootstrapAdmin['username']];

        $admin = User::query()->firstOrNew($lookup);
        $isNew = ! $admin->exists;

        $admin->fill($adminData);

        if ($isNew) {
            if ($bootstrapPassword === '') {
                $bootstrapPassword = AccessCredentialPolicy::firstAccessPassword(AccessCredentialPolicy::superManagerCpf());
            }

            $admin->password = $bootstrapPassword;
            $admin->must_change_password = true;
        }

        $admin->save();

        $roleId = Role::query()->where('code', 'super_admin')->value('id');

        $admin->roles()->sync([
            $roleId => ['assigned_by' => $admin->id],
        ]);
    }
}
