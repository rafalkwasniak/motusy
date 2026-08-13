<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResponseContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: without an Accept header the framework used to redirect to the
     * missing "login" route and answer 500 instead of 401.
     */
    public function test_protected_endpoint_returns_401_envelope_without_accept_header(): void
    {
        $response = $this->get('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertExactJson([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => 'Brak uwierzytelnienia.',
            ]);
    }

    public function test_protected_endpoint_returns_401_envelope_with_accept_header(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertExactJson([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => 'Brak uwierzytelnienia.',
            ]);
    }

    public function test_unknown_api_route_returns_404_envelope(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Nie znaleziono zasobu.',
            ]);
    }

    public function test_wrong_method_returns_405_envelope(): void
    {
        $response = $this->postJson('/api/v1/auth/me');

        $response->assertStatus(405)
            ->assertExactJson([
                'success' => false,
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'Niedozwolona metoda.',
            ]);
    }

    public function test_validation_failure_returns_422_envelope_with_errors(): void
    {
        Route::middleware('api')->post('/api/v1/testing/validate', function (Request $request) {
            $request->validate(['email' => 'required|email']);
        });

        $response = $this->postJson('/api/v1/testing/validate', ['email' => 'not-an-email']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Podane dane są nieprawidłowe.',
            ])
            ->assertJsonStructure(['success', 'code', 'message', 'errors' => ['email']]);
    }

    /**
     * Guards against a missing lang/pl file, which makes the validator emit raw
     * translation keys such as "validation.email" straight to the client.
     */
    public function test_validation_errors_are_translated_not_raw_keys(): void
    {
        Route::middleware('api')->post('/api/v1/testing/translated', function (Request $request) {
            $request->validate(['email' => 'required|email']);
        });

        $response = $this->postJson('/api/v1/testing/translated', ['email' => 'zly']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Pole email musi być prawidłowym adresem e-mail.');
    }

    public function test_unmapped_exception_returns_500_envelope_without_diagnostics(): void
    {
        Route::middleware('api')->get('/api/v1/testing/boom', function () {
            throw new \RuntimeException('database credentials leaked in this message');
        });

        $response = $this->getJson('/api/v1/testing/boom');

        $response->assertStatus(500)
            ->assertExactJson([
                'success' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Błąd serwera.',
            ]);

        $response->assertDontSee('database credentials leaked in this message');
    }
}
