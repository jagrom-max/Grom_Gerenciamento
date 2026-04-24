<?php

namespace App\Http\Controllers;

use App\Support\AccessCredentialPolicy;
use Illuminate\Contracts\View\View;

class PilotAccessController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $bootstrapCpf = AccessCredentialPolicy::normalizeCpf((string) config('grom_access.bootstrap_admin.cpf'));
        $password = (string) config('grom_access.bootstrap_admin.password');
        if ($password === '' && $bootstrapCpf !== '') {
            $password = AccessCredentialPolicy::firstAccessPassword($bootstrapCpf);
        }
        if ($password === '') {
            $password = AccessCredentialPolicy::firstAccessPassword(AccessCredentialPolicy::superManagerCpf());
        }

        return view('pilot.access', [
            'password' => $password,
            'accounts' => [
                [
                    'label' => 'Administrador bootstrap',
                    'username' => config('grom_access.bootstrap_admin.username', 'admin'),
                    'password' => $password,
                    'note' => 'Troca obrigatoria de senha no primeiro acesso.',
                ],
                [
                    'label' => 'Gestor demo',
                    'username' => 'gestor.demo',
                    'password' => $password,
                    'note' => 'Acesso para navegacao gerencial do piloto.',
                ],
                [
                    'label' => 'Operador demo',
                    'username' => 'operador.demo',
                    'password' => $password,
                    'note' => 'Acesso restrito para conferencia de permissao.',
                ],
            ],
        ]);
    }
}
