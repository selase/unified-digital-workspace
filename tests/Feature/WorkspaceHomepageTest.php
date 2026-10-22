<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use PHPUnit\Framework\TestCase;

/**
 * A standalone renderer deliberately avoids tests/Pest.php's database-backed
 * Feature test binding and application providers. Run through the Pest runner.
 */
final class WorkspaceHomepageTest extends TestCase
{
    private Application $app;

    private Factory $views;

    private string $compiledPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository([
            'app' => ['locale' => 'en', 'system_setting' => ['allow_registration' => true]],
            'usage-pricing' => require dirname(__DIR__, 2).'/config/usage-pricing.php',
        ]));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);

        $events = new Dispatcher($this->app);
        $router = new Router($events, $this->app);
        $router->get('/login', fn (): string => '')->name('login');
        $router->get('/register', fn (): string => '')->name('register');
        $router->get('/product-template', fn (): string => '')->name('product.template');
        $router->get('/product-enterprise', fn (): string => '')->name('product.enterprise');
        $router->get('/modules/{category?}', fn (): string => '')->name('product.modules');
        $router->getRoutes()->refreshNameLookups();
        $this->app->instance('router', $router);
        $this->app->instance('url', new UrlGenerator($router->getRoutes(), Request::create('https://udworkspace.com')));

        $this->compiledPath = sys_get_temp_dir().'/workspace-homepage-'.bin2hex(random_bytes(8));
        mkdir($this->compiledPath);
        $files = new Filesystem;
        $bladeCompiler = new BladeCompiler($files, $this->compiledPath);
        $engines = new EngineResolver;
        $engines->register('blade', fn (): CompilerEngine => new CompilerEngine($bladeCompiler, $files));
        $this->views = new Factory($engines, new FileViewFinder($files, [dirname(__DIR__, 2).'/resources/views']), $events);
        $this->views->setContainer($this->app);
        $this->views->share('__env', $this->views);
        $this->app->instance('view', $this->views);
        $this->app->instance('blade.compiler', $bladeCompiler);
    }

    protected function tearDown(): void
    {
        Illuminate\View\Component::flushCache();
        Illuminate\View\Component::forgetFactory();
        (new Filesystem)->deleteDirectory($this->compiledPath);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_homepage_explains_supported_work_and_labels_illustrative_content(): void
    {
        $html = $this->views->make('welcome')->render();

        foreach ([
            'Unified Digital Workspace',
            'Every tool for your work.',
            'One shared workspace.',
            'Inside the Unified Digital Workspace',
            'Illustrative workspace',
            '01 / The Tools',
            '02 / Why Unified',
            '03 / Plans &amp; Pricing',
            'Documents &amp; Memos',
            'People &amp; HR',
            'Sites &amp; Forms',
            'Projects &amp; Tasks',
            'Purpose-built tools for everyday work, connected to one shared engine.',
            'Too many tools fragment the work. One workspace keeps it whole.',
            'A website today. A wider workspace when you need it.',
        ] as $text) {
            self::assertStringContainsString($text, $html);
        }
        self::assertStringNotContainsString('<title>Laravel</title>', $html);
        self::assertStringNotContainsString('href="#"', $html);
        self::assertStringNotContainsString('php artisan', $html);
        self::assertStringNotContainsString('TenantResolver::fromHost', $html);
        self::assertStringNotContainsString('class="pill"', $html);
        self::assertStringContainsString('href="#main-content"', $html);
        self::assertStringContainsString('id="main-content"', $html);

        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//a[starts-with(@href, "#")]') as $link) {
            $target = mb_substr($link->getAttribute('href'), 1);
            self::assertSame(1, $xpath->query('//*[@id="'.$target.'"]')->length);
        }

        self::assertStringContainsString('d="M12 14V26C12 32.6274 17.3726 38 24 38C30.6274 38 36 32.6274 36 26V14"', $html);
        self::assertStringNotContainsString('<i></i><i></i><i></i><i></i>', $html);
    }

    public function test_homepage_includes_interactive_workbench_and_section_separators(): void
    {
        $html = $this->views->make('welcome')->render();

        // Interactive workbench view panels
        self::assertStringContainsString('id="view-overview"', $html);
        self::assertStringContainsString('id="view-projects"', $html);
        self::assertStringContainsString('id="view-documents"', $html);
        self::assertStringContainsString('id="view-people"', $html);
        self::assertStringContainsString('id="view-sites"', $html);

        // Section separators breaking continuous rail grid
        self::assertStringContainsString('class="section-separator"', $html);
        self::assertStringContainsString('class="section-separator sep-alt"', $html);
        self::assertStringContainsString('6 Integrated Core Modules', $html);
        self::assertStringContainsString('The Single-System Advantage', $html);
        self::assertStringContainsString('Zero context switching', $html);
    }

    public function test_homepage_links_to_real_conversion_destinations_and_configured_prices(): void
    {
        $this->app['config']->set('usage-pricing.hybrid.website_plans.essentials.GHS', 345);
        $html = $this->views->make('welcome')->render();

        self::assertStringContainsString('https://udworkspace.com/login', $html);
        self::assertStringContainsString('https://udworkspace.com/register', $html);
        self::assertStringContainsString('https://udworkspace.com/product-template#pricing', $html);
        self::assertStringContainsString('GHS 345', $html);
        self::assertStringContainsString('GHS 600', $html);
        self::assertStringContainsString('Setup, additional access and usage charges may apply.', $html);
    }

    public function test_registration_ctas_are_omitted_when_registration_route_is_unavailable(): void
    {
        $router = new Router(new Dispatcher($this->app), $this->app);
        $router->get('/login', fn (): string => '')->name('login');
        $router->get('/product-template', fn (): string => '')->name('product.template');
        $router->getRoutes()->refreshNameLookups();
        $this->app->instance('router', $router);
        Facade::clearResolvedInstance('router');
        $this->app->instance('url', new UrlGenerator($router->getRoutes(), Request::create('https://udworkspace.com')));

        $html = $this->views->make('welcome')->render();

        self::assertStringNotContainsString('/register', $html);
        self::assertStringContainsString('https://udworkspace.com/login', $html);
        self::assertStringContainsString('https://udworkspace.com/product-template#pricing', $html);
    }

    public function test_hidden_registration_uses_the_workspace_enquiry_instead(): void
    {
        $this->app['config']->set('app.system_setting.allow_registration', false);
        $html = $this->views->make('welcome')->render();
        self::assertStringNotContainsString('/register', $html);
        self::assertStringContainsString('Plan your workspace', $html);
        self::assertStringContainsString('/product-enterprise', $html);
    }

    public function test_preview_tables_have_named_keyboard_accessible_scroll_regions(): void
    {
        $document = new DOMDocument;
        $document->loadHTML($this->views->make('welcome')->render(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        foreach (['overview', 'projects', 'documents', 'people', 'sites'] as $panel) {
            $regions = $xpath->query('//*[@id="view-'.$panel.'"]//*[@role="region" and @tabindex="0" and @aria-label]');
            self::assertSame(1, $regions->length);
            self::assertSame(1, $xpath->query('.//table', $regions->item(0))->length);
        }
    }
}
