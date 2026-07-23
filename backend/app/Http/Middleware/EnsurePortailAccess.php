<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortailAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if ($user->hasAnyRole(['admin', 'collaborateur', 'secretaire'])) {
            return response()->json(['message' => 'Accès réservé aux clients.'], 403);
        }

        if (! $user->hasRole('client') || ! $user->portail_actif) {
            return response()->json(['message' => 'Accès portail désactivé.'], 403);
        }

        return $next($request);
    }
}
