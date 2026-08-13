<?php

namespace Tests\Unit\Http\Responses;

use App\Http\Responses\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_omits_data_key_when_there_is_no_payload(): void
    {
        $payload = $this->decode(ApiResponse::success('Deleted.'));

        $this->assertSame(['success' => true, 'message' => 'Deleted.'], $payload);
        $this->assertArrayNotHasKey('data', $payload);
    }

    /**
     * An empty collection must still emit "data": [] so clients never have to tell
     * a missing key apart from an empty result set.
     */
    public function test_success_keeps_empty_array_payload(): void
    {
        $payload = $this->decode(ApiResponse::success('Listed.', []));

        $this->assertArrayHasKey('data', $payload);
        $this->assertSame([], $payload['data']);
    }

    public function test_success_omits_pagination_when_not_paginated(): void
    {
        $payload = $this->decode(ApiResponse::success('Listed.', [['id' => 1]]));

        $this->assertArrayNotHasKey('pagination', $payload);
    }

    public function test_paginated_emits_agreed_pagination_shape(): void
    {
        $paginator = new LengthAwarePaginator([['id' => 1]], total: 137, perPage: 25, currentPage: 1);

        $payload = $this->decode(ApiResponse::paginated('Listed.', $paginator));

        $this->assertSame([
            'current_page' => 1,
            'per_page' => 25,
            'total' => 137,
            'last_page' => 6,
        ], $payload['pagination']);
        $this->assertSame([['id' => 1]], $payload['data']);
    }

    public function test_paginated_emits_empty_data_array_for_no_results(): void
    {
        $paginator = new LengthAwarePaginator([], total: 0, perPage: 25, currentPage: 1);

        $payload = $this->decode(ApiResponse::paginated('Listed.', $paginator));

        $this->assertSame([], $payload['data']);
        $this->assertSame(0, $payload['pagination']['total']);
    }

    public function test_error_omits_errors_key_when_there_are_no_field_errors(): void
    {
        $response = ApiResponse::error('NOT_FOUND', 'Nie znaleziono zasobu.', null, 404);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'code' => 'NOT_FOUND', 'message' => 'Nie znaleziono zasobu.'],
            $this->decode($response),
        );
    }

    public function test_error_carries_field_errors(): void
    {
        $payload = $this->decode(
            ApiResponse::error('VALIDATION_ERROR', 'Podane dane są nieprawidłowe.', ['email' => ['Wymagane.']], 422),
        );

        $this->assertSame(['email' => ['Wymagane.']], $payload['errors']);
    }

    private function decode(\Illuminate\Http\JsonResponse $response): array
    {
        return json_decode($response->getContent(), true);
    }
}
