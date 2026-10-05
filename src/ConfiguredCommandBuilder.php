<?php

namespace Cmsmaxinc\FilamentSystemVersions;

class ConfiguredCommandBuilder
{
    /**
     * Environment for every tool this package shells out to.
     *
     * Symfony Console records `--quiet` by exporting SHELL_VERBOSITY=-1, and
     * child processes inherit it. Composer is a Symfony Console app, so when
     * the parent artisan process was started quietly (as some schedulers do)
     * composer silently prints nothing and still exits 0. Pinning it back to
     * normal verbosity keeps the output we parse.
     *
     * @var array<string, string>
     */
    public const ENVIRONMENT = ['SHELL_VERBOSITY' => '0'];

    /**
     * @param  array<int, string>  $arguments
     * @return string|array<int, string>
     */
    public function composer(array $arguments): string | array
    {
        $composer = (string) config('filament-system-versions.paths.composer_path');
        $php = (string) config('filament-system-versions.paths.php_path');

        if ($composer === '') {
            return $this->defaultCommand('composer', $arguments);
        }

        if ($php !== '' || str_ends_with(strtolower($composer), '.phar')) {
            return [$php !== '' ? $php : PHP_BINARY, $composer, ...$arguments];
        }

        return [$composer, ...$arguments];
    }

    /**
     * @param  array<int, string>  $arguments
     * @return string|array<int, string>
     */
    public function node(array $arguments): string | array
    {
        return $this->binary('node_path', 'node', $arguments);
    }

    /**
     * @param  array<int, string>  $arguments
     * @return string|array<int, string>
     */
    public function npm(array $arguments): string | array
    {
        return $this->binary('npm_path', 'npm', $arguments);
    }

    /**
     * @param  array<int, string>  $arguments
     * @return string|array<int, string>
     */
    private function binary(string $configKey, string $default, array $arguments): string | array
    {
        $configured = (string) config("filament-system-versions.paths.{$configKey}");

        return $configured !== ''
            ? [$configured, ...$arguments]
            : $this->defaultCommand($default, $arguments);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function defaultCommand(string $binary, array $arguments): string
    {
        return implode(' ', [$binary, ...$arguments]);
    }
}
