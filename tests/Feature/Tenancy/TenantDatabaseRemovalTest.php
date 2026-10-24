<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantDatabaseRemover;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

/**
 * Real PostgreSQL databases: a dedicated tenant database is created, then removed
 * with and without a backup. Needs pg_dump / pg_restore to be installed.
 */
function removalPgToolsDirectory(): ?string
{
    $candidates = array_filter([
        (string) env('PG_DUMP_BINARY_PATH', ''),
        ...glob('/usr/local/opt/postgresql@*/bin') ?: [],
        ...glob('/opt/homebrew/opt/postgresql@*/bin') ?: [],
        ...glob('/usr/lib/postgresql/*/bin') ?: [],
    ]);

    foreach ($candidates as $directory) {
        if (is_executable($directory.'/pg_dump') && is_executable($directory.'/pg_restore')) {
            return $directory;
        }
    }

    $found = mb_trim((string) shell_exec('command -v pg_dump 2>/dev/null'));

    return $found !== '' ? dirname($found) : null;
}

function removalDatabaseExists(string $database): bool
{
    $row = DB::connection('landlord')->selectOne('select 1 as present from pg_database where datname = ?', [$database]);

    return $row !== null;
}

function removalNewTenant(string $slug, string $mode = 'db_per_tenant'): Tenant
{
    $tenant = Tenant::factory()->create([
        'slug' => $slug.'-'.uniqid(),
        'isolation_mode' => $mode,
        'db_driver' => 'pgsql',
    ]);

    if ($mode === 'db_per_tenant') {
        app(TenantProvisioner::class)->provision($tenant);
    }

    return $tenant->refresh();
}

beforeEach(function (): void {
    refreshTenantDatabases();

    $this->pgTools = removalPgToolsDirectory();

    if ($this->pgTools !== null) {
        Config::set('database.connections.landlord.dump.dump_binary_path', $this->pgTools);
    }

    $this->createdFiles = [];
    $this->dbsToDrop = [];
});

afterEach(function (): void {
    foreach ($this->createdFiles ?? [] as $file) {
        @unlink($file);
    }

    foreach ($this->dbsToDrop ?? [] as $database) {
        dropTenantDatabase($database);
    }
});

test('removing a dedicated tenant database with backup dumps it first and then drops it', function (): void {
    $this->pgTools ?? test()->markTestSkipped('pg_dump is not installed.');

    $tenant = removalNewTenant('rm-backup');
    $database = $tenant->meta['database'];
    $this->dbsToDrop[] = $database;
    expect(removalDatabaseExists($database))->toBeTrue();

    $result = app(TenantDatabaseRemover::class)->remove($tenant, true);
    $this->createdFiles[] = (string) $result['backup'];

    expect($result['dropped'])->toBeTrue();
    expect($result['database'])->toBe($database);
    expect(removalDatabaseExists($database))->toBeFalse();

    expect($result['backup'])->not->toBeNull();
    expect(is_file($result['backup']))->toBeTrue();
    expect(filesize($result['backup']))->toBeGreaterThan(0);
    expect(decoct(fileperms($result['backup']) & 0777))->toBe('600');

    // The backup is a restorable dump that contains the tenant's tables.
    $listing = (string) shell_exec(escapeshellarg($this->pgTools.'/pg_restore').' --list '.escapeshellarg($result['backup']));
    expect($listing)->toContain('migrations');
});

test('removing a dedicated tenant database without backup drops it and writes no file', function (): void {
    $tenant = removalNewTenant('rm-nobackup');
    $database = $tenant->meta['database'];
    $this->dbsToDrop[] = $database;

    $directory = app(TenantDatabaseRemover::class)->backupDirectory();
    $before = glob($directory.'/'.$database.'-*') ?: [];

    $result = app(TenantDatabaseRemover::class)->remove($tenant, false);

    expect($result['dropped'])->toBeTrue();
    expect($result['backup'])->toBeNull();
    expect(removalDatabaseExists($database))->toBeFalse();
    expect(glob($directory.'/'.$database.'-*') ?: [])->toBe($before);
});

test('a failed backup leaves the database untouched and nothing is dropped', function (): void {
    $tenant = removalNewTenant('rm-fail');
    $database = $tenant->meta['database'];
    $this->dbsToDrop[] = $database;

    Config::set('database.connections.landlord.dump.dump_binary_path', '/nonexistent-directory');

    expect(fn () => app(TenantDatabaseRemover::class)->remove($tenant, true))
        ->toThrow(DomainException::class, 'nothing was removed');

    expect(removalDatabaseExists($database))->toBeTrue();
});

test('the remover refuses to touch the landlord database or any database not owned by the tenant', function (): void {
    $landlordDatabase = (string) config('database.connections.landlord.database');

    $tenant = removalNewTenant('rm-guard', 'shared');
    $tenant->isolation_mode = 'db_per_tenant';

    foreach ([$landlordDatabase, 'postgres', 'tenant_not_this_tenants_database'] as $unsafeName) {
        $tenant->meta = ['database' => $unsafeName];

        expect(fn () => app(TenantDatabaseRemover::class)->remove($tenant, false))
            ->toThrow(DomainException::class, 'Refusing to remove');
    }

    expect(removalDatabaseExists($landlordDatabase))->toBeTrue();
});

test('a shared tenant has no database of its own so nothing is dropped', function (): void {
    $tenant = removalNewTenant('rm-shared', 'shared');

    $result = app(TenantDatabaseRemover::class)->remove($tenant, true);

    expect($result)->toBe(['dropped' => false, 'database' => null, 'backup' => null]);
});

test('the tenant delete endpoint backs up by default, can skip the backup, and validates the option', function (): void {
    $this->pgTools ?? test()->markTestSkipped('pg_dump is not installed.');

    Artisan::call('db:seed', ['--class' => 'RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'PermissionsSeeder']);

    $admin = User::factory()->create();
    $admin->assignRole('Superadmin');

    // Default: backup then delete.
    $withBackup = removalNewTenant('rm-http1');
    $this->dbsToDrop[] = $withBackup->meta['database'];

    $response = $this->actingAs($admin)->deleteJson(route('tenants.destroy', $withBackup->uuid));
    $response->assertOk()->assertJson(['database_dropped' => true]);

    $backupName = $response->json('database_backup');
    expect($backupName)->toStartWith($withBackup->meta['database']);
    $this->createdFiles[] = app(TenantDatabaseRemover::class)->backupDirectory().'/'.$backupName;
    expect(is_file(end($this->createdFiles)))->toBeTrue();
    expect(removalDatabaseExists($withBackup->meta['database']))->toBeFalse();
    expect(Tenant::query()->whereKey($withBackup->id)->exists())->toBeFalse();

    // Explicit delete: no backup.
    $noBackup = removalNewTenant('rm-http2');
    $this->dbsToDrop[] = $noBackup->meta['database'];

    $this->actingAs($admin)
        ->deleteJson(route('tenants.destroy', $noBackup->uuid), ['database_action' => 'delete'])
        ->assertOk()
        ->assertJson(['database_dropped' => true, 'database_backup' => null]);
    expect(removalDatabaseExists($noBackup->meta['database']))->toBeFalse();

    // Invalid option is rejected and nothing is deleted.
    $kept = removalNewTenant('rm-http3');
    $this->dbsToDrop[] = $kept->meta['database'];

    $this->actingAs($admin)
        ->deleteJson(route('tenants.destroy', $kept->uuid), ['database_action' => 'nuke'])
        ->assertStatus(422);
    expect(removalDatabaseExists($kept->meta['database']))->toBeTrue();
    expect(Tenant::query()->whereKey($kept->id)->exists())->toBeTrue();
});

test('the tenant delete endpoint keeps the tenant and its database when the backup fails', function (): void {
    Artisan::call('db:seed', ['--class' => 'RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'PermissionsSeeder']);

    $admin = User::factory()->create();
    $admin->assignRole('Superadmin');

    $tenant = removalNewTenant('rm-http-fail');
    $this->dbsToDrop[] = $tenant->meta['database'];

    Config::set('database.connections.landlord.dump.dump_binary_path', '/nonexistent-directory');

    $this->actingAs($admin)
        ->deleteJson(route('tenants.destroy', $tenant->uuid))
        ->assertStatus(422)
        ->assertJson(['status' => 'error']);

    expect(removalDatabaseExists($tenant->meta['database']))->toBeTrue();
    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue();
});

test('a user without the delete permission cannot delete a tenant', function (): void {
    $user = User::factory()->create();
    $tenant = removalNewTenant('rm-http-denied', 'shared');

    $this->actingAs($user)->deleteJson(route('tenants.destroy', $tenant->uuid))->assertForbidden();

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue();
});

test('tenants:delete removes the tenant and its database from the command line', function (): void {
    $tenant = removalNewTenant('rm-cli');
    $database = $tenant->meta['database'];
    $this->dbsToDrop[] = $database;

    $this->artisan('tenants:delete', ['tenant' => $tenant->slug, '--no-backup' => true, '--force' => true])
        ->assertSuccessful();

    expect(removalDatabaseExists($database))->toBeFalse();
    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeFalse();

    $this->artisan('tenants:delete', ['tenant' => 'does-not-exist', '--force' => true])->assertFailed();
});

test('the application PostgreSQL dumper still targets the selected database with TLS connection options', function (): void {
    $this->pgTools ?? test()->markTestSkipped('pg_dump is not installed.');
    $file = tempnam(sys_get_temp_dir(), 'udw-configured-dump-');
    $this->createdFiles[] = $file;
    $dumper = Spatie\Backup\Tasks\Backup\DbDumperFactory::createFromConnection('landlord');
    $dumper->includeTables(['migrations']);
    $dumper->dumpToFile($file);

    $sql = file_get_contents($file);
    expect($sql)->toContain('CREATE TABLE public.migrations', 'COPY public.migrations');
});
