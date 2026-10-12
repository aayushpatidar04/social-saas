<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No compiled assets in CI, and no test may reach the real internet.
        $this->withoutVite();
        Http::preventStrayRequests();
    }
}
