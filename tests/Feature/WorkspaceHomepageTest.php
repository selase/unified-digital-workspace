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
        $router->getRoutes()->refreshNameLookups();
        $this->app->instance('router', $router);
        $this->app->instance('url', new UrlGenerator($router->getRoutes(), Request::create('https://udworkspace.com')));

        $this->compiledPath = sys_get_temp_dir().'/workspace-homepage-'.bin2hex(random_bytes(8));
        mkdir($this->compiledPath);
        $files = new Filesystem;
        $engines = new EngineResolver;
        $engines->register('blade', fn (): CompilerEngine => new CompilerEngine(new BladeCompiler($files, $this->compiledPath), $files));
        $this->views = new Factory($engines, new FileViewFinder($files, [dirname(__DIR__, 2).'/resources/views']), $events);
        $this->views->setContainer($this->app);
        $this->views->share('__env', $this->views);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->compiledPath);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_homepage_explains_supported_work_and_labels_illustrative_content(): void
    {
        $html = $this->views->make('welcome')->render();

        foreach (['Unified Digital Workspace', 'Make room for the work that matters.', 'Illustrative workspace', 'Documents', 'People', 'Sites', 'Projects', 'Forms'] as $text) {
            self::assertStringContainsString($text, $html);
        }
        self::assertStringNotContainsString('<title>Laravel</title>', $html);
        self::assertStringNotContainsString('href="#"', $html);
        self::assertStringNotContainsString('<button', $html);
        self::assertStringContainsString('href="#main-content"', $html);
        self::assertStringContainsString('id="main-content"', $html);

        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//a[starts-with(@href, "#")]') as $link) {
            $target = mb_substr($link->getAttribute('href'), 1);
            self::assertSame(1, $xpath->query('//*[@id="'.$target.'"]')->length);
        }
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
}
