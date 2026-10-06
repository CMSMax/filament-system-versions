<?php

return [
    'navigation' => [
        'label' => 'System Versions',
        'group' => 'Settings',
    ],
    'actions' => [
        'check_now' => [
            'label' => 'Check now',
            'modal_heading' => 'Check dependency versions now?',
            'modal_description' => 'This contacts the configured Composer repositories and refreshes the stored Composer snapshot. npm packages are read directly from the lockfile.',
            'modal_submit' => 'Check now',
            'success_title' => 'Dependency versions updated',
            'success_body' => 'The package information on this page is now current.',
            'already_running_title' => 'Check already running',
            'already_running_body' => 'Another dependency version check is in progress. Try again in a few minutes.',
            'failure_title' => 'Unable to check dependency versions',
            'failure_body' => 'The existing snapshot was kept. Review the application logs and try again.',
        ],
    ],
    'not_available' => 'Not available',
    'statuses' => [
        'up-to-date' => 'Up to date',
        'semver-safe-update' => 'Update available',
        'update-possible' => 'Major update available',
    ],
    'groups' => [
        'direct-runtime' => 'Direct runtime packages',
        'direct-development' => 'Direct development packages',
        'direct-optional' => 'Direct optional packages',
        'direct-peer' => 'Direct peer packages',
        'transitive-runtime' => 'Transitive runtime packages',
        'transitive-development' => 'Transitive development packages',
        'transitive-optional' => 'Transitive optional packages',
        'transitive-peer' => 'Transitive peer packages',
        'unclassified' => 'Unclassified packages',
    ],
    'widgets' => [
        'dependency' => [
            'heading' => 'Composer packages',
            'description' => 'Composer packages with an update, grouped by relationship and application scope. Select a count to filter the list, or select installed to see every package.',
            'abandoned' => 'Abandoned',
            'summary_label' => 'Composer package summary',
            'total' => 'installed',
            'updates' => 'updates',
            'to' => 'to',
            'no_matches' => 'No packages match this filter.',
            'no_data' => 'No dependency data yet. Run the dependency:versions Artisan command to collect it.',
            'missing_table' => 'The composer versions table has not been migrated yet. Publish and run this package\'s migrations first.',
            'table' => [
                'name' => 'Name',
                'version' => 'Version',
                'status' => 'Status',
            ],
        ],
        'npm' => [
            'heading' => 'npm packages',
            'description' => 'Every resolved package instance from package-lock.json, including nested versions',
            'unavailable' => [
                'missing' => 'No package-lock.json was found. Configure the lockfile path if this project keeps it elsewhere.',
                'invalid' => 'package-lock.json could not be read as a valid npm lockfile.',
                'unsupported' => 'This package-lock.json version is unsupported. Generate a lockfile with npm 7 or newer.',
            ],
            'summary_label' => 'npm package summary',
            'instances' => 'resolved instances',
            'unique_versions' => 'unique package versions',
            'lockfile' => 'lockfile',
            'table' => [
                'name' => 'Name and lockfile path',
                'version' => 'Exact version',
            ],
        ],
        'system' => [
            'heading' => 'System details',
            'description' => 'A summary of the system environment',
            'details' => [
                'environment' => 'Environment',
                'timezone' => 'Timezone',
                'debug' => 'Debug mode',
                'debug_enabled' => 'Enabled',
                'debug_disabled' => 'Disabled',
            ],
        ],
        'stats' => [
            'not_installed' => 'Not installed',
        ],
    ],
];
