<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RefreshDatabase starts with migrate:fresh. Pointed at the live connection it would
 * drop every table in the production database, and nothing about the run would say so
 * — the suite would simply pass on an empty schema.
 *
 * A cached config silently overrides phpunit.xml, so this cannot be left to
 * configuration alone.
 */
class TestDatabaseIsIsolatedTest extends TestCase
{
    public function test_the_suite_never_runs_against_the_live_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }
}
