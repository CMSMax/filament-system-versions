<?php

use Cmsmaxinc\FilamentSystemVersions\Filament\Widgets\DependencyWidget;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Livewire\Livewire;

class RenderableDependencyInventoryWidget extends DependencyWidget
{
    public function getErrorBag(): MessageBag
    {
        return new MessageBag;
    }
}

beforeEach(function () {
    $migration = include __DIR__ . '/../database/migrations/create_composer_versions_table.php.stub';
    $migration->up();

    $this->composerLock = tempnam(sys_get_temp_dir(), 'fsv-composer-');
    config()->set('filament-system-versions.inventory.composer_lock', $this->composerLock);
    Filament::setCurrentPanel(Panel::make()->id('test'));
});

afterEach(function () {
    if (is_string($this->composerLock) && is_file($this->composerLock)) {
        unlink($this->composerLock);
    }
});

it('includes up-to-date packages and organizes every Composer dependency', function () {
    file_put_contents($this->composerLock, json_encode([
        'packages' => [
            ['name' => 'vendor/direct-current', 'version' => '1.0.0'],
        ],
        'packages-dev' => [
            ['name' => 'vendor/transitive-update', 'version' => '2.0.0'],
        ],
    ], JSON_THROW_ON_ERROR));

    DB::table('composer_versions')->insert([
        [
            'name' => 'vendor/direct-current',
            'current_version' => '1.0.0',
            'latest_version' => '1.0.0',
            'status' => 'up-to-date',
            'description' => null,
            'direct_dependency' => true,
            'abandoned' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'vendor/transitive-update',
            'current_version' => '2.0.0',
            'latest_version' => '3.0.0',
            'status' => 'update-possible',
            'description' => null,
            'direct_dependency' => false,
            'abandoned' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $widget = new DependencyWidget;
    $widget->filter = DependencyWidget::FILTER_ALL;

    $method = new ReflectionMethod(DependencyWidget::class, 'getViewData');
    $data = $method->invoke($widget);

    expect($data['dependencies'])->toHaveCount(2)
        ->and($data['total'])->toBe(2)
        ->and($data['updates'])->toBe(1)
        ->and($data['abandoned'])->toBe(1)
        ->and($data['groups']->pluck('key')->all())->toBe([
            'direct-runtime',
            'transitive-development',
        ]);

    Livewire::test(RenderableDependencyInventoryWidget::class)
        ->call('setFilter', DependencyWidget::FILTER_ALL)
        ->assertSee('Composer packages')
        ->assertSee('vendor/direct-current')
        ->assertSee('Up to date')
        ->assertSee('Transitive development packages');
});

function seedFilterablePackages(): void
{
    $row = fn (string $name, string $status, bool $abandoned): array => [
        'name' => $name,
        'current_version' => '1.0.0',
        'latest_version' => $status === 'up-to-date' ? '1.0.0' : '2.0.0',
        'status' => $status,
        'description' => null,
        'direct_dependency' => true,
        'abandoned' => $abandoned,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('composer_versions')->insert([
        $row('vendor/current', 'up-to-date', false),
        $row('vendor/outdated', 'semver-safe-update', false),
        $row('vendor/retired', 'up-to-date', true),
    ]);
}

it('shows only packages with an update by default', function () {
    seedFilterablePackages();

    Livewire::test(RenderableDependencyInventoryWidget::class)
        ->assertSet('filter', DependencyWidget::FILTER_UPDATES)
        ->assertSee('vendor/outdated')
        ->assertDontSee('vendor/current')
        ->assertDontSee('vendor/retired');
});

it('filters the list when a summary count is clicked', function () {
    seedFilterablePackages();

    Livewire::test(RenderableDependencyInventoryWidget::class)
        ->call('setFilter', DependencyWidget::FILTER_ABANDONED)
        ->assertSee('vendor/retired')
        ->assertDontSee('vendor/outdated')
        ->call('setFilter', DependencyWidget::FILTER_ALL)
        ->assertSee('vendor/current')
        ->assertSee('vendor/outdated')
        ->assertSee('vendor/retired');
});

it('keeps the summary counts for every package while a filter is active', function () {
    seedFilterablePackages();

    Livewire::test(RenderableDependencyInventoryWidget::class)
        ->assertSeeHtml('<strong>3</strong>')
        ->assertSeeHtml('<strong>1</strong>');
});

it('says so when no package matches the filter', function () {
    DB::table('composer_versions')->insert([
        'name' => 'vendor/current',
        'current_version' => '1.0.0',
        'latest_version' => '1.0.0',
        'status' => 'up-to-date',
        'description' => null,
        'direct_dependency' => true,
        'abandoned' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test(RenderableDependencyInventoryWidget::class)
        ->assertSee('No packages match this filter.')
        ->assertDontSee('vendor/current');
});

it('ignores an unknown filter value', function () {
    seedFilterablePackages();

    Livewire::test(RenderableDependencyInventoryWidget::class)
        ->call('setFilter', 'everything')
        ->assertSet('filter', DependencyWidget::FILTER_UPDATES)
        ->set('filter', 'everything')
        ->assertSee('vendor/outdated')
        ->assertDontSee('vendor/current');
});

it('describes the list for the selected filter', function () {
    seedFilterablePackages();

    Livewire::test(RenderableDependencyInventoryWidget::class)
        ->assertSee('Composer packages with an update')
        ->call('setFilter', DependencyWidget::FILTER_ABANDONED)
        ->assertSee('Abandoned Composer packages')
        ->call('setFilter', DependencyWidget::FILTER_ALL)
        ->assertSee('Every installed Composer package')
        ->assertDontSee('Composer packages with an update');
});
