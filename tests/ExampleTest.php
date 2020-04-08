<?php

namespace Joaoprado\SimpleDebugLogger\Tests;

use Orchestra\Testbench\TestCase;
use Joaoprado\SimpleDebugLogger\SimpleDebugLoggerServiceProvider;

class ExampleTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [SimpleDebugLoggerServiceProvider::class];
    }
    
    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }
}
