<?php

declare(strict_types=1);

use App\Http\Controllers\ModuleHelpController;
use App\Models\User;
use App\Modules\CmsCore\Support\ContentSanitizer;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

uses(TestCase::class);

beforeEach(function (): void {
    config(['database.connections' => [], 'session.driver' => 'array', 'cache.default' => 'array']);
    Gate::swap(new Illuminate\Auth\Access\Gate(app(), fn (): ?Illuminate\Contracts\Auth\Authenticatable => auth()->user()));
    Gate::before(fn (): bool => false);
    $this->withoutVite();
    $this->withoutMiddleware([
        App\Http\Middleware\ResolveTenant::class,
        App\Http\Middleware\RedirectAdminFromCustomDomain::class,
        App\Http\Middleware\EnforceUsageLimits::class,
        App\Http\Middleware\MeterRequestUsage::class,
        App\Http\Middleware\TrackActiveUser::class,
        App\Http\Middleware\EnsureOnboardingComplete::class,
    ]);
});

it('bundles all seventeen module quickstarts', function (): void {
    $guide = File::get(base_path('docs/MODULE_QUICKSTARTS.md'));
    expect(preg_match_all('/^## \d+\./m', $guide))->toBe(17);
});

it('redirects guests to login without reading the guide', function (string $url): void {
    File::partialMock()->shouldReceive('exists')->with(base_path('docs/MODULE_QUICKSTARTS.md'))->never();
    $this->get($url)->assertRedirect(route('login'));
})->with([
    'main host' => '/help/modules',
    'tenant host' => 'http://sample.unified-digital-workspace.test/help/modules',
]);

it('renders the guide for an authenticated user without module permissions', function (string $url): void {
    $user = new User;
    $user->forceFill([
        'id' => 123, 'uuid' => 'help-reader', 'first_name' => 'Help', 'last_name' => 'Reader',
        'email' => 'reader@example.invalid', 'photo' => null, 'gravatar' => null,
        'two_factor_secret' => null, 'two_factor_confirmed_at' => null,
    ]);
    $this->actingAs($user)->get($url)
        ->assertOk()->assertViewIs('help.modules')
        ->assertSee('Workspace module quickstarts')->assertSee('17. Store:')
        ->assertSee('When something does not work')->assertSee('Source verification and release limits');
})->with([
    'main host' => '/help/modules?path=/etc/passwd',
    'tenant host' => 'http://sample.unified-digital-workspace.test/help/modules?path=/etc/passwd',
]);

it('renders safe markdown and removes unsafe markup through the existing sanitizer', function (): void {
    File::partialMock()->shouldReceive('exists')->with(base_path('docs/MODULE_QUICKSTARTS.md'))->andReturnTrue();
    File::shouldReceive('get')->with(base_path('docs/MODULE_QUICKSTARTS.md'))->andReturn(
        '# Safe heading'."\n\n".'<script>alert(1)</script><img src=x onerror="alert(2)">'."\n\n".'[unsafe](javascript:alert%283%29)'
    );
    $view = app(ModuleHelpController::class)();
    $html = $view->getData()['guideHtml'];
    expect($html)->toContain('<h1>Safe heading</h1>')->not->toContain('<script', 'onerror', 'javascript:');
    expect(ContentSanitizer::markdown('<script>bad()</script>'))->not->toContain('<script');
});

it('shows a useful message if the bundled guide is missing', function (): void {
    File::partialMock()->shouldReceive('exists')->with(base_path('docs/MODULE_QUICKSTARTS.md'))->andReturnFalse();
    $view = app(ModuleHelpController::class)();
    expect($view->getData()['guideHtml'])->toBeNull();
    $this->blade("@include('help.content', ['guideHtml' => null])")
        ->assertSee('Module help is temporarily unavailable')->assertSee('workspace administrator');
});

it('offers shared module home help for all seventeen homes only', function (): void {
    $navigation = file_get_contents(app_path('Services/Navigation/WorkspaceNavigation.php'));
    preg_match_all("/'home_route' => '([^']+)'/", $navigation, $matches);
    expect($matches[1])->toHaveCount(17);
    foreach ($matches[1] as $name) {
        $route = new Illuminate\Routing\Route('GET', '/', fn (): null => null);
        $route->name($name);
        request()->setRouteResolver(fn (): Illuminate\Routing\Route => $route);
        expect(Blade::render('<x-metronic.module-help />'))->toContain('Module quickstarts', '/help/modules');
    }
    foreach (['tenant.pricing', 'cms-core.posts.show', 'help.modules', 'tenant.dashboard'] as $name) {
        $route->name($name);
        expect(mb_trim(Blade::render('<x-metronic.module-help />')))->toBe('');
    }
});

it('registers help with authentication and exposes a workspace navigation link', function (): void {
    $route = Route::getRoutes()->getByName('help.modules');
    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('auth', '2fa_challenge');
    $navigation = app(App\Services\Navigation\WorkspaceNavigation::class);
    $groups = (new ReflectionMethod($navigation, 'resolveTenantGroups'))->invoke($navigation, 'sample', 'help.modules', null);
    $help = $groups->flatMap(fn (array $group): array => $group['items'])->firstWhere('label', 'Module Help');
    expect($help)->not->toBeNull()->and($help['url'])->toContain('/help/modules')->and($help['is_active'])->toBeTrue();
});

it('escapes literal markup while preserving ordinary markdown formatting', function (): void {
    $html = ContentSanitizer::markdown('**Read this** &lt;script&gt;example&lt;/script&gt;');
    expect($html)->toContain('<strong>Read this</strong>', '&lt;script&gt;example&lt;/script&gt;')
        ->not->toContain('<script>');
});
