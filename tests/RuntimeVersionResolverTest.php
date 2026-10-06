<?php

use Cmsmaxinc\FilamentSystemVersions\RuntimeVersionResolver;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    Cache::flush();
    config()->set('filament-system-versions.paths.php_path', '');
    config()->set('filament-system-versions.paths.composer_path', '');
    config()->set('filament-system-versions.paths.node_path', '');
    config()->set('filament-system-versions.paths.npm_path', '');
});

it('collects Composer Node and npm versions from the configured project root', function () {
    Process::fake([
        '*composer*' => Process::result('Composer version 2.8.11 2025-01-01 00:00:00'),
        '*node*' => Process::result("warning from host\nv22.14.0"),
        '*npm*' => Process::result('11.1.0'),
    ]);

    $resolver = app(RuntimeVersionResolver::class);

    expect($resolver->refresh())->toBe([
        'composer' => '2.8.11',
        'node' => '22.14.0',
        'npm' => '11.1.0',
    ])->and($resolver->versions())->toBe([
        'composer' => '2.8.11',
        'node' => '22.14.0',
        'npm' => '11.1.0',
    ]);

    Process::assertRanTimes(fn (PendingProcess $process): bool => $process->path === base_path(), 3);
});

it('runs version checks at normal verbosity so a quiet parent process cannot blank them', function () {
    Process::fake([
        '*composer*' => Process::result('Composer version 2.8.11 2025-01-01 00:00:00'),
        '*node*' => Process::result('v22.14.0'),
        '*npm*' => Process::result('11.1.0'),
    ]);

    app(RuntimeVersionResolver::class)->refresh();

    Process::assertRanTimes(fn (PendingProcess $process): bool => ($process->environment['SHELL_VERBOSITY'] ?? null) === '0', 3);
});

it('stores unavailable values instead of failing when binaries cannot run', function () {
    Process::fake([
        '*' => Process::result(errorOutput: 'not found', exitCode: 1),
    ]);

    $resolver = app(RuntimeVersionResolver::class);

    expect($resolver->refresh())->toBe([
        'composer' => null,
        'node' => null,
        'npm' => null,
    ])->and($resolver->versions())->toBe([
        'composer' => null,
        'node' => null,
        'npm' => null,
    ]);
});

it('never spawns processes while reading versions for a page request', function () {
    Cache::forever(RuntimeVersionResolver::CACHE_KEY, [
        'composer' => '2.8.11',
        'node' => '22.14.0',
        'npm' => '11.1.0',
    ]);
    Process::fake();

    app(RuntimeVersionResolver::class)->versions();
    app(RuntimeVersionResolver::class)->versions();

    Process::assertNothingRan();
});
