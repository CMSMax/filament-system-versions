<?php

namespace Cmsmaxinc\FilamentSystemVersions\Commands;

use Cmsmaxinc\FilamentSystemVersions\ConfiguredCommandBuilder;
use Cmsmaxinc\FilamentSystemVersions\RuntimeVersionResolver;
use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;
use Throwable;
use TypeError;

class CheckDependencyVersions extends Command
{
    public $signature = 'dependency:versions';

    public $description = 'Check the versions of all dependencies.';

    /**
     * How much of composer's output to keep in log context, so a failure is
     * diagnosable without writing a megabyte of JSON into the log.
     */
    private const OUTPUT_SAMPLE_LENGTH = 1000;

    public function handle(RuntimeVersionResolver $runtimeVersions): int
    {
        $result = $this->runComposerShow();

        [$results, $failure, $exception] = $this->decodeComposerOutput($result);

        // `composer show --latest` reaches out to Packagist and to any private
        // repositories the app uses, so a momentary network or auth blip can
        // produce exit code 0 with unusable output. Retry once before treating
        // that as a real problem: otherwise a single blip raises an alert for a
        // nightly command that would have healed itself on the next run.
        if ($results === null) {
            logger()->warning(
                'Composer output could not be parsed, retrying once. ' . $failure,
                $this->failureContext($result)
            );

            $result = $this->runComposerShow();

            [$results, $failure, $exception] = $this->decodeComposerOutput($result);
        }

        if ($results === null) {
            logger()->error($failure, $this->failureContext($result) + ['exception' => $exception]);

            // Only report once the retry has also failed, so an alert means
            // "this is persistently broken" rather than "the network hiccuped".
            report(new \Exception($failure . ' See logs for details.', 0, $exception));

            $this->error('Failed to parse composer output. Check logs for details.');

            return self::FAILURE;
        }

        // Validate that we have the expected structure
        if (! isset($results->installed) || ! is_array($results->installed)) {
            $this->error('Invalid composer output structure. Expected "installed" array.');

            return self::FAILURE;
        }

        $table = config('filament-system-versions.database.table_name', 'composer_versions');
        $now = now();
        $rows = [];

        foreach ($results->installed as $package) {
            $latest = $package->latest ?? $package->version;

            if ($package->version != $latest) {
                $this->info("{$package->name} is outdated. Current version: {$package->version}. Latest version: {$latest}");
            }

            // "abandoned" is false, or the name of a suggested replacement package
            $abandoned = $package->abandoned ?? false;

            $rows[] = [
                'name' => $package->name,
                'current_version' => $package->version,
                'latest_version' => $latest,
                'status' => $package->{'latest-status'} ?? 'up-to-date',
                'direct_dependency' => $package->{'direct-dependency'} ?? false,
                'description' => $package->description ?? null,
                'abandoned' => is_bool($abandoned) ? $abandoned : true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Replace the snapshot atomically so the widgets never observe a half-written table
        DB::transaction(function () use ($table, $rows) {
            DB::table($table)->delete();

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        });

        $runtimeVersions->refresh();

        return self::SUCCESS;
    }

    /**
     * Run `composer show --latest --format=json` against the application root.
     */
    private function runComposerShow(): ProcessResult
    {
        // Composer resolves composer.json from the working directory, and a
        // queue worker's or web server's cwd is not necessarily the app root
        // (Laravel Cloud runs from `/` while the app lives in /var/www/html),
        // so the process is pinned to base_path() explicitly.
        $process = Process::path(base_path())->env(ConfiguredCommandBuilder::ENVIRONMENT);

        $result = $process->run(
            app(ConfiguredCommandBuilder::class)->composer(['show', '--latest', '--format=json'])
        );

        if ($result->failed()) {
            throw new RuntimeException('Composer outdated failed: ' . $result->errorOutput());
        }

        return $result;
    }

    /**
     * Decode composer's JSON output.
     *
     * @return array{0: ?object, 1: ?string, 2: ?Throwable} the decoded payload, or null
     *                                                      plus a description of why it could not be decoded
     */
    private function decodeComposerOutput(ProcessResult $result): array
    {
        $output = $this->cleanJsonOutput($result->output());

        // Composer exiting 0 with nothing on stdout decodes as a bare "Syntax
        // error", which reads as malformed JSON and sends you looking for the
        // wrong thing. Name it for what it actually is instead.
        if ($output === '') {
            return [null, 'Composer produced no output to parse.', null];
        }

        try {
            return [json_decode($output, flags: JSON_THROW_ON_ERROR), null, null];
        } catch (JsonException | TypeError $e) {
            return [null, 'JSON decode failed: ' . $e->getMessage(), $e];
        }
    }

    /**
     * Context describing a failed composer run.
     *
     * Includes stderr: composer writes warnings and network/auth errors there,
     * so when stdout is empty or truncated it is the only thing that explains
     * why — and it was the piece missing when this last failed in production.
     *
     * @return array<string, mixed>
     */
    private function failureContext(ProcessResult $result): array
    {
        $output = $this->cleanJsonOutput($result->output());

        return [
            'output_sample' => $this->sample($output),
            'output_length' => strlen($output),
            'original_output_length' => strlen($result->output()),
            'error_output' => $this->sample($result->errorOutput()),
        ];
    }

    private function sample(string $value): string
    {
        return substr($value, 0, self::OUTPUT_SAMPLE_LENGTH)
            . (strlen($value) > self::OUTPUT_SAMPLE_LENGTH ? '...(truncated)' : '');
    }

    /**
     * Clean the composer output to ensure valid JSON
     */
    private function cleanJsonOutput(string $output): string
    {
        // Remove BOM if present
        $output = preg_replace('/^\xEF\xBB\xBF/', '', $output);

        // Remove any non-JSON content before the opening brace
        $jsonStart = strpos($output, '{');
        if ($jsonStart !== false && $jsonStart > 0) {
            $output = substr($output, $jsonStart);
        }

        // Remove any trailing non-JSON content after the last closing brace
        $jsonEnd = strrpos($output, '}');
        if ($jsonEnd !== false) {
            $output = substr($output, 0, $jsonEnd + 1);
        }

        // Trim whitespace
        return trim($output);
    }
}
