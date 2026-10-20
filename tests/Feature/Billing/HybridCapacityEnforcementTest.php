<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceHybridCapacity;
use App\Models\HybridCapacityCounter;
use App\Models\Tenant;
use App\Models\TenantBillingAgreement;
use App\Services\Tenancy\HybridCapacityService;
use App\Services\Tenancy\TenantStorageManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

function adoptCapacityAgreement(Tenant $tenant, int $files = 1024, int $bandwidth = 1024, int $database = 1048576): TenantBillingAgreement
{
    return TenantBillingAgreement::query()->create([
        'tenant_id' => $tenant->id,
        'effective_from' => now()->startOfMonth()->toDateString(),
        'plan_slug' => 'website-essentials',
        'currency' => 'GHS',
        'editor_seats' => 2,
        'setup_required' => false,
        'snapshot' => ['hybrid' => ['website_plans' => ['website-essentials' => [
            'files_gib' => $files / 1073741824,
            'bandwidth_gib' => $bandwidth / 1073741824,
            'database_gib' => $database / 1073741824,
            'sites' => 1, 'domains' => 1,
        ]]], 'meters' => [], 'selected_packages' => []],
    ]);
}

function capacityRequest(string $name = 'cms-core.public.home', string $method = 'GET'): Request
{
    $request = Request::create('/capacity-test', $method);
    $route = new Route([$method], '/capacity-test', fn () => null);
    $route->name($name);
    $route->bind($request);
    $request->setRouteResolver(fn (): Route => $route);
    app()->instance('request', $request);

    return $request;
}

beforeEach(function (): void {
    $this->tenant = setActiveTenantForTest();
    Storage::fake('capacity-local');
    config([
        'filesystems.default' => 'capacity-local',
        'filesystems.disks.capacity-local' => ['driver' => 'local', 'root' => Storage::disk('capacity-local')->path('')],
        'filesystems.disks.s3' => null,
    ]);
    app(TenantStorageManager::class)->configure($this->tenant);
    $this->capacity = app(HybridCapacityService::class);
});

it('preserves legacy file writes without an agreement or audit', function (): void {
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/legacy.txt', 'legacy');
    expect(Storage::disk('tenant')->get('cms/legacy.txt'))->toBe('legacy');
    expect(HybridCapacityCounter::query()->count())->toBe(0);
});

it('fails closed before audit and permits the exact file boundary', function (): void {
    adoptCapacityAgreement($this->tenant, files: 8);
    expect(fn () => $this->capacity->writeFile($this->tenant, 'tenant', 'cms/file.txt', '12345678'))
        ->toThrow(HttpException::class, 'audited');
    $this->capacity->auditStorage($this->tenant);
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/file.txt', '12345678');
    expect(fn () => $this->capacity->writeFile($this->tenant, 'tenant', 'cms/denied.txt', '9'))
        ->toThrow(HttpException::class, 'capacity exceeded');
    Storage::disk('tenant')->assertMissing('cms/denied.txt');
    expect(HybridCapacityCounter::query()->where('meter', 'files')->first()->bytes)->toBe(8);
});

it('allows deletion and replacement to recover capacity', function (): void {
    adoptCapacityAgreement($this->tenant, files: 8);
    $this->capacity->auditStorage($this->tenant);
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/file.txt', '12345678');
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/file.txt', '12');
    expect(HybridCapacityCounter::query()->where('meter', 'files')->first()->bytes)->toBe(2);
    expect($this->capacity->deleteFile($this->tenant, 'tenant', 'cms/file.txt'))->toBeTrue();
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/new.txt', '12345678');
    expect(Storage::disk('tenant')->get('cms/new.txt'))->toBe('12345678');
});

it('accumulates bandwidth independently per tenant and calendar month', function (): void {
    adoptCapacityAgreement($this->tenant, bandwidth: 8);
    $other = Tenant::factory()->create();
    adoptCapacityAgreement($other, bandwidth: 8);
    $this->capacity->reserveBandwidth($this->tenant, 3);
    $this->capacity->reserveBandwidth($this->tenant, 5);
    expect(fn () => $this->capacity->reserveBandwidth($this->tenant, 1))->toThrow(HttpException::class);
    $this->capacity->reserveBandwidth($other, 8);
    expect(HybridCapacityCounter::query()->where('meter', 'bandwidth')->count())->toBe(2);
    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth());
    $this->capacity->reserveBandwidth($this->tenant, 8);
    expect(HybridCapacityCounter::query()->where('tenant_id', $this->tenant->id)->where('meter', 'bandwidth')->count())->toBe(2);
});

it('meters streamed public responses before delivery and excludes private module responses', function (): void {
    adoptCapacityAgreement($this->tenant, bandwidth: 8);
    $middleware = app(EnforceHybridCapacity::class);
    $response = $middleware->handle(capacityRequest(), fn () => response()->stream(function (): void {
        echo '1234';
        echo '5678';
    }));
    ob_start();
    $response->sendContent();
    expect(ob_get_clean())->toBe('12345678');
    expect(fn () => $middleware->handle(capacityRequest(), fn () => response('9')))->toThrow(HttpException::class);
    $private = $middleware->handle(capacityRequest('hrms.employees.index'), fn () => response(str_repeat('p', 32)));
    expect($private->getContent())->toHaveLength(32);
    expect(HybridCapacityCounter::query()->where('meter', 'bandwidth')->first()->bytes)->toBe(8);
});

it('does not charge failed streamed generators', function (): void {
    adoptCapacityAgreement($this->tenant, bandwidth: 8);
    expect(fn () => app(EnforceHybridCapacity::class)->handle(capacityRequest(), fn () => response()->stream(function (): void {
        echo '1234';
        throw new RuntimeException('broken generator');
    })))->toThrow(RuntimeException::class, 'broken generator');
    expect(HybridCapacityCounter::query()->where('meter', 'bandwidth')->sum('bytes'))->toBe(0);
});

it('counts public binary downloads and omits HEAD bodies', function (): void {
    adoptCapacityAgreement($this->tenant, bandwidth: 8);
    $path = tempnam(sys_get_temp_dir(), 'capacity');
    file_put_contents($path, '12345678');
    try {
        $middleware = app(EnforceHybridCapacity::class);
        $middleware->handle(capacityRequest(), fn () => response()->download($path));
        $middleware->handle(capacityRequest(method: 'HEAD'), fn () => response()->download($path));
        expect(HybridCapacityCounter::query()->where('meter', 'bandwidth')->first()->bytes)->toBe(8);
    } finally {
        unlink($path);
    }
});

it('measures tenant rows including indirect CMS revisions without counting another tenant', function (): void {
    $connection = DB::connection('tenant');
    $before = $this->capacity->databaseBytes($this->tenant);
    $other = Tenant::factory()->create();
    $connection->table('settings')->insert([
        'uuid' => (string) Illuminate\Support\Str::uuid(), 'tenant_id' => $other->id, 'key' => 'capacity-test', 'value' => json_encode(str_repeat('x', 4096)),
    ]);
    expect($this->capacity->databaseBytes($this->tenant))->toBe($before);
    $connection->table('settings')->insert([
        'uuid' => (string) Illuminate\Support\Str::uuid(), 'tenant_id' => $this->tenant->id, 'key' => 'capacity-test', 'value' => json_encode(str_repeat('x', 4096)),
    ]);
    expect($this->capacity->databaseBytes($this->tenant))->toBeGreaterThan($before + 4096);
});

it('rolls back excessive database mutations and their reserved files', function (): void {
    adoptCapacityAgreement($this->tenant, database: 0);
    $this->capacity->auditStorage($this->tenant);
    capacityRequest(method: 'POST');
    expect(fn () => $this->capacity->databaseMutation($this->tenant, function (): void {
        $this->capacity->writeFile($this->tenant, 'tenant', 'cms/rollback.txt', '1234');
        DB::connection('tenant')->table('settings')->insert([
            'uuid' => (string) Illuminate\Support\Str::uuid(), 'tenant_id' => $this->tenant->id, 'key' => 'denied-growth', 'value' => json_encode(str_repeat('x', 1024)),
        ]);
    }))->toThrow(HttpException::class, 'database capacity exceeded');
    Storage::disk('tenant')->assertMissing('cms/rollback.txt');
    expect(DB::connection('tenant')->table('settings')->where('tenant_id', $this->tenant->id)->where('key', 'denied-growth')->exists())->toBeFalse();
    expect(HybridCapacityCounter::query()->where('meter', 'files')->first()->bytes)->toBe(0);
});

it('allows database deletion while over quota', function (): void {
    adoptCapacityAgreement($this->tenant, database: 0);
    $connection = DB::connection('tenant');
    $connection->table('settings')->insert([
        'uuid' => (string) Illuminate\Support\Str::uuid(), 'tenant_id' => $this->tenant->id, 'key' => 'recover', 'value' => json_encode('large content'),
    ]);
    $this->capacity->databaseMutation($this->tenant, fn () => $connection->table('settings')
        ->where('tenant_id', $this->tenant->id)->where('key', 'recover')->delete());
    expect($connection->table('settings')->where('tenant_id', $this->tenant->id)->where('key', 'recover')->exists())->toBeFalse();
});

it('counts UTF8 and binary file bytes rather than characters', function (): void {
    adoptCapacityAgreement($this->tenant, files: 4);
    $this->capacity->auditStorage($this->tenant);
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/unicode.txt', 'é');
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/binary.txt', "\x00\xff");
    expect(HybridCapacityCounter::query()->where('meter', 'files')->first()->bytes)->toBe(4);
    expect(fn () => $this->capacity->writeFile($this->tenant, 'tenant', 'cms/denied.txt', 'a'))->toThrow(HttpException::class);
});

it('counts UTF8 and binary streamed bytes exactly', function (): void {
    adoptCapacityAgreement($this->tenant, bandwidth: 4);
    $middleware = app(EnforceHybridCapacity::class);
    $middleware->handle(capacityRequest(), fn () => response('é'));
    $response = $middleware->handle(capacityRequest(), fn () => response()->stream(function (): void {
        echo "\x00\xff";
    }));
    ob_start();
    $response->sendContent();
    expect(ob_get_clean())->toBe("\x00\xff");
    expect(HybridCapacityCounter::query()->where('meter', 'bandwidth')->first()->bytes)->toBe(4);
    expect(fn () => $middleware->handle(capacityRequest(), fn () => response('a')))->toThrow(HttpException::class);
});

it('guards actual optimizer uploads and rolls back rejected writes', function (): void {
    adoptCapacityAgreement($this->tenant, files: 4);
    $this->capacity->auditStorage($this->tenant);
    $optimizer = app(App\Modules\CmsCore\Services\ImageOptimizer::class);
    $file = Illuminate\Http\UploadedFile::fake()->createWithContent('file.txt', 'éé');
    $result = $optimizer->store($file, 'cms/media');
    expect($result['size_bytes'])->toBe(4);
    expect(fn () => $optimizer->store(Illuminate\Http\UploadedFile::fake()->createWithContent('denied.txt', 'x'), 'cms/media'))
        ->toThrow(HttpException::class);
    expect(Storage::disk('tenant')->allFiles('cms/media'))->toHaveCount(1);
});

it('excludes private module storage from the website audit', function (): void {
    adoptCapacityAgreement($this->tenant, files: 4);
    Storage::disk('tenant')->put('hrms/private.bin', str_repeat('x', 2048));
    Storage::disk('tenant')->put('cms/page.txt', 'é');
    expect($this->capacity->auditStorage($this->tenant))->toBe(2);
    expect($this->capacity->storageAudited($this->tenant))->toBeTrue();
});

it('includes PostgreSQL CMS child rows and Forms while excluding unrelated tenant tables', function (): void {
    $post = App\Modules\CmsCore\Models\Post::factory()->forTenant($this->tenant->id)->create();
    $before = $this->capacity->databaseBytes($this->tenant);
    DB::connection('tenant')->table('post_meta')->insert([
        'post_id' => $post->id, 'key' => 'bytes', 'value' => json_encode(str_repeat('é', 2048)),
    ]);
    expect($this->capacity->databaseBytes($this->tenant))->toBeGreaterThan($before + 4096);
    $beforeForm = $this->capacity->databaseBytes($this->tenant);
    App\Modules\Forms\Models\FormDefinition::query()->create([
        'tenant_id' => $this->tenant->id, 'title' => str_repeat('F', 128),
        'slug' => 'capacity-form', 'description' => str_repeat('x', 2048),
        'created_by' => (string) Illuminate\Support\Str::uuid(),
    ]);
    expect($this->capacity->databaseBytes($this->tenant))->toBeGreaterThan($beforeForm + 2048);
    $beforeCounter = $this->capacity->databaseBytes($this->tenant);
    HybridCapacityCounter::query()->create([
        'tenant_id' => $this->tenant->id, 'meter' => 'unrelated', 'period' => 'lifetime', 'bytes' => 9999999,
    ]);
    expect($this->capacity->databaseBytes($this->tenant))->toBe($beforeCounter);
});

it('enforces Forms mutations even on API-named requests', function (): void {
    adoptCapacityAgreement($this->tenant, database: 0);
    expect(fn () => app(EnforceHybridCapacity::class)->handle(capacityRequest('api.forms.store', 'POST'), function () {
        App\Modules\Forms\Models\FormDefinition::query()->create([
            'tenant_id' => $this->tenant->id, 'title' => 'Denied', 'slug' => 'denied-form',
            'created_by' => (string) Illuminate\Support\Str::uuid(),
        ]);

        return response('created', 201);
    }))->toThrow(HttpException::class, 'database capacity exceeded');
    expect(App\Modules\Forms\Models\FormDefinition::query()->where('slug', 'denied-form')->exists())->toBeFalse();
});

it('restores files and counters when a downstream request returns an error', function (): void {
    adoptCapacityAgreement($this->tenant);
    $this->capacity->auditStorage($this->tenant);
    expect(fn () => app(EnforceHybridCapacity::class)->handle(capacityRequest('cms-core.media.store', 'POST'), function () {
        $this->capacity->writeFile($this->tenant, 'tenant', 'cms/error.txt', '1234');

        return response('failed', 422);
    }))->toThrow(Illuminate\Http\Exceptions\HttpResponseException::class);
    Storage::disk('tenant')->assertMissing('cms/error.txt');
    expect(HybridCapacityCounter::query()->where('meter', 'files')->first()->bytes)->toBe(0);
    expect(request()->attributes->has('hybrid.file_rollback'))->toBeFalse();
});

it('does not measure website database rows for an ordinary public GET', function (): void {
    adoptCapacityAgreement($this->tenant);
    $capacityQueries = [];
    DB::listen(function (Illuminate\Database\Events\QueryExecuted $event) use (&$capacityQueries): void {
        if (str_contains($event->sql, 'capacity_bytes')) {
            $capacityQueries[] = $event->sql;
        }
    });
    app(EnforceHybridCapacity::class)->handle(capacityRequest(), fn () => response('public page'));
    expect($capacityQueries)->toBeEmpty();
});

it('restores deleted website files if the enclosing mutation fails', function (): void {
    adoptCapacityAgreement($this->tenant);
    $this->capacity->auditStorage($this->tenant);
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/recover.txt', 'éé');
    capacityRequest(method: 'DELETE');
    expect(fn () => $this->capacity->databaseMutation($this->tenant, function (): void {
        $this->capacity->deleteFile($this->tenant, 'tenant', 'cms/recover.txt');
        throw new RuntimeException('downstream failure');
    }))->toThrow(RuntimeException::class, 'downstream failure');
    expect(Storage::disk('tenant')->get('cms/recover.txt'))->toBe('éé');
    expect(HybridCapacityCounter::query()->where('meter', 'files')->first()->bytes)->toBe(4);
});

it('meters public form pages but excludes internal form pages', function (): void {
    adoptCapacityAgreement($this->tenant, bandwidth: 4);
    $form = App\Modules\Forms\Models\FormDefinition::query()->create([
        'tenant_id' => $this->tenant->id, 'title' => 'Form', 'slug' => 'meter-form',
        'target_audience' => 'public', 'created_by' => (string) Illuminate\Support\Str::uuid(),
    ]);
    $request = capacityRequest('forms.public.show');
    $request->route()->setParameter('slug', $form->slug);
    app(EnforceHybridCapacity::class)->handle($request, fn () => response('éé'));
    $form->update(['target_audience' => 'internal_only']);
    $request = capacityRequest('forms.public.show');
    $request->route()->setParameter('slug', $form->slug);
    app(EnforceHybridCapacity::class)->handle($request, fn () => response(str_repeat('private', 10)));
    expect(HybridCapacityCounter::query()->where('meter', 'bandwidth')->first()->bytes)->toBe(4);
});

it('meters favicon manifest and sized site icon responses', function (string $name): void {
    adoptCapacityAgreement($this->tenant, bandwidth: 4);
    app(EnforceHybridCapacity::class)->handle(capacityRequest($name), fn () => response('éé'));
    expect(HybridCapacityCounter::query()->where('meter', 'bandwidth')->first()->bytes)->toBe(4);
})->with(['site-icon.favicon', 'site-icon.favicon-file', 'site-icon.apple', 'site-icon.serve', 'site-icon.manifest']);

it('requires a fresh audit when a legacy baseline predates active adoption', function (): void {
    $this->capacity->auditStorage($this->tenant);
    $this->travel(2)->seconds();
    adoptCapacityAgreement($this->tenant);
    expect($this->capacity->storageAudited($this->tenant))->toBeFalse();
    expect(fn () => $this->capacity->writeFile($this->tenant, 'tenant', 'cms/stale.txt', 'x'))
        ->toThrow(HttpException::class, 'audited');
    $this->capacity->auditStorage($this->tenant);
    expect($this->capacity->storageAudited($this->tenant))->toBeTrue();
});

it('validates adopted API media registration against real tenant files', function (): void {
    adoptCapacityAgreement($this->tenant);
    $this->capacity->auditStorage($this->tenant);
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/owned.txt', 'éé');
    $attributes = $this->capacity->mediaAttributes($this->tenant, [
        'disk' => 'tenant', 'path' => 'cms/owned.txt', 'size_bytes' => 0,
    ]);
    expect($attributes['size_bytes'])->toBe(4);
    expect($attributes['checksum_sha256'])->toBe(hash('sha256', 'éé'));
    foreach (['../other.txt', 'cms/../other.txt', 'cms/../../other.txt', 'cms/%2e%2e/other.txt', 'hrms/private.txt', 'cms\\..\\other.txt'] as $path) {
        expect(fn () => $this->capacity->mediaAttributes($this->tenant, ['disk' => 'tenant', 'path' => $path]))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    }
    expect(fn () => $this->capacity->mediaAttributes($this->tenant, ['disk' => 'public', 'path' => 'cms/owned.txt']))
        ->toThrow(Illuminate\Validation\ValidationException::class);
    expect(fn () => $this->capacity->mediaAttributes($this->tenant, ['disk' => 'tenant', 'path' => 'cms/missing.txt']))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('deletes media derivatives and releases their reserved capacity', function (): void {
    adoptCapacityAgreement($this->tenant);
    $this->capacity->auditStorage($this->tenant);
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/original.txt', '1234');
    $this->capacity->writeFile($this->tenant, 'tenant', 'cms/variant.txt', 'é');
    $media = App\Modules\CmsCore\Models\Media::factory()->forTenant($this->tenant->id)->create([
        'disk' => 'tenant', 'path' => 'cms/original.txt', 'size_bytes' => 4,
    ]);
    $media->variants()->create([
        'tenant_id' => $this->tenant->id, 'variant' => 'thumb', 'disk' => 'tenant', 'path' => 'cms/variant.txt', 'size_bytes' => 2,
    ]);
    $this->capacity->databaseMutation($this->tenant, fn () => $this->capacity->deleteMediaFiles($this->tenant, $media));
    Storage::disk('tenant')->assertMissing('cms/original.txt');
    Storage::disk('tenant')->assertMissing('cms/variant.txt');
    expect($media->variants()->count())->toBe(0);
    expect(HybridCapacityCounter::query()->where('meter', 'files')->first()->bytes)->toBe(0);
});

it('restores failed file mutations before releasing tenant serialization', function (): void {
    adoptCapacityAgreement($this->tenant);
    $this->capacity->auditStorage($this->tenant);
    $baseline = DB::connection('landlord')->transactionLevel();
    $restorationLevel = null;
    try {
        $this->capacity->databaseMutation($this->tenant, function () use (&$restorationLevel): void {
            $this->capacity->writeFile($this->tenant, 'tenant', 'cms/failed.txt', 'bytes');
            $callbacks = request()->attributes->get('hybrid.file_rollback', []);
            $callbacks[] = function () use (&$restorationLevel): void {
                $restorationLevel = DB::connection('landlord')->transactionLevel();
            };
            request()->attributes->set('hybrid.file_rollback', $callbacks);
            throw new RuntimeException('Simulated mutation failure.');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulated mutation failure.');
    }
    expect($restorationLevel)->toBeGreaterThan($baseline);
    Storage::disk('tenant')->assertMissing('cms/failed.txt');
});
