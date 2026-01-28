<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Services\Sms\SmsSender;
use App\Services\Sms\FakeSmsSender;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Replace real SMS sender with fake one for all tests
        $this->app->bind(SmsSender::class, FakeSmsSender::class);
    }
}
