<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Support\Env;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;

beforeEach(function (): void {
    $this->originalContainer = Container::getInstance();
    $this->originalFacadeApplication = Illuminate\Support\Facades\Facade::getFacadeApplication();
    new Illuminate\Foundation\Application(dirname(__DIR__, 2));
    $this->originalDriver = [$_ENV['DB_CONNECTION'] ?? null, $_SERVER['DB_CONNECTION'] ?? null];
    $this->originalEnvironment = [];
    foreach (['DB_CONNECTION', 'DATABASE_URL', 'DB_SSLMODE', 'DB_SSLROOTCERT', 'PGSSLMODE', 'PGSSLROOTCERT', 'PG_DUMP_BINARY_PATH'] as $name) {
        $this->originalEnvironment[$name] = Env::getRepository()->get($name);
        Env::getRepository()->clear($name);
    }
});

afterEach(function (): void {
    foreach ($this->originalEnvironment as $name => $value) {
        if ($value === null) {
            Env::getRepository()->clear($name);
        } else {
            Env::getRepository()->set($name, $value);
        }
    }
    [$_ENV['DB_CONNECTION'], $_SERVER['DB_CONNECTION']] = $this->originalDriver;
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($this->originalFacadeApplication);
    Container::setInstance($this->originalContainer);
});

it('passes PostgreSQL TLS settings through the connector and supported dumper API', function (string $connection, bool $url): void {
    Env::getRepository()->set('DB_CONNECTION', 'pgsql');
    Env::getRepository()->set('DB_SSLMODE', $url ? 'require' : 'verify-full');
    Env::getRepository()->set('DB_SSLROOTCERT', $url ? '/ignored.pem' : "/tmp/CA's root.pem");
    Env::getRepository()->set('PG_DUMP_BINARY_PATH', '/opt/postgresql/bin');
    if ($url) {
        Env::getRepository()->set('DATABASE_URL', 'postgresql://user:secret@database.example.test/example?sslmode=verify-full&sslrootcert='.rawurlencode("/tmp/CA's root.pem"));
    }
    $config = require dirname(__DIR__, 2).'/config/database.php';
    $container = new Illuminate\Foundation\Application(dirname(__DIR__, 2));
    $container->instance('config', new Repository(['database' => $config]));
    Container::setInstance($container);

    $settings = $config['connections'][$connection];
    $connector = new class extends PostgresConnector
    {
        public function dsn(array $config): string
        {
            return $this->getDsn($config);
        }
    };
    expect($connector->dsn($settings))->toContain('sslmode=verify-full', "sslrootcert=/tmp/CA's root.pem");
    $dumper = DbDumperFactory::createFromConnection($connection);
    $command = $dumper->getDumpCommand('/tmp/example.sql');
    $tls = "sslmode='verify-full' sslrootcert='/tmp/CA\\'s root.pem'";
    expect($command)->toContain("'/opt/postgresql/bin/pg_dump'", '--dbname='.escapeshellarg($tls))
        ->not->toContain('secret', '--no-ssl');
})->with(['landlord', 'pgsql'])->with([false, true]);

it('preserves defaults and permits native libpq TLS environment fallbacks', function (): void {
    $originalDriver = $_ENV['DB_CONNECTION'] ?? null;
    $_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'mysql';
    $defaults = require dirname(__DIR__, 2).'/config/database.php';
    expect($defaults['connections']['pgsql']['sslmode'])->toBe('prefer')
        ->and($defaults['connections']['pgsql']['sslrootcert'])->toBeNull();
    Env::getRepository()->set('PGSSLMODE', 'verify-full');
    Env::getRepository()->set('PGSSLROOTCERT', '/tmp/root.pem');
    $config = require dirname(__DIR__, 2).'/config/database.php';
    foreach (['landlord', 'pgsql'] as $connection) {
        expect($config['connections'][$connection]['sslmode'])->toBe('verify-full')
            ->and($config['connections'][$connection]['sslrootcert'])->toBe('/tmp/root.pem');
    }
    expect($config['connections']['landlord']['dump'])->not->toHaveKey('add_extra_option');
    $_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = $originalDriver;
});

it('requires only the PDO PostgreSQL extension consistently in the manifest and lock', function (): void {
    $root = dirname(__DIR__, 2);
    $manifest = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $lock = json_decode(file_get_contents($root.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    expect($manifest['require'])->toHaveKey('ext-pdo_pgsql')->not->toHaveKey('ext-pgsql')
        ->and($lock['platform'])->toHaveKey('ext-pdo_pgsql')->not->toHaveKey('ext-pgsql');
});

it('preserves dedicated tenant TLS from the credential source', function (bool $byo): void {
    $container = new Illuminate\Foundation\Application(dirname(__DIR__, 2));
    $container->instance('config', new Repository(['database' => ['connections' => ['landlord' => [
        'host' => 'managed.example.test', 'port' => 5432, 'database' => 'landlord',
        'username' => 'managed', 'password' => 'secret', 'sslmode' => 'verify-full',
        'sslrootcert' => '/managed/ca.pem', 'sslcert' => '/managed/client.pem', 'sslkey' => '/managed/key.pem',
    ]]]]));
    $database = Mockery::mock();
    $database->shouldReceive('purge')->once()->with('tenant');
    $container->instance('db', $database);
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($container);
    $secrets = Mockery::mock(App\Contracts\Secrets\SecretsProvider::class);
    if ($byo) {
        $secrets->shouldReceive('getSecret')->once()->with('byo')->andReturn([
            'host' => 'byo.example.test', 'port' => 5432, 'database' => 'tenant',
            'username' => 'byo', 'password' => 'secret', 'sslmode' => 'verify-ca', 'sslrootcert' => '/byo/ca.pem',
        ]);
    } else {
        $secrets->shouldNotReceive('getSecret');
    }
    expect((new ReflectionClass(App\Services\Tenancy\TenantDatabaseManager::class))->isFinal())->toBeFalse();
    $tenant = new App\Models\Tenant;
    $tenant->forceFill(['isolation_mode' => 'dedicated', 'db_driver' => 'pgsql', 'db_secret_ref' => $byo ? 'byo' : null, 'meta' => ['database' => 'tenant']]);
    (new App\Services\Tenancy\TenantDatabaseManager($secrets))->configure($tenant);
    $config = $container['config']->get('database.connections.tenant');
    expect($config['database'])->toBe('tenant')
        ->and($config['sslmode'])->toBe($byo ? 'verify-ca' : 'verify-full')
        ->and($config['sslrootcert'])->toBe($byo ? '/byo/ca.pem' : '/managed/ca.pem')
        ->and($config['sslcert'])->toBe($byo ? null : '/managed/client.pem')
        ->and($config['sslkey'])->toBe($byo ? null : '/managed/key.pem');
})->with([false, true]);

it('uses PostgreSQL dump TLS when the database URL selects the driver', function (): void {
    $_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'mysql';
    Env::getRepository()->set('DATABASE_URL', 'postgresql://user:secret@database.example.test/example?sslmode=verify-full');
    $config = require dirname(__DIR__, 2).'/config/database.php';
    expect($config['connections']['landlord']['dump']['add_extra_option'])
        ->toBe('--dbname='.escapeshellarg("sslmode='verify-full'"));
});

it('uses a fresh framework PostgreSQL connection with URL resolved TLS for tenant DDL', function (string $operation): void {
    $container = new Illuminate\Foundation\Application(dirname(__DIR__, 2));
    $container->instance('config', new Repository(['database' => ['connections' => ['landlord' => [
        'driver' => 'pgsql', 'host' => 'stale.example.test', 'database' => 'stale',
        'username' => 'stale', 'password' => 'stale', 'sslmode' => 'prefer',
        'url' => 'postgresql://cloud:encoded%40password@cloud.example.test:6543/managed?sslmode=verify-full&sslrootcert=%2Fcloud%2Fca.pem&sslcert=%2Fcloud%2Fclient.pem&sslkey=%2Fcloud%2Fkey.pem',
    ]]]]));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($container);
    $log = Mockery::mock();
    $log->shouldReceive('info')->zeroOrMoreTimes();
    $log->shouldReceive('error')->zeroOrMoreTimes();
    $container->instance('log', $log);
    $pdo = Mockery::mock(PDO::class);
    $connector = Mockery::mock(PostgresConnector::class);
    $connector->shouldReceive('connect')->once()->withArgs(function (array $config): bool {
        expect($config)->toMatchArray([
            'host' => 'cloud.example.test', 'port' => 6543, 'database' => 'managed',
            'username' => 'cloud', 'password' => 'encoded@password', 'sslmode' => 'verify-full',
            'sslrootcert' => '/cloud/ca.pem', 'sslcert' => '/cloud/client.pem', 'sslkey' => '/cloud/key.pem',
        ]);

        return true;
    })->andReturnUsing(function () use ($operation, $pdo): PDO {
        if ($operation === 'provision') {
            throw new PDOException('controlled connection failure');
        }

        return $pdo;
    });
    $container->instance(PostgresConnector::class, $connector);
    if ($operation === 'provision') {
        $tenant = new App\Models\Tenant;
        $tenant->forceFill(['id' => 'test-id', 'db_driver' => 'pgsql', 'meta' => []]);
        $provisioner = (new ReflectionClass(App\Services\Tenancy\TenantProvisioner::class))->newInstanceWithoutConstructor();
        expect(fn () => (new ReflectionMethod($provisioner, 'createDatabase'))->invoke($provisioner, $tenant))
            ->toThrow(RuntimeException::class, 'controlled connection failure');
    } else {
        $remover = new App\Services\Tenancy\TenantDatabaseRemover;
        expect((new ReflectionMethod($remover, 'landlordPdo'))->invoke($remover))->toBe($pdo);
    }
})->with(['provision', 'remove']);

it('passes URL resolved TLS and credentials to the tenant removal dump process', function (): void {
    $root = sys_get_temp_dir().'/udw-dump-tls-'.bin2hex(random_bytes(8));
    $fs = new Illuminate\Filesystem\Filesystem;
    $fs->makeDirectory($root.'/bin', 0700, true);
    $container = new Illuminate\Foundation\Application(dirname(__DIR__, 2));
    $container->useStoragePath($root.'/storage');
    $container->instance('config', new Repository(['database' => ['connections' => ['landlord' => [
        'driver' => 'pgsql', 'host' => 'stale.example.test', 'database' => 'stale',
        'username' => 'stale', 'password' => 'stale', 'sslmode' => 'prefer',
        'dump' => ['dump_binary_path' => $root.'/bin'],
        'url' => 'postgresql://cloud:dummy%40password@cloud.example.test:6543/managed?sslmode=verify-full&sslrootcert=%2Fcloud%2Fca.pem&sslcert=%2Fcloud%2Fclient.pem&sslkey=%2Fcloud%2Fkey.pem',
    ]]]]));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($container);
    $script = <<<'SCRIPT'
<?php
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--file=')) {
        file_put_contents(substr($argument, 7), json_encode([
            'args' => $argv,
            'mode' => getenv('PGSSLMODE'),
            'ca' => getenv('PGSSLROOTCERT'),
            'cert' => getenv('PGSSLCERT'),
            'key' => getenv('PGSSLKEY'),
            'password' => getenv('PGPASSWORD'),
        ]));
    }
}
SCRIPT;
    file_put_contents($root.'/capture.php', $script);
    file_put_contents($root.'/bin/pg_dump', "#!/bin/sh\nexec ".escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/capture.php').' "$@"'."\n");
    chmod($root.'/bin/pg_dump', 0700);
    try {
        $remover = new App\Services\Tenancy\TenantDatabaseRemover;
        $file = (new ReflectionMethod($remover, 'backup'))->invoke($remover, 'tenant_test_id');
        $observed = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        expect($observed)->toMatchArray([
            'mode' => 'verify-full', 'ca' => '/cloud/ca.pem', 'cert' => '/cloud/client.pem',
            'key' => '/cloud/key.pem', 'password' => 'dummy@password',
        ])->and($observed['args'])->toContain('--host=cloud.example.test', '--port=6543', '--username=cloud', 'tenant_test_id')
            ->and(fileperms($file) & 0777)->toBe(0600);
    } finally {
        $fs->deleteDirectory($root);
    }
});

it('protects the landlord database resolved from a URL before attempting removal', function (): void {
    $container = new Illuminate\Foundation\Application(dirname(__DIR__, 2));
    $container->instance('config', new Repository(['database' => ['connections' => ['landlord' => [
        'driver' => 'pgsql', 'database' => 'stale',
        'url' => 'postgresql://cloud:dummy@cloud.example.test/tenant_test_id?sslmode=verify-full',
    ]]]]));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($container);
    $tenant = new App\Models\Tenant;
    $tenant->forceFill(['id' => 'test-id', 'db_driver' => 'pgsql', 'isolation_mode' => 'db_per_tenant', 'meta' => ['database' => 'tenant_test_id']]);
    expect(fn () => (new App\Services\Tenancy\TenantDatabaseRemover)->remove($tenant))
        ->toThrow(DomainException::class, 'it is not a dedicated tenant database');
});
