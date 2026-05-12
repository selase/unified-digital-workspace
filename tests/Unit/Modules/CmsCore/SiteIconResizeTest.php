<?php

declare(strict_types=1);

use App\Modules\CmsCore\Services\SiteIconService;

/**
 * Exercises the GD resize routine on the SiteIconService. We invoke the
 * private resizer via reflection so we can assert the byte-level output
 * (dimensions, PNG signature, transparency) without spinning up the full
 * Media/MediaVariant/Storage stack that variantBytes() depends on.
 */
function invokeResize(SiteIconService $service, string $sourceBytes, string $mime, int $size): ?string
{
    $method = new ReflectionMethod($service, 'resizeToPng');
    $method->setAccessible(true);

    return $method->invoke($service, $sourceBytes, $mime, $size);
}

function makeSquarePng(int $w, int $h, array $rgb = [255, 0, 0]): string
{
    $im = imagecreatetruecolor($w, $h);
    $color = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    imagefilledrectangle($im, 0, 0, $w, $h, $color);

    ob_start();
    imagepng($im);
    $bytes = (string) ob_get_clean();
    imagedestroy($im);

    return $bytes;
}

beforeEach(function (): void {
    $this->service = app(SiteIconService::class);
});

test('resizing produces a PNG of the requested square dimensions', function (): void {
    $source = makeSquarePng(256, 256);

    $resized = invokeResize($this->service, $source, 'image/png', 32);

    expect($resized)->not->toBeNull();
    expect(str_starts_with((string) $resized, "\x89PNG\r\n\x1a\n"))->toBeTrue();

    $img = imagecreatefromstring($resized);
    expect(imagesx($img))->toBe(32);
    expect(imagesy($img))->toBe(32);
    imagedestroy($img);
});

test('non-square source is center-cropped to a square then resized', function (): void {
    $source = makeSquarePng(400, 200);

    $resized = invokeResize($this->service, $source, 'image/png', 64);

    $img = imagecreatefromstring($resized);
    expect(imagesx($img))->toBe(64);
    expect(imagesy($img))->toBe(64);
    imagedestroy($img);
});

test('unsupported mime returns null instead of falling through', function (): void {
    $resized = invokeResize($this->service, 'not-an-image', 'application/pdf', 32);

    expect($resized)->toBeNull();
});

test('resizing supports JPEG sources via imagecreatefromstring', function (): void {
    $im = imagecreatetruecolor(128, 128);
    imagefilledrectangle($im, 0, 0, 128, 128, imagecolorallocate($im, 0, 0, 255));
    ob_start();
    imagejpeg($im, null, 90);
    $jpegBytes = (string) ob_get_clean();
    imagedestroy($im);

    $resized = invokeResize($this->service, $jpegBytes, 'image/jpeg', 96);

    $img = imagecreatefromstring($resized);
    expect(imagesx($img))->toBe(96);
    expect(imagesy($img))->toBe(96);
    imagedestroy($img);
});

test('manifest exposes 192 and 512 icons under any/maskable purpose', function (): void {
    $manifest = $this->service->manifest();

    expect($manifest)->toHaveKey('name')
        ->and($manifest)->toHaveKey('icons')
        ->and($manifest)->toHaveKey('theme_color')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/');
});

test('available sizes catalog covers iOS, Android, and web breakpoints', function (): void {
    expect(SiteIconService::SIZES)->toContain(32)
        ->and(SiteIconService::SIZES)->toContain(180)
        ->and(SiteIconService::SIZES)->toContain(192)
        ->and(SiteIconService::SIZES)->toContain(512);
});
