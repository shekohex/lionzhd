<?php

declare(strict_types=1);

use App\Listeners\PreventDestructiveScoutCommands;
use Illuminate\Console\Events\CommandStarting;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

it('blocks destructive Scout commands in production', function (string $command): void {
    app()->instance('env', 'production');
    $listener = app(PreventDestructiveScoutCommands::class);

    expect(fn () => $listener->handle(new CommandStarting($command, new ArrayInput([]), new NullOutput)))
        ->toThrow(RuntimeException::class, 'disabled in production');
})->with([
    'scout:delete-all-indexes',
    'scout:delete-index',
    'scout:flush',
]);

it('allows safe Scout commands in production', function (): void {
    app()->instance('env', 'production');
    $listener = app(PreventDestructiveScoutCommands::class);

    $listener->handle(new CommandStarting('scout:import', new ArrayInput([]), new NullOutput));

    expect(true)->toBeTrue();
});
