<?php

declare(strict_types=1);

use App\Modules\CmsCore\Http\Controllers\Web\CmsMediaLibraryController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * The resolveMimeType helper is private; reflection lets us assert the
 * normalisation rule (favicons must surface as image/x-icon even when
 * the browser reports no MIME) without invoking the full upload flow.
 */
function invokeResolveMimeType(UploadedFile $file, string $extension): string
{
    $controller = new CmsMediaLibraryController;
    $method = new ReflectionMethod($controller, 'resolveMimeType');
    $method->setAccessible(true);

    return (string) $method->invoke($controller, $file, $extension);
}

function makeUploadedFile(string $name, ?string $mime): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'mime_test_');
    File::put($path, 'x');

    return new UploadedFile($path, $name, $mime, null, true);
}

test('ico upload with missing mime falls back to image/x-icon', function (): void {
    $file = makeUploadedFile('favicon.ico', null);

    expect(invokeResolveMimeType($file, 'ico'))->toBe('image/x-icon');
});

test('ico upload with generic mime is normalised to image/x-icon', function (): void {
    $file = makeUploadedFile('favicon.ico', 'application/octet-stream');

    expect(invokeResolveMimeType($file, 'ico'))->toBe('image/x-icon');
});

test('ico upload with a correct image mime is preserved', function (): void {
    $file = makeUploadedFile('favicon.ico', 'image/vnd.microsoft.icon');

    expect(invokeResolveMimeType($file, 'ico'))->toBe('image/vnd.microsoft.icon');
});

test('non-ico upload keeps the browser-reported mime', function (): void {
    $file = makeUploadedFile('photo.png', 'image/png');

    expect(invokeResolveMimeType($file, 'png'))->toBe('image/png');
});

test('non-ico upload with no mime falls back to octet-stream', function (): void {
    $file = makeUploadedFile('data.bin', null);

    expect(invokeResolveMimeType($file, 'bin'))->toBe('application/octet-stream');
});
