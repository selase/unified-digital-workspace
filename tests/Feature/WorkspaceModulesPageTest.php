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

final class WorkspaceModulesPageTest extends TestCase
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
            'product-page' => require dirname(__DIR__, 2).'/config/product-page.php',
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
        $router->get('/product-docs/{section?}', fn () => redirect()->route('product.modules'))->name('product.docs');
        $router->getRoutes()->refreshNameLookups();
        $this->app->instance('router', $router);
        $this->app->instance('url', new UrlGenerator($router->getRoutes(), Request::create('https://udworkspace.com')));

        $this->compiledPath = sys_get_temp_dir().'/modules-test-'.bin2hex(random_bytes(8));
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

    public function test_modules_page_lists_and_explains_all_workspace_modules(): void
    {
        $html = $this->views->make('product.modules')->render();

        // Title and positioning
        self::assertStringContainsString('Unified Digital Workspace', $html);
        self::assertStringContainsString('Every module.', $html);
        self::assertStringContainsString('Built to connect.', $html);

        // Core categories
        self::assertStringContainsString('01 / Operations', $html);
        self::assertStringContainsString('02 / Governance', $html);
        self::assertStringContainsString('03 / People &amp; HR', $html);
        self::assertStringContainsString('04 / Public Web', $html);
        self::assertStringContainsString('05 / Extensibility', $html);

        // Listed and explained modules
        $expectedModules = [
            'Projects &amp; Tasks',
            'Facilities &amp; Assets',
            'Incident Management &amp; CAPA',
            'Quality Monitoring &amp; Evidence Vault',
            'Document Management',
            'Institutional Memos &amp; e-Signatures',
            'Institutional Intranet Portal',
            'Forums &amp; Direct Messaging',
            'Employee Directory &amp; Leave',
            'Recruitment ATS &amp; Appraisals',
            'Organizational Hierarchy &amp; RBAC',
            'Salary Structures &amp; Promotions',
            'CMS &amp; Web Publishing',
            'Forms, Surveys &amp; Live Polls',
            'Institutional CRM &amp; Deals',
            'Real Estate &amp; Land Compliance',
            'Vendor Marketplace &amp; Escrow',
            'Custom Report Builder &amp; Exporter',
            'Business Process Automation Engine',
            'Workspace Store &amp; Credit Top-Ups',
        ];

        foreach ($expectedModules as $module) {
            self::assertStringContainsString($module, $html);
        }

        // Capabilities and architecture guarantees
        self::assertStringContainsString('100% Tenant Isolation', $html);
        self::assertStringContainsString('Single Directory &amp; RBAC', $html);
        self::assertStringContainsString('Centralized Audit Trail', $html);
        self::assertStringContainsString('Core Capabilities', $html);
        self::assertStringContainsString('Connected Context', $html);

        // Negative assertions
        self::assertStringNotContainsString('php artisan', $html);
        self::assertStringNotContainsString('class="pill"', $html);
        self::assertStringNotContainsString('href="#"', $html);
    }

    public function test_homepage_navigation_links_to_modules_page(): void
    {
        $html = $this->views->make('welcome')->render();

        self::assertStringContainsString('https://udworkspace.com/modules', $html);
        self::assertStringContainsString('>Modules</a>', $html);
        self::assertStringNotContainsString('>Documentation</a>', $html);
    }
}
