<?php

declare(strict_types=1);

/**
 * Standalone deployment gate: no Composer, Laravel, Node or database bootstrap.
 * All tracked and nonignored project files are inputs, except build output
 * and Forge runtime storage/cache directories (which may be shared symlinks).
 * Installed vendor pagination Blade templates are explicit Tailwind inputs too.
 * File contents are streamed by hash_file; metadata is bounded to 8 MiB.
 */
final class PrebuiltAssets
{
    private const METADATA = 'build-metadata.json';

    public function __construct(private string $root) {}

    public function run(string $mode): void
    {
        $metadataPath = $this->root.'/public/build/'.self::METADATA;
        if ($mode === '--invalidate') {
            if (is_link($metadataPath) || is_file($metadataPath)) {
                if (! unlink($metadataPath)) {
                    throw new RuntimeException('Cannot invalidate build metadata.');
                }
            }

            return;
        }

        if (! in_array($mode, ['', '--write'], true)) {
            throw new RuntimeException('Usage: php scripts/prebuilt-assets.php [--write|--invalidate]');
        }

        $source = $this->sourceDigest();
        $files = $this->buildFiles();
        $this->validateManifest($files);
        $expected = ['version' => 1, 'source_sha256' => $source, 'files' => $files];

        if ($mode === '--write') {
            $temporary = $metadataPath.'.tmp';
            if (is_link($metadataPath) || is_link($temporary)) {
                throw new RuntimeException('Symlinked metadata is forbidden.');
            }
            $json = json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            if (file_put_contents($temporary, $json) !== mb_strlen($json) || ! rename($temporary, $metadataPath)) {
                throw new RuntimeException('Cannot write build metadata.');
            }
        } elseif ($this->readJson($metadataPath) !== $expected) {
            throw new RuntimeException('Prebuilt assets are stale or tampered; run npm run build locally and include public/build.');
        }
    }

    private function sourceDigest(): string
    {
        $head = mb_trim($this->gitOutput(['rev-parse', '--verify', '--quiet', 'HEAD'], true));
        $arguments = ['ls-files', '-z', '--cached', '--others', '--exclude-standard'];
        if ($head !== '') {
            // Retain committed paths when a deployment rewrites only the index.
            $arguments[] = '--with-tree='.$head;
        }
        $output = $this->gitOutput($arguments);
        $paths = array_unique(array_filter(explode("\0", $output), static function (string $path): bool {
            if ($path === '' || $path === 'auth.json') {
                return false;
            }
            foreach (['public/build', 'storage', 'bootstrap/cache'] as $excluded) {
                if ($path === $excluded || str_starts_with($path, $excluded.'/')) {
                    return false;
                }
            }

            return true;
        }));
        $paginationDirectory = 'vendor/laravel/framework/src/Illuminate/Pagination/resources/views';
        $paginationFiles = glob($this->root.'/'.$paginationDirectory.'/*.blade.php');
        if ($paginationFiles === false || $paginationFiles === []) {
            throw new RuntimeException('Missing vendor pagination templates; install Composer dependencies before validating or building.');
        }
        foreach ($paginationFiles as $file) {
            $paths[] = mb_substr($file, mb_strlen($this->root) + 1);
        }
        $paths = array_unique($paths);
        sort($paths, SORT_STRING);
        if (! in_array('package.json', $paths, true)) {
            throw new RuntimeException('Run from the project root containing tracked package.json.');
        }
        $hash = hash_init('sha256');
        foreach ($paths as $path) {
            $absolute = $this->safeFile($this->root, $path);
            hash_update($hash, $path."\0".$this->fileHash($absolute)."\0");
        }

        return hash_final($hash);
    }

    /** @param list<string> $arguments */
    private function gitOutput(array $arguments, bool $allowUnbornHead = false): string
    {
        $pipes = [];
        $process = proc_open(['git', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        if (! is_resource($process)) {
            throw new RuntimeException('Git is required to enumerate build inputs.');
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($output === false || $error === false || ($status !== 0 && ! ($allowUnbornHead && $status === 1 && $output === '' && $error === ''))) {
            throw new RuntimeException('Cannot enumerate build inputs with Git.');
        }

        return $output;
    }

    /** @return array<string, string> */
    private function buildFiles(): array
    {
        $directory = $this->root.'/public/build';
        if (! is_dir($directory) || is_link($directory) || is_link($this->root.'/public')) {
            throw new RuntimeException('Missing or symlinked build directory.');
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Symlinks are forbidden in build output.');
            }
            if ($file->isDir()) {
                continue;
            }
            $path = mb_substr($file->getPathname(), mb_strlen($directory) + 1);
            if ($path === self::METADATA) {
                continue;
            }
            $files[$path] = $this->fileHash($this->safeFile($directory, $path));
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /** @param array<string, string> $files */
    private function validateManifest(array $files): void
    {
        $manifest = $this->readJson($this->root.'/public/build/manifest.json');
        if ($manifest === []) {
            throw new RuntimeException('Empty Vite manifest.');
        }
        foreach ($manifest as $entry) {
            if (! is_array($entry) || ! isset($entry['file']) || ! is_string($entry['file'])) {
                throw new RuntimeException('Invalid Vite manifest entry.');
            }
            $references = [$entry['file']];
            foreach (['css', 'assets'] as $field) {
                if (isset($entry[$field]) && (! is_array($entry[$field]) || ! array_is_list($entry[$field]))) {
                    throw new RuntimeException('Invalid manifest asset list.');
                }
                $references = array_merge($references, $entry[$field] ?? []);
            }
            foreach ($references as $path) {
                if (! is_string($path)) {
                    throw new RuntimeException('Invalid manifest asset path.');
                }
                $this->safeFile($this->root.'/public/build', $path);
                if (! isset($files[$path])) {
                    throw new RuntimeException('Manifest references an uncertified asset.');
                }
            }
            foreach (['imports', 'dynamicImports'] as $field) {
                if (isset($entry[$field]) && (! is_array($entry[$field]) || ! array_is_list($entry[$field]))) {
                    throw new RuntimeException('Invalid manifest import list.');
                }
                foreach ($entry[$field] ?? [] as $key) {
                    if (! is_string($key) || ! isset($manifest[$key])) {
                        throw new RuntimeException('Manifest references a missing import.');
                    }
                }
            }
        }
    }

    private function safeFile(string $base, string $path): string
    {
        if ($path === '' || str_contains($path, '\\') || str_contains($path, "\0") || preg_match('~(^/|^[A-Za-z]:|(^|/)\.{1,2}(/|$)|//)~', $path)) {
            throw new RuntimeException('Unsafe file path.');
        }
        $current = $base;
        foreach (explode('/', $path) as $part) {
            $current .= '/'.$part;
            if (is_link($current)) {
                throw new RuntimeException('Symlinked inputs are forbidden.');
            }
        }
        if (! is_file($current) || ! is_readable($current)) {
            throw new RuntimeException('Missing or unreadable input: '.$path);
        }

        return $current;
    }

    private function fileHash(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('Cannot hash build input.');
        }

        return $hash;
    }

    /** @return array<mixed> */
    private function readJson(string $path): array
    {
        if (is_link($path) || ! is_file($path) || filesize($path) > 8 * 1024 * 1024) {
            throw new RuntimeException('Missing or oversized JSON: '.basename($path));
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unreadable JSON.');
        }
        $data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Invalid JSON object.');
        }

        return $data;
    }
}

try {
    if (count($argv ?? []) > 2) {
        throw new RuntimeException('Too many arguments.');
    }
    (new PrebuiltAssets(getcwd()))->run($argv[1] ?? '');
    fwrite(STDOUT, "Prebuilt assets: OK\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Prebuilt assets: '.$exception->getMessage()."\n");
    exit(1);
}
