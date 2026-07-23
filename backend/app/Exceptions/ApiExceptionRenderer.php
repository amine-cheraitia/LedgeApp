<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Renderer unifie pour toutes les exceptions sur les routes /api/*.
 *
 * Garantit qu'aucun detail technique (SQL, host, stack) ne fuit au client,
 * meme quand APP_DEBUG=true. Le detail reel est logue cote serveur.
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return self::respond(401, 'Authentification requise.');
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return self::respond(403, 'Acces refuse.');
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return self::respond(404, 'Ressource introuvable.');
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return self::respond(405, 'Methode non autorisee.');
        }

        if ($e instanceof TokenMismatchException) {
            return self::respond(419, 'Session expiree. Rechargez la page et reessayez.');
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return self::respond(429, 'Trop de tentatives. Reessayez dans quelques instants.');
        }

        if ($e instanceof QueryException || $e instanceof PDOException) {
            self::logServer($e, $request, 'Database error');

            return self::respond(503, 'Service temporairement indisponible. Reessayez plus tard.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $status >= 500
                ? 'Erreur interne. L\'equipe a ete notifiee.'
                : ($e->getMessage() ?: 'Erreur.');

            if ($status >= 500) {
                self::logServer($e, $request, 'HTTP exception');
            }

            return self::respond($status, $message);
        }

        // Catch-all : ne rien exposer
        self::logServer($e, $request, 'Unhandled exception');

        return self::respond(500, 'Erreur interne. L\'equipe a ete notifiee.');
    }

    private static function respond(int $status, string $message): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    private static function logServer(Throwable $e, Request $request, string $context): void
    {
        Log::error($context.': '.$e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => optional($request->user())->id,
        ]);
    }
}
