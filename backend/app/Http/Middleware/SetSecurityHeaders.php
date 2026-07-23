<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // HSTS : uniquement sur les requetes HTTPS pour ne pas gener le dev en HTTP local.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Telescope / health-dashboard utilisent du JS/CSS inline : on relache uniquement
        // la CSP pour ces pages, sans retirer les autres en-tetes de securite.
        if (! $request->is('telescope*') && ! $request->is('health*')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self' ".env('FRONTEND_URL', 'http://localhost:5173').'; object-src \'none\'; frame-ancestors \'none\';'
            );
        }

        return $response;
    }
}
