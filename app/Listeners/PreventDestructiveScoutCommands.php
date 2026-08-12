<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Application;
use RuntimeException;

final readonly class PreventDestructiveScoutCommands
{
    private const COMMANDS = [
        'scout:delete-all-indexes',
        'scout:delete-index',
        'scout:flush',
    ];

    public function __construct(private Application $application) {}

    public function handle(CommandStarting $event): void
    {
        if (! $this->application->isProduction() || ! in_array($event->command, self::COMMANDS, true)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Command [%s] is disabled in production. Use [lionz:reconcile-search --force].',
            $event->command,
        ));
    }
}
