<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        $mustChangePassword = (bool) config('grom_access.require_password_change') && (bool) $request->user()?->must_change_password;

        return view('auth.password-change', [
            'mustChangePassword' => $mustChangePassword,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ];

        $data = $request->validate($rules);

        if (Hash::check($data['password'], $user->getAuthPassword())) {
            return back()->withErrors([
                'password' => 'A nova senha deve ser diferente da senha atual.',
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'auth.password_changed',
            entityType: 'user',
            entityId: $user->id,
            description: 'Senha atualizada pelo proprio usuario.'
        );

        return redirect()->route('dashboard')->with('status', 'Senha atualizada com sucesso.');
    }
}
