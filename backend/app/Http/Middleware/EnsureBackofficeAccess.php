<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackofficeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if ($user->hasRole('client')) {
            return response()->json(['message' => 'Accès réservé au back-office.'], 403);
        }

        if (! $user->hasAnyRole(['admin', 'collaborateur', 'secretaire'])) {
            return response()->json(['message' => 'Compte en attente d\'activation.'], 403);
        }

        return $next($request);
    }
}
