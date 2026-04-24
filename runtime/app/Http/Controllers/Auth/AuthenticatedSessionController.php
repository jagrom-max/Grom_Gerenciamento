<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AccessCredentialPolicy;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['login']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            AuditLogger::log(
                moduleCode: 'access',
                eventType: 'auth.login_throttled',
                entityType: 'auth.login',
                entityId: $credentials['login'],
                description: 'Tentativa de login bloqueada por excesso de repeticoes.',
                metadata: ['login' => $credentials['login']]
            );

            throw ValidationException::withMessages([
                'login' => 'Muitas tentativas. Aguarde um minuto e tente novamente.',
            ]);
        }

        // Detecta automaticamente o campo de login:
        // - 11 dígitos numéricos → CPF
        // - contém @ → email
        // - qualquer outro → username
        $rawLoginValue = (string) $credentials['login'];
        $cpfDigits = AccessCredentialPolicy::normalizeCpf($rawLoginValue);
        $loginValue = $rawLoginValue;

        if (strlen($cpfDigits) === 11) {
            $field = 'cpf';
            $loginValue = $cpfDigits;
        } elseif (filter_var($rawLoginValue, FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
        } else {
            $field = 'username';
        }

        if (! Auth::attempt([$field => $loginValue, 'password' => $credentials['password'], 'is_active' => true])) {
            RateLimiter::hit($throttleKey, 60);
            AuditLogger::log(
                moduleCode: 'access',
                eventType: 'auth.login_failed',
                entityType: 'auth.login',
                entityId: $credentials['login'],
                description: 'Tentativa de login sem sucesso.',
                metadata: [
                    'login' => $credentials['login'],
                    'field' => $field,
                ]
            );

            throw ValidationException::withMessages([
                'login' => 'Credenciais invalidas.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        AuditLogger::log(
            moduleCode: 'access',
            eventType: 'auth.login',
            entityType: 'user',
            entityId: $user->id,
            description: 'Login realizado com sucesso.'
        );

        if (config('grom_access.require_password_change') && $user->must_change_password) {
            return redirect()->route('password.edit');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user()) {
            AuditLogger::log(
                moduleCode: 'access',
                eventType: 'auth.logout',
                entityType: 'user',
                entityId: $request->user()->id,
                description: 'Logout realizado.'
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
