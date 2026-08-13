<?php

namespace App\Http\Responses;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Single source of the API response envelope: { success, message, data?, pagination?, errors? }.
 *
 * Every endpoint and every handled exception goes through here, so the shape can
 * never drift between controllers.
 */
class ApiResponse
{
    /**
     * Pass null as $data to omit the key entirely; pass an empty array to emit "data": [].
     * Collections must always pass an array so clients never have to tell
     * "key absent" apart from "no results".
     */
    public static function success(
        string $message,
        mixed $data = null,
        ?array $pagination = null,
        int $status = 200,
    ): JsonResponse {
        $payload = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($pagination !== null) {
            $payload['pagination'] = $pagination;
        }

        return response()->json($payload, $status);
    }

    public static function paginated(
        string $message,
        LengthAwarePaginator $paginator,
        ?Closure $map = null,
    ): JsonResponse {
        $items = collect($paginator->items());

        return self::success(
            $message,
            $map ? $items->map($map)->all() : $items->all(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public static function error(
        string $message,
        ?array $errors = null,
        int $status = 400,
    ): JsonResponse {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Diagnostics never reach the client: unmapped throwables collapse to a generic
     * 500 and the details stay in the logs.
     */
    public static function fromException(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => self::error(
                __('api.validation_failed'),
                $e->errors(),
                422,
            ),
            $e instanceof AuthenticationException => self::error(__('api.unauthenticated'), null, 401),
            $e instanceof AuthorizationException => self::error(__('api.forbidden'), null, 403),
            $e instanceof ModelNotFoundException => self::error(__('api.not_found'), null, 404),
            $e instanceof HttpExceptionInterface => self::error(
                self::messageForStatus($e->getStatusCode()),
                null,
                $e->getStatusCode(),
            ),
            default => self::error(__('api.server_error'), null, 500),
        };
    }

    private static function messageForStatus(int $status): string
    {
        return match ($status) {
            401 => __('api.unauthenticated'),
            403 => __('api.forbidden'),
            404 => __('api.not_found'),
            405 => __('api.method_not_allowed'),
            429 => __('api.too_many_requests'),
            default => $status >= 500 ? __('api.server_error') : __('api.bad_request'),
        };
    }
}
