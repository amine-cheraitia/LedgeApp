<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiExceptionRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Routes de test isolees pour declencher chaque type d'exception
        Route::middleware('api')->prefix('api/v1/__test')->group(function () {
            Route::get('/db-error', function () {
                throw new QueryException(
                    'mysql',
                    'select * from `sessions` where `id` = ?',
                    ['leak'],
                    new \PDOException('SQLSTATE[HY000] [2002] connection refused, Host: 127.0.0.1, Port: 3306, Database: ledge')
                );
            });

            Route::get('/boom', function () {
                throw new \RuntimeException('Stack trace secrete: /var/www/secret/file.php line 42');
            });

            Route::get('/method-test', function () {
                return response()->json(['ok' => true]);
            });
        });
    }

    public function test_database_error_does_not_leak_sql_or_host(): void
    {
        $response = $this->getJson('/api/v1/__test/db-error');

        $response->assertStatus(503);
        $response->assertJsonStructure(['message']);

        $body = $response->getContent();

        // Aucune fuite : ni SQL, ni host, ni port, ni nom de DB, ni SQLSTATE
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('127.0.0.1', $body);
        $this->assertStringNotContainsString('3306', $body);
        $this->assertStringNotContainsString('mysql', $body);
        $this->assertStringNotContainsString('sessions', $body);
        $this->assertStringNotContainsString('select', strtolower($body));
        $this->assertStringNotContainsString('PDO', $body);

        $response->assertJson(['message' => 'Service temporairement indisponible. Reessayez plus tard.']);
    }

    public function test_unhandled_exception_does_not_leak_stack_or_path(): void
    {
        $response = $this->getJson('/api/v1/__test/boom');

        $response->assertStatus(500);
        $body = $response->getContent();

        $this->assertStringNotContainsString('/var/www', $body);
        $this->assertStringNotContainsString('secret', $body);
        $this->assertStringNotContainsString('line 42', $body);
        $this->assertStringNotContainsString('Stack trace', $body);

        $response->assertJson(['message' => 'Erreur interne. L\'equipe a ete notifiee.']);
    }

    public function test_404_returns_clean_json(): void
    {
        $response = $this->getJson('/api/v1/route-inexistante');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Ressource introuvable.']);
    }

    public function test_405_returns_clean_json(): void
    {
        $response = $this->postJson('/api/v1/__test/method-test');

        $response->assertStatus(405);
        $response->assertJson(['message' => 'Methode non autorisee.']);
    }

    public function test_unauthenticated_returns_401_with_clean_message(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Authentification requise.']);
    }

    public function test_response_is_always_json_for_api_routes(): void
    {
        $response = $this->getJson('/api/v1/__test/db-error');

        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }
}
