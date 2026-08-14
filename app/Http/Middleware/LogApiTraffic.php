<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Temporary tracing for a client that misbehaves without leaving a trace.
 *
 * Nothing about a rejected request reaches the log on its own: validation errors and
 * business answers like 409 are normal responses, not faults, so there is no way to
 * tell "the app never called us" apart from "it called us and we said no".
 *
 * Off unless API_DIAGNOSTICS is set. Meant to be switched on for one run through the
 * app and switched straight back off.
 */
class LogApiTraffic
{
    /**
     * Field names worth seeing to diagnose a rejected form. Anything not listed is
     * reported by name only — the log must never grow a copy of somebody's password.
     */
    private const SAFE_FIELDS = [
        'nickname', 'gender', 'brand', 'model', 'production_year', 'color', 'incognito', 'device_id', 'platform',
        // Booleans arriving as the strings "true"/"false" are the usual reason a form is
        // rejected, and seeing the raw value is the whole point. They carry no personal
        // data, unlike the fields they describe.
        'first_name_visible', 'last_name_visible', 'phone_visible', 'email_visible',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // A missing token throws rather than returns, and the handler that turns it
            // into a 401 sits outside this pipeline. Without catching it here the one
            // case worth diagnosing — the app not sending the token at all — would leave
            // no trace, and silence would read as "the app never called us".
            $this->write($request, null, $e);

            throw $e;
        }

        $this->write($request, $response);

        return $response;
    }

    private function write(Request $request, ?Response $response, ?Throwable $e = null): void
    {
        if (! config('motusy.diagnostics.enabled')) {
            return;
        }

        $payload = $request->except(['password', 'password_confirmation', 'push_token', 'ble_token']);

        Log::info('API', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response?->getStatusCode() ?? class_basename($e),
            'user' => $request->user()?->id,
            'authorization' => $request->hasHeader('Authorization') ? 'jest' : 'BRAK',
            'content_type' => $request->header('Content-Type'),

            // Tells an empty body apart from one we failed to parse. Without it a form
            // that arrives with nothing attached looks exactly like a parsing fault.
            'content_length' => $request->header('Content-Length'),
            'pliki' => array_keys($request->allFiles()),
            'pola' => array_keys($payload),
            'wartosci' => array_intersect_key($payload, array_flip(self::SAFE_FIELDS)),
            'odpowiedz' => $response !== null ? $this->outcome($response) : $e?->getMessage(),
        ]);
    }

    /**
     * Only the parts that say why a request was turned down — never the payload of a
     * successful one, which would put personal data in the log.
     */
    private function outcome(Response $response): ?array
    {
        if ($response->getStatusCode() < 400 || ! method_exists($response, 'getData')) {
            return null;
        }

        $body = (array) $response->getData(true);

        return array_intersect_key($body, array_flip(['code', 'message', 'errors']));
    }
}
