<?php

declare(strict_types=1);

use Illuminate\Support\Str;

it('enforces metronic table classes across blade views', function (): void {
    $paths = collect([
        ...bladeFilesIn(resource_path('views')),
        ...bladeFilesIn(app_path('Modules')),
    ])->filter(fn (string $path): bool => ! Str::endsWith($path, 'resources/views/pdf/invoice.blade.php'));

    $nonCompliantTables = [];

    foreach ($paths as $path) {
        $content = file_get_contents($path);

        if ($content === false) {
            continue;
        }

        preg_match_all('/<table\s+class="([^"]+)"/', $content, $matches);

        foreach (($matches[1] ?? []) as $tableClasses) {
            if (str_contains($tableClasses, 'kt-table')
                && (! str_contains($tableClasses, 'table-auto') || ! str_contains($tableClasses, 'kt-table-border'))) {
                $nonCompliantTables[] = "{$path} => {$tableClasses}";
            }
        }
    }

    expect($nonCompliantTables)
        ->toBeEmpty('Non-compliant table classes found: '.implode(' | ', $nonCompliantTables));
});

it('keeps core index pages aligned with the team crew table container pattern', function (): void {
    $pages = [
        resource_path('views/admin/user-management/users/index.blade.php'),
        resource_path('views/admin/roles/index.blade.php'),
        resource_path('views/admin/tenants/index.blade.php'),
    ];

    foreach ($pages as $page) {
        $content = file_get_contents($page);

        expect($content)->not->toBeFalse();
        expect((string) $content)->toContain('kt-card kt-card-grid min-w-full');
        expect((string) $content)->toContain('kt-scrollable-x-auto');
    }
});

/**
 * @return array<int, string>
 */
function bladeFilesIn(string $directory): array
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    $paths = [];

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        if (Str::endsWith($path, '.blade.php')) {
            $paths[] = $path;
        }
    }

    return $paths;
}
