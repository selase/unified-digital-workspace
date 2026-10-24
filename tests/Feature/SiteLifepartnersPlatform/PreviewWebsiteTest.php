<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\MenuItem;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Services\CmsUrlService;
use App\Modules\CmsCore\Services\CmsViewResolver;
use App\Modules\SiteLifepartnersPlatform\Providers\SiteLifepartnersPlatformServiceProvider;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->app->register(SiteLifepartnersPlatformServiceProvider::class);
    $this->previewTenant = (new Tenant)->forceFill(['id' => 'synthetic-preview-tenant', 'slug' => 'lifepartners-platform', 'name' => 'Life Partners Platform']);
    app(TenantContext::class)->setTenant($this->previewTenant);

    $menu = new Menu;
    $menu->setRelation('items', collect([
        'home' => 'Home', 'about' => 'About us', 'what-we-do' => 'What we do',
        'our-team' => 'Our Team', 'areas-of-operation' => 'Areas of Operation', 'contact' => 'Contact us',
    ])->map(function (string $label, string $slug): MenuItem {
        $item = new MenuItem(['label' => $label, 'parent_id' => null, 'sort_order' => 0]);
        $item->setRelation('post', new Post(['slug' => $slug]));

        return $item;
    })->values());
    $this->previewTheme = new class($menu)
    {
        public function __construct(private Menu $menu) {}

        public function siteName(): string
        {
            return 'Life Partners Platform';
        }

        public function headerMenu(): Menu
        {
            return $this->menu;
        }
    };
});

it('resolves the tenant module home page and layout before generic CMS views', function (): void {
    $resolver = app(CmsViewResolver::class);
    expect($resolver->resolve('home'))->toBe('site-lifepartners-platform::public.home')
        ->and($resolver->resolve('page'))->toBe('site-lifepartners-platform::public.page')
        ->and($resolver->resolveLayout())->toBe('site-lifepartners-platform::public.layouts.website');
});

it('renders editable CMS bodies and native accessible navigation in root and site contexts', function (bool $website): void {
    $this->app->forgetInstance(CmsUrlService::class);
    $body = '<main><h1>Saving Lives, Building Resilience</h1><p>Editable synthetic body.</p></main>';
    $path = $website ? '/__lpp-preview-view' : '/site/__lpp-preview-view';
    Route::get($path, fn () => view('site-lifepartners-platform::public.home', [
        'homepage' => new Post(['body' => $body]), 'theme' => $this->previewTheme,
    ]))->name($website ? 'cms-core.website.lpp-view-test' : 'cms-core.public.lpp-view-test');

    $request = Request::create($path);
    $this->app->instance('request', $request);
    app('url')->setRequest($request);
    $response = TestResponse::fromBaseResponse(Route::dispatch($request))->assertOk()
        ->assertSee('Saving Lives, Building Resilience')->assertSee('Editable synthetic body.')
        ->assertSee('<details class="lpp-mobile-menu">', false)
        ->assertSee('<main id="lpp-content"', false)
        ->assertSee('aria-label="Footer navigation"', false)
        ->assertSee('class="lpp-contact-link"', false)
        ->assertSee('Safer communities. Stronger futures.')
        ->assertSee('href="#lpp-content"', false)->assertSee('name="robots" content="noindex, nofollow"', false)
        ->assertSee('<link rel="icon" href="data:,">', false)
        ->assertDontSee('<script', false)->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('kit.fontawesome.com', false)->assertDontSee('cdn.', false);
    $prefix = $website ? '' : '/site';
    foreach (['about', 'what-we-do', 'our-team', 'areas-of-operation', 'contact'] as $slug) {
        $response->assertSee('href="'.url($prefix.'/'.$slug).'"', false);
    }
})->with([true, false]);

it('renders the source contact content without inventing contact donation or signup forms', function (): void {
    $html = view('site-lifepartners-platform::public.page', [
        'page' => new Post(['slug' => 'contact', 'title' => 'Contact us', 'body' => '<main><h2>Contact us</h2><p>Please get in touch with us if you have any questions or concerns.</p></main>']),
        'theme' => $this->previewTheme,
    ])->render();
    expect($html)->toContain('<h1 class="lpp-visually-hidden">Contact us</h1>', 'Please get in touch with us', 'Contact details — placeholders',
        '[Public email address to be confirmed]', '[Public phone number to be confirmed]',
        '[Office address to be confirmed]', '[Recipient to be confirmed]',
        'Online enquiries are not available in this preview.')
        ->not->toContain('<form', 'mailto:', 'tel:', 'Donate', 'Sign up');

    $otherPage = view('site-lifepartners-platform::public.page', [
        'page' => new Post(['slug' => 'about', 'title' => 'About', 'body' => '<p>About us</p>']),
        'theme' => $this->previewTheme,
    ])->render();
    expect($otherPage)->not->toContain('Contact details — placeholders', '[Recipient to be confirmed]');
});

it('limits response noindex headers to Lifepartners public CMS routes', function (): void {
    foreach ([['lifepartners-platform', 'cms-core.website.home', true], ['lifepartners-platform', 'cms-core.public.home', true], ['another-site', 'cms-core.website.home', false], ['lifepartners-platform', 'dashboard', false]] as [$slug, $name, $expected]) {
        app(TenantContext::class)->setTenant((new Tenant)->forceFill(['slug' => $slug]));
        $request = Request::create('/');
        $request->setRouteResolver(fn () => (new RoutingRoute('GET', '/', []))->name($name));
        $response = response('Synthetic');
        Event::dispatch(new RequestHandled($request, $response));
        expect($response->headers->has('X-Robots-Tag'))->toBe($expected);
    }
});


it('labels unverified source stories without replacing editable CMS content', function (): void {
    $html = view('site-lifepartners-platform::public.home', [
        'homepage' => new Post(['body' => '<section><h2 class="display-4">Success Stories</h2><blockquote>Unverified source quotation.</blockquote></section>']),
        'theme' => $this->previewTheme,
    ])->render();
    expect($html)->toContain('Unverified source quotation.', 'Source story awaiting verification', 'lpp-review-note');

    $empty = view('site-lifepartners-platform::public.home', [
        'homepage' => null, 'theme' => $this->previewTheme,
    ])->render();
    expect($empty)->toContain('This preview page is not available yet.')
        ->not->toContain('Source story awaiting verification');
});
