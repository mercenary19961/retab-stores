<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Page-render tests must not depend on a fresh `public/build` manifest
        // (gitignored, absent in CI, stale locally after adding a page).
        $this->withoutVite();
    }
}
