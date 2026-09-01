<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AccountToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uwierzytelnianie urządzeń tokenem konta (kontrakt telemetrii §2).
 *
 * Token jedzie w zwykłym nagłówku `Authorization: Bearer`. Nie używamy tu
 * Sanctuma, bo ten trzyma skrót, a nasz token ma być stale czytelny
 * w panelu — szczegóły w CLAUDE.md §2.
 *
 * Odpowiedź 401 jest dla urządzenia sygnałem, żeby **przestało próbować**
 * (kontrakt §3), więc nie wolno jej zwracać przy zwykłej awarii serwera.
 */
class AuthenticateAccountToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Normalizacja wybacza małe litery i brak myślników — ktoś, kto
        // przepisuje token ręcznie, nie powinien dostać odmowy za kreskę.
        $token = AccountToken::normalize($request->bearerToken());

        $user = $token === null
            ? null
            : User::firstWhere('api_token', $token);

        if ($user === null) {
            return response()->json([
                'message' => __('Nieprawidłowy token konta.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->setUserResolver(fn (): User => $user);

        return $next($request);
    }
}
