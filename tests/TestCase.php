<?php

namespace CornellCustomDev\LaravelLdap\Tests;

use CornellCustomDev\LaravelLdap\LaravelLdapServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // additional setup
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelLdapServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // perform environment setup
    }
}
