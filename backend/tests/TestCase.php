<?php

namespace Tests;

use App\Contracts\SmsProvider;
use App\Services\Sms\FakeSmsProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SmsProvider::class, new FakeSmsProvider());
    }
}
