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
    $fs->makeDirectory($root.'/unrelated', 0700, true);
    file_put_contents($release.'/source.php', '<?php');
    file_put_contents($release.'/vendor/excluded.php', 'not backed up');
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
        expect($selected)->toContain(realpath($storage.'/app/public/upload.txt'), realpath($release.'/source.php'))
            ->not->toContain(realpath($release.'/vendor/excluded.php'), realpath($root.'/unrelated/private.txt'));
        expect(array_count_values($selected)[realpath($storage.'/app/public/upload.txt')])->toBe(1);
    } finally {
        Container::setInstance($original);
        $fs->deleteDirectory($root);
    }
})->with(['shared Forge storage' => true, 'ordinary local storage' => false]);
