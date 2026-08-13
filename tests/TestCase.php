<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set here rather than only in phpunit.xml: once config is cached, the values
        // are baked into the cache file and the <env> entries no longer reach it.
        // This runs after the container is up, so it wins either way.
        config(['services.discord.webhook' => null]);

        // A test run must never reach anything outside this machine. The alert channel
        // already learned that lesson: tests throw on purpose, those exceptions are
        // reported, and the reports went to the real Discord webhook.
        Http::preventStrayRequests();
    }
}
