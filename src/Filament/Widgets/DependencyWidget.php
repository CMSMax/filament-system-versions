<?php

namespace Cmsmaxinc\FilamentSystemVersions\Filament\Widgets;

use Cmsmaxinc\FilamentSystemVersions\Filament\Pages\SystemVersions;
use Cmsmaxinc\FilamentSystemVersions\ProjectDependencyInventory;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;

class DependencyWidget extends Widget
{
    public const FILTER_ALL = 'all';

    public const FILTER_UPDATES = 'updates';

    public const FILTER_ABANDONED = 'abandoned';

    public const FILTERS = [self::FILTER_ALL, self::FILTER_UPDATES, self::FILTER_ABANDONED];

    protected string $view = 'filament-system-versions::filament.widgets.dependency';

    /**
     * Which packages the list shows. Defaults to the ones with an update, since
     * that is what someone opening this page usually wants to act on.
     */
    public string $filter = self::FILTER_UPDATES;

    public function getCardHeading(): string
    {
        return __('filament-system-versions::system-versions.widgets.dependency.heading');
    }

    /**
     * Describes the list for the filter that is currently selected.
     */
    public function getDescription(): string
    {
        return __("filament-system-versions::system-versions.widgets.dependency.description.{$this->getActiveFilter()}");
    }

    public function setFilter(string $filter): void
    {
        if (in_array($filter, self::FILTERS, true)) {
            $this->filter = $filter;
        }
    }

    /**
     * The property is writable from the browser, so anything unexpected falls back to the default.
     */
    protected function getActiveFilter(): string
    {
        return in_array($this->filter, self::FILTERS, true) ? $this->filter : self::FILTER_UPDATES;
    }

    #[On(SystemVersions::DEPENDENCY_VERSIONS_REFRESHED_EVENT)]
    public function refreshDependencyVersions(): void {}

    protected function getViewData(): array
    {
        $table = config('filament-system-versions.database.table_name', 'composer_versions');

        $missingTable = ! Schema::hasTable($table);
        $hasData = ! $missingTable && DB::table($table)->exists();

        $dependencies = collect();

        if ($hasData) {
            $scopes = app(ProjectDependencyInventory::class)->composerScopes();

            $dependencies = DB::table($table)
                ->orderBy('name')
                ->get()
                ->map(function ($dependency) use ($scopes) {
                    // Composer's latest-status: "update-possible" means the constraint blocks a
                    // (usually major) update, "semver-safe-update" is a compatible upgrade.
                    $dependency->badge_color = match ($dependency->status) {
                        'up-to-date' => 'success',
                        'update-possible' => 'danger',
                        default => 'warning',
                    };
                    $dependency->scope = $scopes[$dependency->name] ?? 'unknown';
                    $dependency->status_label = __("filament-system-versions::system-versions.statuses.{$dependency->status}");

                    return $dependency;
                });
        }

        $filter = $this->getActiveFilter();

        $visibleDependencies = match ($filter) {
            self::FILTER_UPDATES => $dependencies->where('status', '!=', 'up-to-date'),
            self::FILTER_ABANDONED => $dependencies->where('abandoned', true),
            default => $dependencies,
        };

        $groups = collect([
            ['key' => 'direct-runtime', 'direct' => true, 'scope' => 'runtime', 'open' => true],
            ['key' => 'direct-development', 'direct' => true, 'scope' => 'development', 'open' => true],
            ['key' => 'transitive-runtime', 'direct' => false, 'scope' => 'runtime', 'open' => false],
            ['key' => 'transitive-development', 'direct' => false, 'scope' => 'development', 'open' => false],
            ['key' => 'unclassified', 'direct' => null, 'scope' => 'unknown', 'open' => false],
        ])->map(function (array $group) use ($visibleDependencies, $filter): array {
            $items = $visibleDependencies->where('scope', $group['scope']);

            if ($group['direct'] !== null) {
                $items = $items->filter(fn ($dependency): bool => (bool) $dependency->direct_dependency === $group['direct']);
            }

            // A filtered list is short, so every group with a match starts expanded.
            $group['open'] = $group['open'] || $filter !== self::FILTER_ALL;
            $group['label'] = __("filament-system-versions::system-versions.groups.{$group['key']}");
            $group['dependencies'] = $items->values();

            return $group;
        })->filter(fn (array $group): bool => $group['dependencies']->isNotEmpty())->values();

        return [
            'dependencies' => $dependencies,
            'groups' => $groups,
            'total' => $dependencies->count(),
            'updates' => $dependencies->where('status', '!=', 'up-to-date')->count(),
            'abandoned' => $dependencies->where('abandoned', true)->count(),
            'filter' => $filter,
            'missingTable' => $missingTable,
            'hasData' => $hasData,
            'heading' => $this->getCardHeading(),
            'description' => $this->getDescription(),
        ];
    }
}
