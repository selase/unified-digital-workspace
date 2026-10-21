<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Spatie\Backup\Tasks\Backup\FileSelection;

it('includes shared application files outside a symlinked release without following unrelated symlinks', function (bool $shared): void {
    $original = Container::getInstance();
    $root = sys_get_temp_dir().'/udw-backup-'.bin2hex(random_bytes(8));
    $release = $root.'/release';
    $storage = $shared ? $root.'/shared/storage' : $release.'/storage';
    $fs = new Filesystem;
    $fs->makeDirectory($storage.'/app/public', 0700, true);
    $fs->makeDirectory($release.'/vendor', 0700, true);
    $fs->makeDirectory($release.'/.git', 0700, true);
    $fs->makeDirectory($storage.'/app/backup-temp/stale', 0700, true);
    $fs->makeDirectory($root.'/unrelated', 0700, true);
    file_put_contents($release.'/source.php', '<?php');
    file_put_contents($release.'/vendor/excluded.php', 'not backed up');
    file_put_contents($release.'/.git/config', 'repository metadata');
    file_put_contents($storage.'/app/backup-temp/stale/archive.zip', 'old temporary backup');
    file_put_contents($storage.'/app/public/upload.txt', 'restorable customer upload');
    file_put_contents($root.'/unrelated/private.txt', 'another application');
    symlink($root.'/unrelated', $release.'/unrelated-link');
    if ($shared) {
        symlink($storage, $release.'/storage');
    }
    $configPath = dirname(__DIR__, 2).'/config/backup.php';
    try {
        $app = new Application($release);
        $app->useStoragePath($storage);
        $config = require $configPath;
        $files = $config['backup']['source']['files'];
        $selection = FileSelection::create($files['include'])
            ->excludeFilesFrom($files['exclude'])
            ->shouldFollowLinks($files['follow_links']);
        $selected = [];
        foreach ($selection->selectedFiles() as $path) {
            if (is_file($path)) {
                $selected[] = realpath($path);
            }
        }
        expect($selected)->toContain(realpath($storage.'/app/public/upload.txt'), realpath($release.'/source.php'));
        foreach ([$release.'/vendor/excluded.php', $root.'/unrelated/private.txt', $release.'/.git/config', $storage.'/app/backup-temp/stale/archive.zip'] as $excluded) {
            expect($selected)->not->toContain(realpath($excluded));
        }
        expect(array_count_values($selected)[realpath($storage.'/app/public/upload.txt')])->toBe(1);
    } finally {
        Container::setInstance($original);
        $fs->deleteDirectory($root);
    }
})->with(['shared Forge storage' => true, 'ordinary local storage' => false]);
