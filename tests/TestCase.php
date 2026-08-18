<?php

namespace Tests;

use App\Services\WhatsApp\LogGateway;
use App\Services\WhatsApp\WhatsAppGateway;
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

    /**
     * Pretend WhatsApp can really deliver, while still logging instead of calling Meta.
     *
     * 🔑 Tests run on the log driver, which now honestly reports `isLive() === false`,
     * so anything that REQUIRES delivery (the sign-in code) refuses by default. That
     * is the production-accurate default and it stays opt-in on purpose: a test that
     * needs a deliverable channel should have to say so, rather than the suite
     * quietly asserting a flow that a real unconfigured deployment cannot perform.
     */
    protected function withLiveWhatsapp(): void
    {
        $this->app->instance(WhatsAppGateway::class, new class extends LogGateway
        {
            public function isLive(): bool
            {
                return true;
            }
        });
    }
}
