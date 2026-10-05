<?php

use Cmsmaxinc\FilamentSystemVersions\RuntimeVersionResolver;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $migration = include __DIR__ . '/../database/migrations/create_composer_versions_table.php.stub';
    $migration->up();
});

$composerOutput = json_encode([
    'installed' => [
        [
            'name' => 'vendor/package',
            'version' => '1.0.0',
            'latest' => '1.1.0',
            'latest-status' => 'semver-safe-update',
            'direct-dependency' => true,
            'description' => 'A package.',
            'abandoned' => false,
        ],
    ],
]);

it('runs composer from the application root, not the inherited working directory', function () use ($composerOutput) {
    // A queue worker's or web server's cwd is not necessarily the app root
    // (Laravel Cloud runs from `/`), and composer resolves composer.json
    // from cwd — the regression this pins was "No composer.json in current
    // directory" in production.
    Process::fake([
        '*' => Process::result($composerOutput),
    ]);

    $this->artisan('dependency:versions')->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process) => $process->path === base_path());
});

it('runs composer at normal verbosity even when the parent process was started quietly', function () use ($composerOutput) {
    // `artisan --quiet` exports SHELL_VERBOSITY=-1, which composer inherits and
    // obeys by printing nothing while still exiting 0. That surfaced nightly in
    // production as "Composer produced no output to parse."
    Process::fake([
        '*' => Process::result($composerOutput),
    ]);

    $this->artisan('dependency:versions')->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process) => ($process->environment['SHELL_VERBOSITY'] ?? null) === '0');
});

it('stores the reported packages', function () use ($composerOutput) {
    Process::fake([
        '*' => Process::result($composerOutput),
    ]);

    $this->artisan('dependency:versions')->assertSuccessful();

    expect(DB::table('composer_versions')->get())
        ->toHaveCount(1)
        ->sequence(fn ($row) => $row
            ->name->toBe('vendor/package')
            ->current_version->toBe('1.0.0')
            ->latest_version->toBe('1.1.0')
            ->status->toBe('semver-safe-update'));
});

it('refreshes cached runtime versions after a successful dependency snapshot', function () use ($composerOutput) {
    Process::fake([
        '*show --latest*' => Process::result($composerOutput),
        '*composer*' => Process::result('Composer version 2.8.11 2025-01-01 00:00:00'),
        '*node*' => Process::result('v22.14.0'),
        '*npm*' => Process::result('11.1.0'),
    ]);

    $this->artisan('dependency:versions')->assertSuccessful();

    expect(Cache::get(RuntimeVersionResolver::CACHE_KEY))->toBe([
        'composer' => '2.8.11',
        'node' => '22.14.0',
        'npm' => '11.1.0',
    ]);
});

it('uses a configured Composer executable even when no PHP path is configured', function () use ($composerOutput) {
    config()->set('filament-system-versions.paths.composer_path', '/opt/tools/composer');
    config()->set('filament-system-versions.paths.php_path', '');
    Process::fake([
        '*' => Process::result($composerOutput),
    ]);

    $this->artisan('dependency:versions')->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        '/opt/tools/composer',
        'show',
        '--latest',
        '--format=json',
    ]);
});

it('retries once when composer returns unparseable output, then succeeds', function () use ($composerOutput) {
    // `composer show --latest` hits Packagist and any private repositories, so a
    // momentary blip can exit 0 with unusable output. That should self-heal
    // rather than raise an alert.
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result('not json at all'))
            ->push(Process::result($composerOutput)),
    ]);

    $this->artisan('dependency:versions')->assertSuccessful();

    expect(DB::table('composer_versions')->get())->toHaveCount(1);
});

it('fails only after the retry also returns unparseable output', function () {
    Process::fake([
        '*' => Process::result('not json at all'),
    ]);

    $this->artisan('dependency:versions')->assertFailed();

    // Twice, not once: the retry is what keeps a single blip from alerting.
    Process::assertRanTimes(fn (PendingProcess $process) => $process->path === base_path(), 2);

    expect(DB::table('composer_versions')->get())->toBeEmpty();
});

it('reports empty composer output as such rather than as a JSON syntax error', function () {
    // Exit code 0 with nothing on stdout used to surface as a bare
    // "JSON decode failed: Syntax error", which points at the wrong problem.
    Log::spy();

    Process::fake([
        '*' => Process::result(''),
    ]);

    $this->artisan('dependency:versions')->assertFailed();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (...$args) => str_contains($args[0], 'Composer produced no output'));
});

it('keeps the previous snapshot when composer output cannot be parsed', function () {
    DB::table('composer_versions')->insert([
        'name' => 'vendor/previous',
        'current_version' => '1.0.0',
        'latest_version' => '1.0.0',
        'status' => 'up-to-date',
        'direct_dependency' => true,
        'description' => 'Kept from the last good run.',
        'abandoned' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Process::fake([
        '*' => Process::result('not json at all'),
    ]);

    $this->artisan('dependency:versions')->assertFailed();

    expect(DB::table('composer_versions')->get())
        ->toHaveCount(1)
        ->sequence(fn ($row) => $row->name->toBe('vendor/previous'));
});

it('throws when composer itself fails', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'Could not authenticate', exitCode: 1),
    ]);

    $this->artisan('dependency:versions');
})->throws(RuntimeException::class, 'Composer outdated failed: Could not authenticate');
