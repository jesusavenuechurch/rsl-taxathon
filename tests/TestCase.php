<?php

namespace Tests;

use App\Contracts\AI\AIProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeAIProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Every case open/referral queues an AI brief (sync in tests). Default
        // to a fake that fails instantly, so unrelated tests never make a real
        // network call; brief-specific tests rebind this themselves.
        $this->app->bind(AIProvider::class, fn () => FakeAIProvider::unavailable());
    }
}
