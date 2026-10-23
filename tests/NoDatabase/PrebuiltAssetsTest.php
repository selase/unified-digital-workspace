<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->assetRoot = sys_get_temp_dir().'/prebuilt-assets-'.bin2hex(random_bytes(8));
    mkdir($this->assetRoot.'/public/build/assets', 0777, true);
    mkdir($this->assetRoot.'/resources', 0777, true);
    file_put_contents($this->assetRoot.'/resources/app.js', 'source');
    file_put_contents($this->assetRoot.'/package.json', '{}');
    file_put_contents($this->assetRoot.'/.gitignore', '/vendor/');
    mkdir($this->assetRoot.'/vendor/laravel/framework/src/Illuminate/Pagination/resources/views', 0777, true);
    file_put_contents($this->assetRoot.'/vendor/laravel/framework/src/Illuminate/Pagination/resources/views/tailwind.blade.php', '<nav>pagination</nav>');
    file_put_contents($this->assetRoot.'/public/build/assets/app.js', 'compiled');
    file_put_contents($this->assetRoot.'/public/build/manifest.json', json_encode([
        'resources/app.js' => ['file' => 'assets/app.js', 'isEntry' => true],
    ], JSON_THROW_ON_ERROR));
    (new Process(['git', 'init', '-q', $this->assetRoot]))->mustRun();
    (new Process(['git', 'add', '.'], $this->assetRoot))->mustRun();
    $this->assets = function (string ...$arguments): Process {
        $process = new Process([PHP_BINARY, '-d', 'memory_limit=32M', __DIR__.'/../../scripts/prebuilt-assets.php', ...$arguments], $this->assetRoot);
        $process->run();

        return $process;
    };
});

afterEach(function (): void {
    (new Filesystem)->remove($this->assetRoot);
});

it('generates deterministic metadata and validates without application dependencies', function (): void {
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    $metadata = file_get_contents($this->assetRoot.'/public/build/build-metadata.json');
    expect(($this->assets)()->getExitCode())->toBe(0);
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    expect(file_get_contents($this->assetRoot.'/public/build/build-metadata.json'))->toBe($metadata);
});

it('rejects stale missing or tampered build inputs', function (string $mutation): void {
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    $root = $this->assetRoot;
    match ($mutation) {
        'source' => file_put_contents($root.'/resources/app.js', 'changed'),
        'new source' => file_put_contents($root.'/resources/new.js', 'new'),
        'deleted source' => unlink($root.'/resources/app.js'),
        'config' => file_put_contents($root.'/package.json', '{"changed":true}'),
        'asset' => file_put_contents($root.'/public/build/assets/app.js', 'tampered'),
        'missing asset' => unlink($root.'/public/build/assets/app.js'),
        'manifest' => file_put_contents($root.'/public/build/manifest.json', '{}'),
        'missing manifest' => unlink($root.'/public/build/manifest.json'),
        'metadata' => file_put_contents($root.'/public/build/build-metadata.json', '{}'),
        'missing metadata' => unlink($root.'/public/build/build-metadata.json'),
        'malformed metadata' => file_put_contents($root.'/public/build/build-metadata.json', '{'),
        'metadata traversal' => (function () use ($root): void {
            $path = $root.'/public/build/build-metadata.json';
            $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $data['files']['../package.json'] = hash_file('sha256', $root.'/package.json');
            file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        })(),
        'extra asset' => file_put_contents($root.'/public/build/assets/extra.js', 'extra'),
        'symlink' => (function () use ($root): void {
            unlink($root.'/public/build/assets/app.js');
            symlink($root.'/resources/app.js', $root.'/public/build/assets/app.js');
        })(),
    };
    expect(($this->assets)()->getExitCode())->toBe(1);
})->with(['source', 'new source', 'deleted source', 'config', 'asset', 'missing asset', 'manifest', 'missing manifest', 'metadata', 'missing metadata', 'malformed metadata', 'metadata traversal', 'extra asset', 'symlink']);

it('refuses to certify unsafe or unresolved manifest references', function (array $entry): void {
    file_put_contents($this->assetRoot.'/public/build/manifest.json', json_encode(['entry' => $entry], JSON_THROW_ON_ERROR));
    expect(($this->assets)('--write')->getExitCode())->toBe(1);
    expect(is_file($this->assetRoot.'/public/build/build-metadata.json'))->toBeFalse();
})->with([
    'traversal' => [['file' => '../package.json']],
    'absolute' => [['file' => '/etc/passwd']],
    'missing css' => [['file' => 'assets/app.js', 'css' => ['assets/missing.css']]],
    'missing import' => [['file' => 'assets/app.js', 'imports' => ['missing']]],
    'missing dynamic import' => [['file' => 'assets/app.js', 'dynamicImports' => ['missing']]],
    'missing asset reference' => [['file' => 'assets/app.js', 'assets' => ['missing.svg']]],
]);

it('invalidates previous certification before starting another build', function (): void {
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    expect(($this->assets)('--invalidate')->getExitCode())->toBe(0);
    expect(($this->assets)()->getExitCode())->toBe(1);
});

it('accepts Forge shared runtime directories after tracked placeholders disappear', function (string $runtime): void {
    $directory = $this->assetRoot.'/'.$runtime;
    mkdir($directory, 0777, true);
    file_put_contents($directory.'/.gitignore', "*\n!.gitignore\n");
    (new Process(['git', 'add', $runtime.'/.gitignore'], $this->assetRoot))->mustRun();
    (new Process(['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-qm', 'Fixture'], $this->assetRoot))->mustRun();
    expect(($this->assets)('--write')->getExitCode())->toBe(0);

    $shared = $this->assetRoot.'/../'.basename($this->assetRoot).'-shared';
    mkdir($shared);
    try {
        (new Filesystem)->remove($directory);
        symlink($shared, $directory);
        file_put_contents($shared.'/runtime.php', '<?php return [];');
        $status = (new Process(['git', 'status', '--porcelain'], $this->assetRoot))->mustRun()->getOutput();
        expect($status)->toContain(' D '.$runtime.'/.gitignore');
        expect($status)->toContain('?? '.$runtime);
        expect(($this->assets)()->getExitCode())->toBe(0);
        file_put_contents($shared.'/runtime.php', '<?php return [1];');
        expect(($this->assets)()->getExitCode())->toBe(0);
    } finally {
        unlink($directory);
        (new Filesystem)->remove($shared);
    }
})->with(['storage', 'bootstrap/cache']);

it('continues detecting real sources adjacent to runtime exclusions', function (string $path): void {
    mkdir(dirname($this->assetRoot.'/'.$path), 0777, true);
    file_put_contents($this->assetRoot.'/'.$path, 'original');
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    file_put_contents($this->assetRoot.'/'.$path, 'changed');
    expect(($this->assets)()->getExitCode())->toBe(1);
})->with(['storage-source/input.js', 'bootstrap/cache-source/input.js', 'bootstrap/app.php', 'resources/views/page.blade.php']);

it('rejects drift in ignored installed vendor pagination build inputs', function (string $mutation): void {
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    $directory = $this->assetRoot.'/vendor/laravel/framework/src/Illuminate/Pagination/resources/views';
    match ($mutation) {
        'changed' => file_put_contents($directory.'/tailwind.blade.php', '<nav>changed</nav>'),
        'removed' => unlink($directory.'/tailwind.blade.php'),
        'added' => file_put_contents($directory.'/simple.blade.php', '<nav>new</nav>'),
        'symlinked' => (function () use ($directory): void {
            unlink($directory.'/tailwind.blade.php');
            symlink($this->assetRoot.'/resources/app.js', $directory.'/tailwind.blade.php');
        })(),
    };
    expect(($this->assets)()->getExitCode())->toBe(1);
})->with(['changed', 'removed', 'added', 'symlinked']);

it('refuses certification without installed vendor pagination templates', function (): void {
    (new Filesystem)->remove($this->assetRoot.'/vendor');
    expect(($this->assets)('--write')->getExitCode())->toBe(1);
});

it('accepts Forge generated root Composer authentication without hashing credentials', function (): void {
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    file_put_contents($this->assetRoot.'/auth.json', '{"fixture":1}');
    expect(($this->assets)()->getExitCode())->toBe(0);
    file_put_contents($this->assetRoot.'/auth.json', '{"fixture":2}');
    expect(($this->assets)()->getExitCode())->toBe(0);
});

it('continues hashing nested files named auth.json', function (): void {
    file_put_contents($this->assetRoot.'/resources/auth.json', '{"source":1}');
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    file_put_contents($this->assetRoot.'/resources/auth.json', '{"source":2}');
    expect(($this->assets)()->getExitCode())->toBe(1);
});

it('preserves certification across index removals with proven ignored source membership drift', function (bool $ignored): void {
    if ($ignored) {
        file_put_contents($this->assetRoot.'/.gitignore', "/vendor/\n/resources/\n");
    }
    (new Process(['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-qm', 'Fixture'], $this->assetRoot))->mustRun();
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    $metadata = file_get_contents($this->assetRoot.'/public/build/build-metadata.json');
    $listing = ['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'];
    $before = explode("\0", (new Process($listing, $this->assetRoot))->mustRun()->getOutput());
    expect($before)->toContain('resources/app.js');

    (new Process(['git', 'rm', '--cached', '-r', 'resources'], $this->assetRoot))->mustRun();
    $status = (new Process(['git', 'status', '--porcelain', '--untracked-files=all'], $this->assetRoot))->mustRun()->getOutput();
    expect($status)->toContain('D  resources/app.js');
    expect(file_get_contents($this->assetRoot.'/resources/app.js'))->toBe('source');
    $after = explode("\0", (new Process($listing, $this->assetRoot))->mustRun()->getOutput());
    if ($ignored) {
        expect($after)->not->toContain('resources/app.js');
        expect($status)->not->toContain('?? resources/app.js');
        $ignoredPaths = (new Process(['git', 'ls-files', '-z', '--others', '--ignored', '--exclude-standard'], $this->assetRoot))->mustRun()->getOutput();
        expect(explode("\0", $ignoredPaths))->toContain('resources/app.js');
    } else {
        expect($status)->toContain('?? resources/app.js');
        expect($after)->toContain('resources/app.js');
    }
    $validation = ($this->assets)();
    expect($validation->getErrorOutput())->toBe('');
    expect($validation->getExitCode())->toBe(0);
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    expect(file_get_contents($this->assetRoot.'/public/build/build-metadata.json'))->toBe($metadata);
})->with(['untracked originals' => false, 'ignored originals' => true]);

it('rejects source tampering after committed sources are removed from the index', function (string $mutation): void {
    file_put_contents($this->assetRoot.'/.gitignore', "/vendor/\n/resources/\n");
    (new Process(['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-qm', 'Fixture'], $this->assetRoot))->mustRun();
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    (new Process(['git', 'rm', '--cached', '-r', 'resources'], $this->assetRoot))->mustRun();
    match ($mutation) {
        'changed' => file_put_contents($this->assetRoot.'/resources/app.js', 'changed'),
        'missing' => unlink($this->assetRoot.'/resources/app.js'),
        'symlinked' => (function (): void {
            unlink($this->assetRoot.'/resources/app.js');
            symlink($this->assetRoot.'/package.json', $this->assetRoot.'/resources/app.js');
        })(),
    };
    expect(($this->assets)()->getExitCode())->toBe(1);
    if ($mutation !== 'changed') {
        expect(($this->assets)('--write')->getExitCode())->toBe(1);
    }
})->with(['changed', 'missing', 'symlinked']);

it('hashes restored committed inputs from disk rather than trusting HEAD contents', function (): void {
    file_put_contents($this->assetRoot.'/.gitignore', "/vendor/\n/resources/\n");
    (new Process(['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-qm', 'Fixture'], $this->assetRoot))->mustRun();
    (new Process(['git', 'rm', '--cached', '-r', 'resources'], $this->assetRoot))->mustRun();
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    file_put_contents($this->assetRoot.'/resources/app.js', 'changed');
    expect(($this->assets)()->getExitCode())->toBe(1);
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    expect(($this->assets)()->getExitCode())->toBe(0);
    file_put_contents($this->assetRoot.'/resources/new.js', 'new');
    (new Process(['git', 'add', '-f', 'resources/new.js'], $this->assetRoot))->mustRun();
    expect(($this->assets)()->getExitCode())->toBe(1);
});

it('allows certification after a source deletion is committed', function (): void {
    (new Process(['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-qm', 'Fixture'], $this->assetRoot))->mustRun();
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    (new Process(['git', 'rm', 'resources/app.js'], $this->assetRoot))->mustRun();
    expect(($this->assets)('--write')->getExitCode())->toBe(1);
    (new Process(['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-qm', 'Remove source'], $this->assetRoot))->mustRun();
    expect(($this->assets)()->getExitCode())->toBe(1);
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    expect(($this->assets)()->getExitCode())->toBe(0);
});

it('reports exact source drift paths and counts without file contents', function (): void {
    file_put_contents($this->assetRoot.'/resources/removed.js', 'original');
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    file_put_contents($this->assetRoot.'/resources/app.js', 'PRIVATE_CHANGED_CONTENT');
    unlink($this->assetRoot.'/resources/removed.js');
    file_put_contents($this->assetRoot.'/.env.default', 'PRIVATE_GENERATED_CONTENT');
    $process = ($this->assets)();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain(
            'source changed (1): ["resources/app.js"]',
            'source missing (1): ["resources/removed.js"]',
            'source added (1): [".env.default"]',
        )->not->toContain('PRIVATE_CHANGED_CONTENT', 'PRIVATE_GENERATED_CONTENT');
});

it('writes only deterministic source path hashes and excludes private runtime inputs', function (): void {
    file_put_contents($this->assetRoot.'/.gitignore', "/vendor/\n.env\n");
    file_put_contents($this->assetRoot.'/.env', 'PRIVATE_ENVIRONMENT_CONTENT');
    file_put_contents($this->assetRoot.'/auth.json', 'PRIVATE_AUTH_CONTENT');
    mkdir($this->assetRoot.'/storage');
    file_put_contents($this->assetRoot.'/storage/runtime', 'PRIVATE_RUNTIME_CONTENT');
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    $json = file_get_contents($this->assetRoot.'/public/build/build-metadata.json');
    $metadata = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($metadata['version'])->toBe(2)
        ->and($metadata['source_files']['resources/app.js'])->toBe(hash('sha256', 'source'))
        ->and($metadata['source_files'])->not->toHaveKeys(['.env', 'auth.json', 'storage/runtime'])
        ->and($json)->not->toContain('PRIVATE_ENVIRONMENT_CONTENT', 'PRIVATE_AUTH_CONTENT', 'PRIVATE_RUNTIME_CONTENT');
});

it('keeps version one certifications strict and usable without per path diagnostics', function (): void {
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    $path = $this->assetRoot.'/public/build/build-metadata.json';
    $metadata = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $metadata['version'] = 1;
    unset($metadata['source_files']);
    file_put_contents($path, json_encode($metadata, JSON_THROW_ON_ERROR));
    expect(($this->assets)()->getExitCode())->toBe(0);
    file_put_contents($this->assetRoot.'/resources/app.js', 'changed');
    $process = ($this->assets)();
    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('version 1 metadata has no per-input hashes');
});

it('bounds diagnostic path listings while preserving full mismatch counts', function (): void {
    expect(($this->assets)('--write')->getExitCode())->toBe(0);
    for ($i = 0; $i < 70; $i++) {
        file_put_contents($this->assetRoot.'/resources/added-'.$i.'.js', 'new');
    }
    $process = ($this->assets)();
    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('source added (70):', '20 additional paths omitted');
});
