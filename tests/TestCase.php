<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(Passport::keyPath('oauth-private.key')) || ! file_exists(Passport::keyPath('oauth-public.key'))) {
            $command = $this->artisan('passport:keys:ensure', ['--length' => 2048]);

            self::assertInstanceOf(PendingCommand::class, $command);
            $command->assertSuccessful();
        }
    }
}
