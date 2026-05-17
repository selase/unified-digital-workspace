<?php

declare(strict_types=1);

use App\Modules\CmsCore\Support\VideoEmbed;

it('builds youtube background and watch embed urls', function () {
    $embed = VideoEmbed::fromUrl('https://www.youtube.com/watch?v=qVNYLJCEn-A');

    expect($embed?->kind)->toBe('youtube')
        ->and($embed?->backgroundSrc())->toContain('https://www.youtube.com/embed/qVNYLJCEn-A')
        ->and($embed?->backgroundSrc())->toContain('autoplay=1')
        ->and($embed?->backgroundSrc())->toContain('mute=1')
        ->and($embed?->watchSrc())->toBe('https://www.youtube-nocookie.com/embed/qVNYLJCEn-A');
});

it('builds vimeo background and watch embed urls', function () {
    $embed = VideoEmbed::fromUrl('https://vimeo.com/123456789');

    expect($embed?->kind)->toBe('vimeo')
        ->and($embed?->backgroundSrc())->toBe('https://player.vimeo.com/video/123456789?background=1&autoplay=1&loop=1&muted=1')
        ->and($embed?->watchSrc())->toBe('https://player.vimeo.com/video/123456789');
});

it('supports direct video files', function () {
    $embed = VideoEmbed::fromUrl('https://example.com/clip.mp4?download=1');

    expect($embed?->kind)->toBe('file')
        ->and($embed?->backgroundSrc())->toBe('https://example.com/clip.mp4?download=1')
        ->and($embed?->watchSrc())->toBe('https://example.com/clip.mp4?download=1')
        ->and($embed?->usesIframe())->toBeFalse();
});

it('ignores unsupported urls', function () {
    expect(VideoEmbed::fromUrl('https://example.com/story'))->toBeNull();
});
