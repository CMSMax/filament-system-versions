<?php

namespace Cmsmaxinc\FilamentSystemVersions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Throwable;

class RuntimeVersionResolver
{
    public const CACHE_KEY = 'filament-system-versions.runtime-versions';

    /**
     * Return the most recently collected runtime versions without spawning
     * processes during an HTTP request.
     *
     * @return array{composer: string|null, node: string|null, npm: string|null}
     */
    public function versions(): array
    {
        try {
            $versions = Cache::get(self::CACHE_KEY);
        } catch (Throwable) {
            return $this->emptyVersions();
        }

        if (! is_array($versions)) {
            return $this->emptyVersions();
        }

        return [
            'composer' => is_string($versions['composer'] ?? null) ? $versions['composer'] : null,
            'node' => is_string($versions['node'] ?? null) ? $versions['node'] : null,
            'npm' => is_string($versions['npm'] ?? null) ? $versions['npm'] : null,
        ];
    }

    /**
     * Collect and persist runtime versions from the dependency refresh command.
     *
     * @return array{composer: string|null, node: string|null, npm: string|null}
     */
    public function refresh(): array
    {
        $commands = app(ConfiguredCommandBuilder::class);
        $versions = [
            'composer' => $this->run(
                $commands->composer(['--version', '--no-ansi']),
                '/^Composer(?:\s+version)?\s+(\d+\.\d+(?:\.\d+)?(?:[-+][0-9A-Za-z.-]+)?)(?:\s|$)/mi',
            ),
            'node' => $this->run(
                $commands->node(['--version']),
                '/^v?(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)\s*$/mi',
            ),
            'npm' => $this->run(
                $commands->npm(['--version']),
                '/^v?(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)\s*$/mi',
            ),
        ];

        try {
            Cache::forever(self::CACHE_KEY, $versions);
        } catch (Throwable $exception) {
            logger()->warning('Runtime versions could not be cached.', [
                'exception' => $exception,
            ]);
        }

        return $versions;
    }

    /**
     * @param  string|array<int, string>  $command
     */
    private function run(string | array $command, string $pattern): ?string
    {
        try {
            $result = Process::path(base_path())
                ->env(ConfiguredCommandBuilder::ENVIRONMENT)
                ->timeout(5)
                ->run($command);
        } catch (Throwable) {
            return null;
        }

        if (! $result->successful()) {
            return null;
        }

        if (preg_match($pattern, trim($result->output()), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return array{composer: null, node: null, npm: null}
     */
    private function emptyVersions(): array
    {
        return [
            'composer' => null,
            'node' => null,
            'npm' => null,
        ];
    }
}
