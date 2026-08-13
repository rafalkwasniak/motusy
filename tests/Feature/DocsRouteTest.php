<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocsRouteTest extends TestCase
{
    public function test_serves_an_existing_guide(): void
    {
        $this->get('/docs/api-guide.html')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('Motusy API', false);
    }

    public function test_serves_the_code_map(): void
    {
        $this->get('/docs/code-map.html')
            ->assertStatus(200)
            ->assertSee('mapa kodu', false);
    }

    public function test_keeps_documentation_out_of_search_engines(): void
    {
        $this->get('/docs/api-guide.html')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_missing_page_is_a_404(): void
    {
        $this->get('/docs/nie-ma-takiej-strony.html')->assertStatus(404);
    }

    /**
     * The markdown specification and any config sitting in docs/ must stay
     * unreachable: only the literal .html suffix is routed.
     */
    public function test_does_not_serve_the_markdown_specification(): void
    {
        $this->get('/docs/motusy-api.md')->assertStatus(404);
        $this->get('/docs/FOUNDATION.md')->assertStatus(404);
    }

    #[DataProvider('traversalAttempts')]
    public function test_rejects_path_traversal(string $attempt): void
    {
        $this->get($attempt)->assertStatus(404);
    }

    public static function traversalAttempts(): array
    {
        return [
            'parent directory' => ['/docs/../.env.html'],
            'encoded parent' => ['/docs/%2e%2e%2f.env.html'],
            'nested path' => ['/docs/subdir/secret.html'],
            'dot in slug' => ['/docs/api-guide.html.html'],
            'null byte' => ['/docs/api-guide%00.html'],
        ];
    }

    public function test_scramble_contract_still_reachable_alongside_the_guide_route(): void
    {
        $this->get('/docs/api')->assertStatus(200);
    }
}
