<?php

declare(strict_types=1);

use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostRevision;
use App\Modules\CmsCore\Services\CmsThemeService;
use App\Modules\CmsCore\Support\ContentSanitizer;
use App\Services\Tenancy\TenantContext;

test('html sanitizer strips scripts, event handlers and javascript urls', function () {
    $clean = ContentSanitizer::html(
        '<p onclick="steal()">Hi</p><script>alert(1)</script>'
        .'<img src="x" onerror="alert(1)"><a href="javascript:alert(1)">bad</a>'
        .'<iframe src="https://evil.test"></iframe>'
    );

    expect($clean)
        ->not->toContain('<script')
        ->not->toContain('alert(1)</script>')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('<iframe')
        ->toContain('Hi');
});

test('html sanitizer keeps ordinary editor markup', function () {
    $clean = ContentSanitizer::html(
        '<h2>Title</h2><p>Some <strong>bold</strong> and <em>italic</em> text.</p>'
        .'<ul><li>One</li></ul><a href="https://example.com/a" target="_blank" rel="noopener">link</a>'
        .'<a href="/news/hello">relative</a><img src="/cms-media/1" alt="pic" class="rounded">'
    );

    expect($clean)
        ->toContain('<h2>Title</h2>')
        ->toContain('<strong>bold</strong>')
        ->toContain('<li>One</li>')
        ->toContain('href="https://example.com/a"')
        ->toContain('href="/news/hello"')
        ->toContain('src="/cms-media/1"');
});

test('post body is sanitized on write and when reading legacy rows', function () {
    $post = new Post;
    $post->body = '<p>Safe</p><script>alert(1)</script>';

    expect($post->body)->toBe('<p>Safe</p>');

    $legacy = (new Post)->newFromBuilder(['body' => '<p>Old</p><img src=x onerror=alert(1)>']);

    expect($legacy->body)->not->toContain('onerror')->toContain('<p>Old</p>');
});

test('post revision body is sanitized', function () {
    $revision = new PostRevision;
    $revision->body = '<b onmouseover="x()">hi</b>';

    expect($revision->body)->not->toContain('onmouseover')->toContain('hi');
});

test('custom css cannot break out of the style element', function () {
    $css = ContentSanitizer::css('body{color:red}</style><script>alert(1)</script><style>a>b{color:blue}');

    expect($css)->not->toContain('<')->toContain('body{color:red}')->toContain('a>b{color:blue}');
    expect(ContentSanitizer::css('a{background:url(javascript:alert(1))}'))->not->toContain('javascript:');
});

test('theme service returns sanitized custom css', function () {
    $theme = new CmsThemeService(app(TenantContext::class));
    (new ReflectionProperty($theme, 'cache'))->setValue($theme, [
        'custom_css' => 'p{color:red}</style><script>x()</script>',
    ]);

    expect($theme->customCss())->not->toContain('<script')->not->toContain('</style')->toContain('p{color:red}');
});

test('media urls carry a content hash so replaced files are not stuck in browser caches', function () {
    $media = new Media;
    $media->uuid = '6bda4ef5-dcec-4a20-8b1c-fc24ffbbce27';
    $media->disk = 'tenant';

    expect($media->serveUrl(false))->toBe('/cms-media/6bda4ef5-dcec-4a20-8b1c-fc24ffbbce27');

    $media->checksum_sha256 = 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';

    expect($media->serveUrl(false))->toBe('/cms-media/6bda4ef5-dcec-4a20-8b1c-fc24ffbbce27?v=abcdef012345')
        ->and($media->url())->toEndWith('/cms-media/6bda4ef5-dcec-4a20-8b1c-fc24ffbbce27?v=abcdef012345');

    $media->checksum_sha256 = str_repeat('0', 64);
    expect($media->serveUrl(false))->not->toContain('abcdef012345');
});
