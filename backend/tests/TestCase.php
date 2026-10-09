<?php

namespace Tests;

use App\Contracts\SmsProvider;
use App\Services\Sms\FakeSmsProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // before RefreshDatabase gets to wipe anything
    protected function setUpTraits()
    {
        $connection = config('database.connections.' . config('database.default'), []);

        if (($refusal = DatabaseGuard::refusal($connection)) !== null) {
            throw new RuntimeException($refusal);
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SmsProvider::class, new FakeSmsProvider());
    }
}
