<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceBillingAccess;
use App\Http\Middleware\TrackActiveUser;
use App\Models\Tenant;
use App\Models\TenantBillingAccess;
use App\Models\TenantBillingAgreement;
use App\Models\TenantBillingMonthlyUser;
use App\Models\User;
use App\Services\Tenancy\BillingAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(now()->startOfMonth()->addDays(3));
    $this->member = User::factory()->create();
    $this->billingTenant = setActiveTenantForTest($this->member);
    $this->access = app(BillingAccessService::class);
    $this->adopt = function (int $seats = 2, bool $future = false, array $packages = []): TenantBillingAgreement {
        return TenantBillingAgreement::query()->create([
            'tenant_id' => $this->billingTenant->id,
            'effective_from' => $future ? today()->addMonth()->startOfMonth() : today()->startOfMonth(),
            'plan_slug' => 'essentials', 'currency' => 'GHS',
            'editor_seats' => $seats, 'setup_required' => false,
            'snapshot' => ['hybrid' => config('usage-pricing.hybrid'), 'meters' => config('usage-pricing.meters'), 'selected_packages' => $packages],
        ]);
    };

    Route::middleware(['web', 'auth'])->get('/hybrid-access-unknown', fn () => response('allowed'))->name('hybrid.unknown');
    Route::middleware(['api', 'auth:sanctum'])->get('/api/hybrid-access-unknown', fn () => response('allowed'))->name('api.hybrid.unknown');
    Route::middleware(['web', 'auth'])->get('/hybrid-access-forbidden', fn () => abort(403))->name('hybrid.forbidden');
    Route::middleware(['web', 'auth'])->post('/hybrid-access-livewire', fn () => response('mutated'))->name('livewire.billing-test');
    Route::middleware('web')->get('/hybrid-access-public', fn () => response('public'))->name('hybrid.public');
    $this->prepareOwnAccess = function (): void {
        ($this->adopt)(2, false, ['hr', 'collaboration', 'quality']);
        foreach (['hrms-core', 'document-management', 'incident-management', 'forms'] as $module) {
            App\Models\TenantModule::query()->updateOrCreate(['tenant_id' => $this->billingTenant->id, 'module_slug' => $module], ['is_enabled' => true]);
        }
        foreach (['hrms.leave.view', 'hrms.leave.request', 'documents.view', 'forms.view', 'incidents.create', 'incidents.view'] as $permission) {
            App\Models\Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'test']);
            $this->member->givePermissionTo($permission);
        }
        $this->access->assign($this->billingTenant, $this->member, 'self_service');
        $this->actingAs($this->member);
    };

});

test('legacy authenticated access passes through without assignments or monthly billing', function (): void {
    $this->actingAs($this->member)->get('/hybrid-access-unknown')->assertOk();
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
});

test('future adoption permits preparing assignments without changing current access or billing', function (): void {
    ($this->adopt)(1, true);
    $this->access->assign($this->billingTenant, $this->member, 'website_editor');
    expect($this->access->level($this->billingTenant, $this->member))->toBe('website_editor');
    $this->actingAs($this->member)->get('/hybrid-access-unknown')->assertOk();
    $this->access->recordActivity($this->billingTenant, $this->member);
    expect($this->access->counts($this->billingTenant, now()))->toBe(['self_service' => 0, 'operational' => 0]);
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
});

test('unassigned members are denied before recording activity', function (): void {
    ($this->adopt)();
    $this->actingAs($this->member)->get('/hybrid-access-unknown')->assertForbidden();
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
});

test('lower levels cannot access unknown web or sanctum routes', function (string $level): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, $level);
    $this->actingAs($this->member)->get('/hybrid-access-unknown')->assertForbidden();
    Laravel\Sanctum\Sanctum::actingAs($this->member);
    $this->getJson('/api/hybrid-access-unknown')->assertForbidden();
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
})->with(['website_editor', 'self_service']);

test('unknown livewire components are denied for every assigned level', function (string $level): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, $level);
    $this->actingAs($this->member)->postJson('/hybrid-access-livewire')->assertForbidden();
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
})->with(BillingAccessService::LEVELS);

test('public content remains accessible to guests and unassigned signed in users', function (): void {
    ($this->adopt)();
    $this->get('/sample-product')->assertOk();
    $this->actingAs($this->member)->get('/sample-product')->assertOk();
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
});

test('own notifications permit self service but downstream permission denial is never metered', function (): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, 'self_service');
    $this->actingAs($this->member)->get('/hybrid-access-forbidden')->assertForbidden();
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
    $this->get('/notifications')->assertOk();
    expect($this->access->counts($this->billingTenant, now()))->toBe(['self_service' => 1, 'operational' => 0]);
});

test('monthly activity promotes immediately despite the daily cache and never downgrades', function (): void {
    ($this->adopt)();
    Cache::put('active_user_'.$this->billingTenant->id.'_'.$this->member->id.'_'.today()->format('Y-m-d'), true, now()->endOfDay());
    $this->access->assign($this->billingTenant, $this->member, 'self_service');
    $this->actingAs($this->member)->get('/notifications')->assertOk();
    $this->access->assign($this->billingTenant, $this->member, 'operational');
    $this->get('/hybrid-access-unknown')->assertOk();
    $this->access->assign($this->billingTenant, $this->member, 'self_service');
    $this->get('/notifications')->assertOk();
    expect($this->access->counts($this->billingTenant, now()))->toBe(['self_service' => 0, 'operational' => 1]);
    expect(TenantBillingMonthlyUser::query()->where('tenant_id', $this->billingTenant->id)->count())->toBe(1);
    expect($this->access->counts($this->billingTenant, now()->subMonth()))->toBe(['self_service' => 0, 'operational' => 0]);
});

test('website editors are not billed as paid active users', function (): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, 'website_editor');
    $this->access->recordActivity($this->billingTenant, $this->member);
    expect($this->access->counts($this->billingTenant, now()))->toBe(['self_service' => 0, 'operational' => 0]);
    expect(TenantBillingMonthlyUser::query()->sole()->level)->toBe('website_editor');
});

test('editor seat assignment is capped and reassignment is idempotent', function (): void {
    ($this->adopt)(1);
    $other = User::factory()->create();
    $this->billingTenant->users()->attach($other->id);
    $this->access->assign($this->billingTenant, $this->member, 'website_editor');
    $this->access->assign($this->billingTenant, $this->member, 'website_editor');
    expect(fn () => $this->access->assign($this->billingTenant, $other, 'website_editor'))->toThrow(ValidationException::class);
    expect(TenantBillingAccess::query()->count())->toBe(1);
});

test('future lower editor capacity limits new assignments immediately', function (): void {
    ($this->adopt)(3);
    ($this->adopt)(1, true);
    $other = User::factory()->create();
    $this->billingTenant->users()->attach($other->id);
    $this->access->assign($this->billingTenant, $this->member, 'website_editor');
    expect(fn () => $this->access->assign($this->billingTenant, $other, 'website_editor'))->toThrow(ValidationException::class);
});

test('invalid levels and nonmembers cannot be assigned or recorded', function (): void {
    ($this->adopt)();
    $outsider = User::factory()->create();
    expect(fn () => $this->access->assign($this->billingTenant, $this->member, 'administrator'))->toThrow(ValidationException::class);
    expect(fn () => $this->access->assign($this->billingTenant, $outsider, 'operational'))->toThrow(ValidationException::class);
    TenantBillingAccess::query()->create(['tenant_id' => $this->billingTenant->id, 'user_id' => $outsider->id, 'level' => 'operational']);
    expect($this->access->level($this->billingTenant, $outsider))->toBeNull();
    $this->access->recordActivity($this->billingTenant, $outsider);
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
});

test('cross tenant assignment cannot grant access in another organisation', function (): void {
    ($this->adopt)();
    $otherTenant = Tenant::factory()->create();
    $otherTenant->users()->attach($this->member->id);
    TenantBillingAccess::query()->create(['tenant_id' => $otherTenant->id, 'user_id' => $this->member->id, 'level' => 'operational']);
    expect($this->access->level($this->billingTenant, $this->member))->toBeNull();
    $this->actingAs($this->member)->get('/hybrid-access-unknown')->assertForbidden();
});

test('landlord support is exempt from assignments and monthly billing', function (): void {
    ($this->adopt)();
    $role = App\Models\Role::query()->firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web', 'tenant_id' => null], ['slug' => 'superadmin']);
    $this->member->assignRole($role);
    $this->actingAs($this->member)->get('/hybrid-access-unknown')->assertOk();
    $this->access->recordActivity($this->billingTenant, $this->member);
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
});

test('self service cannot use HR approvals or unrestricted document lists', function (): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, 'self_service');
    Route::middleware(['web', 'auth'])->get('/hybrid-document-list', fn () => response('documents'))->name('document-management.index');
    Route::middleware(['web', 'auth'])->post('/hybrid-hr-approve', fn () => response('approved'))->name('hrms-core.leave-requests.approve');
    $this->actingAs($this->member)->get('/hybrid-document-list')->assertForbidden();
    $this->post('/hybrid-hr-approve')->assertForbidden();
});

test('billing middleware precedes usage tracking in both transport groups', function (): void {
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    foreach (['web', 'api'] as $group) {
        $stack = $kernel->getMiddlewareGroups()[$group];
        expect(array_search(EnforceBillingAccess::class, $stack, true))->toBeLessThan(array_search(TrackActiveUser::class, $stack, true));
    }
});

test('active agreements override legacy module grants while preserving enable state', function (): void {
    App\Models\TenantModule::query()->updateOrCreate(['tenant_id' => $this->billingTenant->id, 'module_slug' => 'hrms-core'], ['is_enabled' => true]);
    App\Models\TenantFeature::query()->create(['tenant_id' => $this->billingTenant->id, 'feature_key' => 'module-hrms-core', 'enabled' => true]);
    ($this->adopt)();
    $manager = app(App\Services\ModuleManager::class);
    expect($manager->isEnabledForTenant('hrms-core', $this->billingTenant))->toBeFalse();
    App\Models\TenantModule::query()->updateOrCreate(['tenant_id' => $this->billingTenant->id, 'module_slug' => 'forms'], ['is_enabled' => false]);
    expect($manager->isEnabledForTenant('forms', $this->billingTenant))->toBeFalse();
    App\Models\TenantModule::query()->where('tenant_id', $this->billingTenant->id)->where('module_slug', 'forms')->update(['is_enabled' => true]);
    Cache::forget('tenant.'.$this->billingTenant->id.'.module.forms');
    expect($manager->isEnabledForTenant('forms', $this->billingTenant))->toBeTrue();
});

test('website editors can use included forms subject to existing RBAC', function (): void {
    ($this->adopt)();
    App\Models\TenantModule::query()->updateOrCreate(['tenant_id' => $this->billingTenant->id, 'module_slug' => 'forms'], ['is_enabled' => true]);
    $this->access->assign($this->billingTenant, $this->member, 'website_editor');
    Route::middleware(['web', 'auth', 'module:forms'])->get('/hybrid-forms', fn () => response('forms'))->name('forms.billing-test');
    Route::middleware(['web', 'auth', 'module:forms'])->get('/hybrid-forms-rbac', fn () => abort(403))->name('forms.billing-test-denied');
    $this->actingAs($this->member)->get('/hybrid-forms')->assertOk();
    $this->get('/hybrid-forms-rbac')->assertForbidden();
});

test('own profile is safe but another member profile is denied at lower tiers', function (string $level): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, $level);
    $other = User::factory()->create();
    $middleware = app(EnforceBillingAccess::class);
    foreach ([$this->member, $other] as $profile) {
        $request = Illuminate\Http\Request::create('/profile/'.$profile->uuid);
        $route = new Illuminate\Routing\Route('GET', '/profile/{user}', ['middleware' => ['auth'], 'uses' => App\Http\Controllers\Admin\ProfileController::class.'@index']);
        $route->setContainer(app());
        $route->name('profile.index');
        $route->bind($request);
        $route->setParameter('user', $profile);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $this->member);
        if ($profile->is($this->member)) {
            expect($middleware->handle($request, fn () => response('profile'))->getStatusCode())->toBe(200);
        } else {
            expect(fn () => $middleware->handle($request, fn () => response('profile')))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
        }
    }
})->with(['website_editor', 'self_service']);

test('operational users cannot bypass module purchase checks on controller routes without module middleware', function (): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, 'operational');
    Route::middleware(['web', 'auth'])->get('/hybrid-unpurchased-facilities', [App\Modules\FacilitiesManagement\Http\Controllers\Web\FacilitiesManagementWebController::class, 'index']);
    $this->actingAs($this->member)->get('/hybrid-unpurchased-facilities')->assertForbidden();
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
});

test('ownerless API keys cannot bypass access assignments', function (): void {
    ($this->adopt)();
    $request = Illuminate\Http\Request::create('/api/llm/chat', 'POST');
    $request->attributes->set('auth_type', 'api_key');
    $request->setUserResolver(fn () => null);
    expect(fn () => app(EnforceBillingAccess::class)->handle($request, fn () => response('chat')))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('access cannot be prepared without an explicit agreement', function (): void {
    expect(fn () => $this->access->assign($this->billingTenant, $this->member, 'operational'))->toThrow(ValidationException::class);
    expect(TenantBillingAccess::query()->count())->toBe(0);
});

test('revoking access frees the editor seat and preserves the monthly highest charge', function (): void {
    ($this->adopt)(1);
    $this->access->assign($this->billingTenant, $this->member, 'operational');
    $this->access->recordActivity($this->billingTenant, $this->member);
    $this->access->assign($this->billingTenant, $this->member, 'website_editor');
    $this->access->revoke($this->billingTenant, $this->member);
    $this->access->revoke($this->billingTenant, $this->member);
    expect($this->access->level($this->billingTenant, $this->member))->toBeNull();
    expect($this->access->counts($this->billingTenant, now()))->toBe(['self_service' => 0, 'operational' => 1]);
    $other = User::factory()->create();
    $this->billingTenant->users()->attach($other->id);
    $this->access->assign($this->billingTenant, $other, 'website_editor');
    $this->actingAs($this->member)->get('/hybrid-access-unknown')->assertForbidden();
    expect(fn () => $this->access->revoke(Tenant::factory()->create(), $other))->toThrow(ValidationException::class);
});

test('real API key requests authenticate before billing enforcement without preexisting context', function (string $level, bool $allowed, string $authMiddleware): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, $level);
    $key = app(App\Services\Api\ApiKeyService::class)->generate($this->billingTenant->id, $this->member->id, 'Access ordering');
    Route::middleware(['api', $authMiddleware])->post('/api/hybrid-key-order', fn () => response()->json(['allowed' => true]));
    $route = app('router')->getRoutes()->match(Illuminate\Http\Request::create('/api/hybrid-key-order', 'POST'));
    $stack = app('router')->gatherRouteMiddleware($route);
    foreach ([
        [App\Http\Middleware\ResolveTenant::class, App\Http\Middleware\AuthenticateWithApiKey::class],
        [App\Http\Middleware\AuthenticateWithApiKey::class, Illuminate\Routing\Middleware\SubstituteBindings::class],
        [Illuminate\Routing\Middleware\SubstituteBindings::class, EnforceBillingAccess::class],
        [EnforceBillingAccess::class, App\Http\Middleware\MeterRequestUsage::class],
    ] as [$before, $after]) {
        expect(array_search($before, $stack, true))->toBeInt()->toBeLessThan(array_search($after, $stack, true));
    }
    Illuminate\Support\Facades\Session::flush();
    app(App\Services\Tenancy\TenantContext::class)->clear();
    app('auth')->forgetGuards();
    $response = $this->postJson('/api/hybrid-key-order', [], ['X-Api-Key' => $key['key']]);
    if ($allowed) {
        $response->assertOk();
        expect($this->access->counts($this->billingTenant, now()))->toBe(['self_service' => 0, 'operational' => 1]);
    } else {
        $response->assertForbidden();
        expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
    }
})->with([
    ['website_editor', false, 'auth_api_key'],
    ['self_service', false, App\Http\Middleware\AuthenticateWithApiKey::class],
    ['operational', true, 'auth_api_key'],
]);

test('borrowing a safe route name with a different action never grants self service access', function (string $name): void {
    ($this->prepareOwnAccess)();
    Route::middleware(['web', 'auth'])->get('/hybrid-borrowed-safe-name', fn () => response('leaked'))->name($name);
    $this->get('/hybrid-borrowed-safe-name')->assertForbidden();
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
})->with(['notifications.index', 'hrms-core.leave-requests.index', 'document-management.documents.show', 'forms.show', 'incident-management.incidents.index']);

test('self service leave list metrics and employee selection are restricted to the actor', function (): void {
    ($this->prepareOwnAccess)();
    $own = App\Modules\HrmsCore\Models\Employees\Employee::factory()->create(['tenant_id' => $this->billingTenant->id, 'user_id' => $this->member->id]);
    $other = App\Modules\HrmsCore\Models\Employees\Employee::factory()->create(['tenant_id' => $this->billingTenant->id]);
    $category = App\Modules\HrmsCore\Models\Leave\LeaveCategory::factory()->create(['tenant_id' => $this->billingTenant->id]);
    App\Modules\HrmsCore\Models\Leave\LeaveRequest::factory()->create(['tenant_id' => $this->billingTenant->id, 'employee_id' => $own->id, 'leave_category_id' => $category->id]);
    App\Modules\HrmsCore\Models\Leave\LeaveRequest::factory()->create(['tenant_id' => $this->billingTenant->id, 'employee_id' => $other->id, 'leave_category_id' => $category->id]);
    $this->get('/hrms-core/leave-requests')->assertOk()
        ->assertViewHas('leaveRequests', fn ($records) => $records->total() === 1 && $records->first()->employee_id === $own->id)
        ->assertViewHas('metrics', fn ($metrics) => $metrics['total'] === 1);
    $this->get('/hrms-core/leave-requests/create')->assertOk()
        ->assertViewHas('employees', fn ($employees) => $employees->modelKeys() === [$own->id]);
    $this->post('/hrms-core/leave-requests', [
        'employee_id' => $other->id, 'leave_category_id' => $category->id,
        'proposed_start_date' => now()->addDays(5)->toDateString(), 'proposed_end_date' => now()->addDays(6)->toDateString(),
    ])->assertForbidden();
    $this->post('/hrms-core/leave-requests', [
        'employee_id' => $own->id, 'leave_category_id' => $category->id,
        'proposed_start_date' => now()->addDays(5)->toDateString(), 'proposed_end_date' => now()->addDays(6)->toDateString(),
    ])->assertRedirect();
    expect(App\Modules\HrmsCore\Models\Leave\LeaveRequest::query()->where('employee_id', $own->id)->count())->toBe(2);
});

test('self service document library shows only published distributed documents and records own acknowledgement', function (): void {
    ($this->prepareOwnAccess)();
    $visible = App\Modules\DocumentManagement\Database\Factories\DocumentFactory::new()->create([
        'tenant_id' => $this->billingTenant->id, 'owner_id' => $this->member->uuid,
        'status' => 'published', 'visibility' => ['users' => [$this->member->uuid]],
    ]);
    $hidden = App\Modules\DocumentManagement\Database\Factories\DocumentFactory::new()->create([
        'tenant_id' => $this->billingTenant->id, 'owner_id' => User::factory()->create()->uuid,
        'status' => 'published', 'visibility' => ['is_private' => true],
    ]);
    $draft = App\Modules\DocumentManagement\Database\Factories\DocumentFactory::new()->create([
        'tenant_id' => $this->billingTenant->id, 'owner_id' => $this->member->uuid, 'status' => 'draft',
    ]);
    $this->get('/document-management/documents')->assertOk()
        ->assertViewHas('documents', fn ($documents) => $documents->total() === 1 && $documents->first()->id === $visible->id);
    $this->get('/document-management/documents/'.$visible->id)->assertOk()
        ->assertViewHas('tenantUsers', fn ($users) => $users->isEmpty())->assertViewHas('canApprove', false);
    $this->get('/document-management/documents/'.$hidden->id)->assertForbidden();
    $this->get('/document-management/documents/'.$draft->id)->assertForbidden();
    $this->post('/document-management/documents/'.$visible->id.'/acknowledge', ['user_id' => (string) User::factory()->create()->uuid])->assertRedirect();
    expect($visible->acknowledgements()->sole()->user_id)->toBe((string) $this->member->uuid);
    $this->post('/document-management/documents/'.$visible->id.'/approve')->assertForbidden();
});

test('self service documents permit current downloads but exclude unpublished version history', function (): void {
    ($this->prepareOwnAccess)();
    Illuminate\Support\Facades\Storage::fake('public');
    $document = App\Modules\DocumentManagement\Database\Factories\DocumentFactory::new()->create(['tenant_id' => $this->billingTenant->id, 'owner_id' => $this->member->uuid, 'status' => 'published']);
    $version = App\Modules\DocumentManagement\Models\DocumentVersion::query()->create([
        'tenant_id' => $this->billingTenant->id, 'document_id' => $document->id, 'version_number' => 2,
        'disk' => 'public', 'path' => 'current.txt', 'filename' => 'current.txt', 'uploaded_by_id' => $this->member->uuid,
    ]);
    $document->update(['current_version_id' => $version->id]);
    Illuminate\Support\Facades\Storage::disk('public')->put('current.txt', 'Published content');
    $this->get('/document-management/documents/'.$document->id.'/download')->assertOk()->assertDownload('current.txt');
    $this->get('/document-management/documents/'.$document->id.'/download/1')->assertNotFound();
});

test('self service incident reports cannot disclose others or inject assignment and lifecycle state', function (): void {
    ($this->prepareOwnAccess)();
    Illuminate\Support\Facades\Mail::fake();
    $own = App\Modules\IncidentManagement\Models\Incident::factory()->forTenant($this->billingTenant->id)->create(['reported_by_id' => $this->member->uuid]);
    $other = App\Modules\IncidentManagement\Models\Incident::factory()->forTenant($this->billingTenant->id)->create();
    $own->comments()->create(['tenant_id' => $this->billingTenant->id, 'user_id' => $this->member->uuid, 'body' => 'Private investigation note', 'is_internal' => true]);
    $this->get('/incident-management/incidents')->assertOk()->assertViewHas('incidents', fn ($incidents) => $incidents->total() === 1);
    $this->get('/incident-management/incidents/'.$other->id)->assertForbidden();
    $this->get('/incident-management/incidents/'.$own->id)->assertOk()->assertDontSee('Private investigation note')
        ->assertViewHas('activities', fn ($activities) => $activities->isEmpty());
    $this->post('/incident-management/incidents', [
        'title' => 'Own report', 'description' => 'My report', 'assigned_to_id' => (string) User::factory()->create()->uuid,
        'status_id' => $other->status_id, 'metadata' => ['private' => 'injected'], 'reported_by_id' => $other->reported_by_id,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $created = App\Modules\IncidentManagement\Models\Incident::query()->where('title', 'Own report')->sole();
    expect($created->reported_by_id)->toBe((string) $this->member->uuid)->and($created->assigned_to_id)->toBeNull()
        ->and($created->metadata)->toBeNull();
    $this->post('/incident-management/incidents/'.$created->id.'/assign', ['assigned_to_id' => (string) $this->member->uuid])->assertForbidden();
});

test('self service forms show respondent content and only the members own submissions', function (): void {
    ($this->prepareOwnAccess)();
    $form = App\Modules\Forms\Models\FormDefinition::query()->create([
        'tenant_id' => $this->billingTenant->id, 'created_by' => $this->member->uuid,
        'title' => 'Staff feedback', 'slug' => 'staff-feedback', 'type' => 'general',
        'target_audience' => 'internal_only', 'status' => 'published', 'allow_anonymous' => false,
    ]);
    App\Modules\Forms\Models\FormField::query()->create([
        'tenant_id' => $this->billingTenant->id, 'form_definition_id' => $form->id,
        'label' => 'Feedback', 'field_name' => 'feedback', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1,
    ]);
    $other = App\Modules\Forms\Models\FormSubmission::query()->create([
        'tenant_id' => $this->billingTenant->id, 'form_definition_id' => $form->id,
        'user_id' => (string) User::factory()->create()->uuid, 'responses_json' => ['feedback' => 'Someone elses private answer'], 'status' => 'submitted',
    ]);
    $this->get('/forms')->assertOk()->assertViewHas('metrics', fn ($metrics) => $metrics['total_submissions'] === 0)
        ->assertViewHas('forms', fn ($forms) => $forms->first()->submissions_count === 0);
    $this->get('/forms/'.$form->id)->assertOk()->assertViewIs('forms::public-form')->assertDontSee('Someone elses private answer');
    $this->get('/forms/'.$form->id.'/submissions/'.$other->id)->assertForbidden();
    $this->post('/forms/f/staff-feedback/submit', ['feedback' => 'My feedback', 'user_id' => $other->user_id])->assertRedirect();
    $own = $form->submissions()->where('user_id', $this->member->uuid)->sole();
    expect($own->responses_json)->toBe(['feedback' => 'My feedback']);
    $this->get('/forms/'.$form->id.'/submissions/'.$own->id)->assertOk()->assertSee('My feedback');
    $this->get('/forms/'.$form->id.'/results')->assertForbidden();
    $this->get('/forms/'.$form->id.'/export-csv')->assertForbidden();
});

test('self service cannot read draft forms or another departments forms even with broad form management permission', function (): void {
    ($this->prepareOwnAccess)();
    App\Models\Permission::query()->firstOrCreate(['name' => 'forms.manage', 'guard_name' => 'web'], ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'test']);
    $this->member->givePermissionTo('forms.manage');
    $department = App\Modules\HrmsCore\Models\Organization\Department::query()->create(['tenant_id' => $this->billingTenant->id, 'name' => 'Restricted']);
    foreach ([['draft', 'internal_only'], ['published', 'departmental']] as [$status, $audience]) {
        $form = App\Modules\Forms\Models\FormDefinition::query()->create([
            'tenant_id' => $this->billingTenant->id, 'created_by' => $this->member->uuid,
            'title' => $audience, 'slug' => $audience, 'type' => 'general', 'target_audience' => $audience,
            'target_department_id' => $department->id, 'status' => $status,
        ]);
        $response = $this->get('/forms/'.$form->id);
        if ($status === 'draft') {
            $response->assertNotFound();
        } else {
            $response->assertForbidden();
        }
    }
    $this->get('/forms')->assertOk()->assertViewHas('forms', fn ($forms) => $forms->total() === 0);
});

test('signed allowlisted pricing Livewire works for operational users while tampering and borrowed snapshots fail', function (): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, 'operational');
    $this->actingAs($this->member);
    $snapshot = [
        'data' => [],
        'memo' => ['name' => 'pricing-table', 'id' => 'billing-pricing', 'path' => 'pricing', 'method' => 'GET', 'children' => [], 'errors' => [], 'locale' => 'en'],
    ];
    $snapshot['memo']['release'] = Livewire\Features\SupportReleaseTokens\ReleaseToken::generate(
        app(Livewire\Mechanisms\ComponentRegistry::class)->getClass($snapshot['memo']['name'])
    );
    $snapshot['checksum'] = Livewire\Mechanisms\HandleComponents\Checksum::generate($snapshot);
    $request = Illuminate\Http\Request::create('/livewire/update', 'POST', ['components' => [[
        'snapshot' => json_encode($snapshot), 'updates' => ['operationalUsers' => 20], 'calls' => [],
    ]]]);
    $request->setUserResolver(fn () => $this->member);
    $route = new Illuminate\Routing\Route('POST', '/livewire/update', fn () => null);
    $route->setContainer(app());
    $route->name('livewire.update');
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    $middleware = app(EnforceBillingAccess::class);
    Livewire\Mechanisms\HandleComponents\Checksum::verify(json_decode($request->input('components.0.snapshot'), true));
    expect(mb_ltrim(app(Livewire\Mechanisms\ComponentRegistry::class)->getClass('pricing-table'), '\\'))->toBe(App\Livewire\PricingTable::class);
    expect(app(App\Services\ModuleManager::class)->isEnabledForTenant('core', $this->billingTenant))->toBeTrue();
    expect($middleware->handle($request, fn () => response('calculator'))->getStatusCode())->toBe(200);
    $snapshot['memo']['name'] = 'admin.user-profile-security';
    $request->merge(['components' => [['snapshot' => json_encode($snapshot), 'updates' => [], 'calls' => []]]]);
    expect(fn () => $middleware->handle($request, fn () => response('tampered')))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('signed security components remain limited to the current user and cannot change their model', function (): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, 'self_service');
    $this->actingAs($this->member);
    foreach ([[$this->member->id, [], true], [User::factory()->create()->id, [], false], [$this->member->id, ['user' => 123], false]] as [$userId, $updates, $allowed]) {
        $snapshot = [
            'data' => ['user' => [null, ['class' => $this->member->getMorphClass(), 'key' => $userId, 's' => 'mdl']]],
            'memo' => ['name' => 'admin.user-profile-security', 'id' => 'security'],
        ];
        $snapshot['memo']['release'] = Livewire\Features\SupportReleaseTokens\ReleaseToken::generate(
            app(Livewire\Mechanisms\ComponentRegistry::class)->getClass($snapshot['memo']['name'])
        );
        $snapshot['checksum'] = Livewire\Mechanisms\HandleComponents\Checksum::generate($snapshot);
        $request = Illuminate\Http\Request::create('/livewire/update', 'POST', ['components' => [[
            'snapshot' => json_encode($snapshot), 'updates' => $updates, 'calls' => [['method' => 'enableTwoFactor', 'params' => []]],
        ]]]);
        $request->setUserResolver(fn () => $this->member);
        $middleware = app(EnforceBillingAccess::class);
        if ($allowed) {
            expect($middleware->handle($request, fn () => response('own security'))->getStatusCode())->toBe(200);
        } else {
            expect(fn () => $middleware->handle($request, fn () => response('other security')))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
        }
    }
});

test('future agreements do not impose own record filtering on legacy controllers', function (): void {
    ($this->adopt)(2, true);
    $this->access->assign($this->billingTenant, $this->member, 'self_service');
    expect($this->access->isSelfService($this->member))->toBeFalse();
});

test('unassigned tenant members retain account recovery and logout without monthly activity', function (): void {
    ($this->adopt)();
    foreach ([
        ['POST', '/logout', 'logout', App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'],
        ['GET', '/two-factor-challenge', 'two-factor.challenge', App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'show'],
        ['GET', '/verify-email', 'verification.notice', App\Http\Controllers\Auth\EmailVerificationPromptController::class, '__invoke'],
        ['GET', '/profile/'.$this->member->uuid, 'profile.index', App\Http\Controllers\Admin\ProfileController::class, 'index'],
    ] as [$verb, $path, $name, $controller, $method]) {
        $request = Illuminate\Http\Request::create($path, $verb);
        $route = new Illuminate\Routing\Route($verb, $path, ['uses' => $controller.'@'.$method, 'middleware' => ['auth']]);
        $route->setContainer(app());
        $route->name($name);
        $route->bind($request);
        if ($name === 'profile.index') {
            $route->setParameter('user', $this->member);
        }
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $this->member);
        expect(app(EnforceBillingAccess::class)->handle($request, fn () => response('recovery'))->getStatusCode())->toBe(200);
        expect($request->attributes->get('hybrid_billing_access_allowed'))->toBeNull();
    }
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
    $this->actingAs($this->member)->post('/logout')->assertRedirect();
    $this->assertGuest();
});

test('self service dashboards redirect to purchased own scope pages and navigation excludes management', function (): void {
    ($this->prepareOwnAccess)();
    $this->get('/dashboard')->assertRedirectToRoute('hrms-core.leave-requests.index');
    $request = Illuminate\Http\Request::create('/hrms-core/leave-requests');
    $request->setUserResolver(fn () => $this->member);
    $navigation = app(App\Services\Navigation\WorkspaceNavigation::class)->forRequest($request);
    $labels = collect($navigation['sidebar']['tenant_groups'][0]['items'])->pluck('label')->all();
    expect($labels)->toContain('My leave', 'Published documents', 'Forms', 'My incident reports')
        ->not->toContain('Employees', 'HR Overview', 'Users', 'Roles', 'Approvals');
    expect($navigation['sidebar']['dashboards'])->toBe([])->and($navigation['sidebar']['modules'])->toBe([]);
    App\Models\TenantModule::query()->where('tenant_id', $this->billingTenant->id)->where('module_slug', 'hrms-core')->update(['is_enabled' => false]);
    Cache::forget('tenant.'.$this->billingTenant->id.'.module.hrms-core');
    expect(collect($this->access->workspaceLinks($this->billingTenant, $this->member))->pluck('label')->all())->not->toContain('My leave');
});

test('website editors land on the CMS and see only website links permitted by RBAC', function (): void {
    ($this->adopt)();
    App\Models\TenantModule::query()->updateOrCreate(['tenant_id' => $this->billingTenant->id, 'module_slug' => 'cms-core'], ['is_enabled' => true]);
    App\Models\Permission::query()->firstOrCreate(['name' => 'cms.posts.view', 'guard_name' => 'web'], ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'test']);
    $this->member->givePermissionTo('cms.posts.view');
    $this->access->assign($this->billingTenant, $this->member, 'website_editor');
    $this->actingAs($this->member)->get('/dashboard')->assertRedirectToRoute('cms-core.index');
    $links = $this->access->workspaceLinks($this->billingTenant, $this->member);
    expect(array_column($links, 'label'))->toBe(['Website', 'My profile']);
});

test('the real tenant website extension is included only for its own organisation and requires enabled CMS', function (): void {
    $this->billingTenant->update(['slug' => 'thyroid-ghana-foundation']);
    $agreement = ($this->adopt)();
    foreach (['cms-core', 'site-thyroid-ghana-foundation'] as $slug) {
        App\Models\TenantModule::query()->updateOrCreate(['tenant_id' => $this->billingTenant->id, 'module_slug' => $slug], ['is_enabled' => true]);
    }
    $manager = app(App\Services\ModuleManager::class);
    expect($manager->isWebsiteExtensionForTenant('site-thyroid-ghana-foundation', $this->billingTenant))->toBeTrue();
    expect($manager->isEnabledForTenant('site-thyroid-ghana-foundation', $this->billingTenant))->toBeTrue();
    $other = Tenant::factory()->create();
    TenantBillingAgreement::query()->create([
        'tenant_id' => $other->id, 'effective_from' => today()->startOfMonth(), 'plan_slug' => 'essentials',
        'currency' => 'GHS', 'editor_seats' => 2, 'setup_required' => false, 'snapshot' => $agreement->snapshot,
    ]);
    App\Models\TenantModule::query()->create(['tenant_id' => $other->id, 'module_slug' => 'site-thyroid-ghana-foundation', 'is_enabled' => true]);
    expect($manager->isWebsiteExtensionForTenant('site-thyroid-ghana-foundation', $other))->toBeFalse();
    expect($manager->isEnabledForTenant('site-thyroid-ghana-foundation', $other))->toBeFalse();
    expect($manager->isWebsiteExtensionForTenant('facilities-management', $this->billingTenant))->toBeFalse();
    expect($manager->canEnableForTenant('facilities-management', $this->billingTenant))->toBeFalse();
    App\Models\TenantModule::query()->where('tenant_id', $this->billingTenant->id)->where('module_slug', 'cms-core')->update(['is_enabled' => false]);
    Cache::forget('tenant.'.$this->billingTenant->id.'.module.cms-core');
    expect($manager->isEnabledForTenant('site-thyroid-ghana-foundation', $this->billingTenant))->toBeFalse();
});

test('adopted operational members are redirected away from the global dashboard', function (): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, 'operational');
    $this->actingAs($this->member)->get('/dashboard')
        ->assertRedirectToRoute('tenant.dashboard', ['subdomain' => $this->billingTenant->slug]);
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
});

test('operational dashboard redirect applies only to the real global dashboard action', function (): void {
    ($this->adopt)();
    $this->access->assign($this->billingTenant, $this->member, 'operational');
    Route::middleware(['web', 'auth'])->get('/hybrid-borrowed-dashboard', fn () => response('ordinary endpoint'))->name('dashboard');
    $this->actingAs($this->member)->get('/hybrid-borrowed-dashboard')->assertOk();
});

test('a CMS licence never grants API read or writes without the resource permission', function (string $level): void {
    ($this->adopt)();
    App\Models\TenantModule::query()->updateOrCreate(['tenant_id' => $this->billingTenant->id, 'module_slug' => 'cms-core'], ['is_enabled' => true]);
    $this->access->assign($this->billingTenant, $this->member, $level);
    Laravel\Sanctum\Sanctum::actingAs($this->member);
    $this->getJson('/api/cms-core/v1/settings')->assertForbidden();
    $this->postJson('/api/cms-core/v1/settings', ['group' => 'website', 'key' => 'billing_access_check', 'value' => 'denied'])->assertForbidden();
    $this->postJson('/api/cms-core/v1/media', [])->assertForbidden();
    expect(App\Modules\CmsCore\Models\Setting::query()->where('key', 'billing_access_check')->exists())->toBeFalse();
    expect(TenantBillingMonthlyUser::query()->count())->toBe(0);
})->with(['website_editor', 'operational']);

test('an authorised CMS role permits its resource API while unrelated resources stay denied', function (string $level): void {
    ($this->adopt)();
    App\Models\TenantModule::query()->updateOrCreate(['tenant_id' => $this->billingTenant->id, 'module_slug' => 'cms-core'], ['is_enabled' => true]);
    $this->access->assign($this->billingTenant, $this->member, $level);
    foreach (['cms.settings.view', 'cms.settings.manage'] as $permission) {
        App\Models\Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'cms']);
    }
    $role = App\Models\Role::query()->create(['name' => 'CMS settings editor', 'guard_name' => 'web', 'tenant_id' => $this->billingTenant->id]);
    $role->givePermissionTo(['cms.settings.view', 'cms.settings.manage']);
    $this->member->assignRole($role);
    Laravel\Sanctum\Sanctum::actingAs($this->member);
    $this->postJson('/api/cms-core/v1/settings', ['group' => 'website', 'key' => 'billing_access_check', 'value' => 'permitted'])->assertCreated();
    $setting = App\Modules\CmsCore\Models\Setting::query()->where('key', 'billing_access_check')->sole();
    $this->getJson('/api/cms-core/v1/settings')->assertOk();
    $this->putJson('/api/cms-core/v1/settings/'.$setting->getRouteKey(), ['group' => 'website', 'key' => 'billing_access_check', 'value' => 'updated'])->assertOk();
    $this->getJson('/api/cms-core/v1/media')->assertForbidden();
    $this->postJson('/api/cms-core/v1/media', [])->assertForbidden();
})->with(['website_editor', 'operational']);

test('CMS API resource checks fail closed for unknown routes and borrowed names', function (): void {
    ($this->adopt)();
    App\Models\TenantModule::query()->updateOrCreate(['tenant_id' => $this->billingTenant->id, 'module_slug' => 'cms-core'], ['is_enabled' => true]);
    $this->access->assign($this->billingTenant, $this->member, 'operational');
    App\Models\Permission::query()->firstOrCreate(['name' => 'cms.posts.view', 'guard_name' => 'web'], ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'cms']);
    $this->member->givePermissionTo('cms.posts.view');
    Route::middleware(['api', 'auth:sanctum', 'module:cms-core'])->get('/api/hybrid-cms-unknown', fn () => response('leak'))->name('api.cms-core.v1.unknown.index');
    Route::middleware(['api', 'auth:sanctum', 'module:cms-core'])->get('/api/hybrid-cms-borrowed', fn () => response('leak'))->name('api.cms-core.v1.posts.index');
    Laravel\Sanctum\Sanctum::actingAs($this->member);
    $this->getJson('/api/hybrid-cms-unknown')->assertForbidden();
    $this->getJson('/api/hybrid-cms-borrowed')->assertForbidden();
});

test('legacy CMS API permissions remain unchanged until agreement adoption', function (): void {
    App\Models\TenantModule::query()->updateOrCreate(['tenant_id' => $this->billingTenant->id, 'module_slug' => 'cms-core'], ['is_enabled' => true]);
    Laravel\Sanctum\Sanctum::actingAs($this->member);
    $this->postJson('/api/cms-core/v1/settings', ['group' => 'website', 'key' => 'legacy_access_check', 'value' => 'legacy'])->assertCreated();
});

test('self service scope survives access revocation during an admitted request', function (): void {
    ($this->prepareOwnAccess)();
    $request = Illuminate\Http\Request::create('/document-management/documents', 'GET');
    $route = new Illuminate\Routing\Route('GET', '/document-management/documents', [
        'uses' => App\Modules\DocumentManagement\Http\Controllers\Web\DocumentLibraryController::class.'@index',
        'middleware' => ['auth'],
    ]);
    $route->name('document-management.documents.index');
    $route->setContainer(app());
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => $this->member);
    app()->instance('request', $request);

    app(EnforceBillingAccess::class)->handle($request, function () {
        $this->access->revoke($this->billingTenant, $this->member);
        expect($this->access->isSelfService($this->member))->toBeTrue();
        expect($this->access->isSelfService(User::factory()->create()))->toBeFalse();

        return response('scoped');
    });
});

test('unassigned members can use signed Paystack recovery without a paid access level', function (): void {
    ($this->adopt)();
    $this->actingAs($this->member);
    $invoice = App\Models\Invoice::query()->create([
        'tenant_id' => $this->billingTenant->id, 'number' => 'RECOVERY-'.Illuminate\Support\Str::uuid(),
        'period_start' => today(), 'period_end' => today(), 'due_at' => today()->addDay(),
        'status' => App\Models\Invoice::STATUS_ISSUED, 'currency' => 'GHS', 'subtotal' => 2, 'tax_total' => 0, 'total' => 2,
    ]);
    $links = app(App\Services\Billing\InvoicePaymentLinkService::class);
    $parameters = ['reference' => 'pending-ref', 'access_code' => 'pending-code'];
    config(['services.paystack.secret_key' => 'test-key']);
    Illuminate\Support\Facades\Http::fake(['api.paystack.co/transaction/verify/pending-ref' => Illuminate\Support\Facades\Http::response([
        'data' => ['id' => 123, 'status' => 'pending'],
    ])]);
    $this->get($links->signedUrl($invoice, 'billing.payment.checkout', $parameters))->assertOk();
    $this->postJson($links->signedUrl($invoice, 'billing.payment.check', $parameters))->assertOk()->assertJson(['status' => 'pending']);
    expect(TenantBillingMonthlyUser::query()->where('tenant_id', $this->billingTenant->id)->count())->toBe(0);
});
