<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adiciona cabeçalhos de segurança a todas as respostas HTML.
 *
 * Não usa cache-control agressivo para não interferir com diálogos de impressão.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Impede que navegadores façam sniff de content-type
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Bloqueia embedding em iframes de outras origens
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Controle de referrer ao navegar entre origens
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Desabilita FLoC / Topics API
        $response->headers->set('Permissions-Policy', 'interest-cohort=()');

        // CSP básica: carrega recursos apenas da mesma origem
        // 'unsafe-inline' necessário para estilos inline do Blade/Tailwind
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data:; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "frame-ancestors 'self';"
        );

        return $response;
    }
}
