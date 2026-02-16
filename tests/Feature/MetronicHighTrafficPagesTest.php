<?php

declare(strict_types=1);

use App\Livewire\Admin\TenantHealthCheck as TenantHealthCheckComponent;
use App\Livewire\Admin\UserProfileSecurity as UserProfileSecurityComponent;
use App\Models\Feature;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

it('renders the admin dashboard with the metronic layout', function (): void {
    $user = User::factory()->create();

    Permission::firstOrCreate([
        'name' => 'access dashboard',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'dashboard',
    ]);

    setPermissionsTeamId(null);
    $user->givePermissionTo('access dashboard');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Login Activity')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the custom error layout with metronic assets and button styles', function (): void {
    $html = view('errors.custom-layout')->render();

    expect($html)->toContain('assets/metronic/css/styles.css');
    expect($html)->toContain('kt-btn kt-btn-primary');
    expect($html)->not->toContain('btn btn-lg btn-primary');
});

it('renders the admin users index with the metronic layout', function (): void {
    $user = User::factory()->create();

    Permission::firstOrCreate([
        'name' => 'read user',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'user',
    ]);

    setPermissionsTeamId(null);
    $user->givePermissionTo('read user');

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertSuccessful()
        ->assertSee('User Directory')
        ->assertSee('data-kt-modal="true"', false)
        ->assertSee('kt-checkbox row-check', false)
        ->assertDontSee('modal fade', false)
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the livewire user profile security widget with metronic button classes', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    Livewire::test(UserProfileSecurityComponent::class, ['user' => $user])
        ->assertSee('Two-Factor Authentication')
        ->assertSee('Enable 2FA')
        ->assertSee('kt-btn kt-btn-primary kt-btn-sm', false);
});

it('renders the livewire tenant health check widget with metronic layout classes', function (): void {
    $tenant = Tenant::factory()->create();

    Livewire::test(TenantHealthCheckComponent::class, ['tenant' => $tenant])
        ->assertSee('Infrastructure Health')
        ->assertSee('Run Full Health Check')
        ->assertSee('kt-btn kt-btn-sm kt-btn-primary', false);
});

it('renders the admin roles index with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('roles.index'))
        ->assertSuccessful()
        ->assertSee('Roles & Permissions')
        ->assertSee('kt-checkbox row-check', false)
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin roles create view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('roles.create'))
        ->assertSuccessful()
        ->assertSee('Create Global Role')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin roles edit view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $editableRole = Role::create([
        'name' => 'Operations',
        'guard_name' => 'web',
    ]);

    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('roles.edit', $editableRole->id))
        ->assertSuccessful()
        ->assertSee('Edit Global Role')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin user-management roles index view with the metronic layout', function (): void {
    $role = Role::create([
        'name' => 'Ops Lead',
        'guard_name' => 'web',
    ]);

    $permission = Permission::firstOrCreate([
        'name' => 'read user',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'user',
    ]);

    setPermissionsTeamId(null);
    $role->givePermissionTo($permission);

    $html = view('admin.user-management.roles.index', [
        'roles' => Role::with('permissions')->get(),
    ])->render();

    expect($html)->toContain('Access Control');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the admin user-management roles edit view with the metronic layout', function (): void {
    $role = Role::create([
        'name' => 'Ops Editor',
        'guard_name' => 'web',
    ]);

    $permission = Permission::firstOrCreate([
        'name' => 'update user',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'user',
    ]);

    app('view')->share('errors', new ViewErrorBag());

    $html = view('admin.user-management.roles.edit', [
        'role' => $role,
        'permissions' => ['user' => collect([$permission])],
        'existing_permissions' => collect([$permission]),
    ])->render();

    expect($html)->toContain('Role Permissions');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the features index with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('features.index'))
        ->assertSuccessful()
        ->assertSee('Features & Capabilities', false)
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the features create view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('features.create'))
        ->assertSuccessful()
        ->assertSee('Define New System Feature')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the features edit view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $feature = Feature::factory()->create();

    $this->actingAs($user)
        ->get(route('features.edit', $feature->id))
        ->assertSuccessful()
        ->assertSee('Edit Feature')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the packages index with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('packages.index'))
        ->assertSuccessful()
        ->assertSee('Subscription Packages')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the packages create view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('packages.create'))
        ->assertSuccessful()
        ->assertSee('Create Subscription Package')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the packages edit view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $package = Package::factory()->create();

    $this->actingAs($user)
        ->get(route('packages.edit', $package->id))
        ->assertSuccessful()
        ->assertSee('Edit Plan')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the audit trail activity logs with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('audit-trail.activity-logs.index'))
        ->assertSuccessful()
        ->assertSee('Activity Logs')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the audit trail login history with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('audit-trail.login-history.index'))
        ->assertSuccessful()
        ->assertSee('Login History')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin billing transactions index with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    Transaction::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.billing.transactions.index'))
        ->assertSuccessful()
        ->assertSee('Global Transactions')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin billing subscriptions index with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    Subscription::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.billing.subscriptions.index'))
        ->assertSuccessful()
        ->assertSee('Global Subscriptions')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin rate cards index with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    Tax::create([
        'name' => 'VAT',
        'rate' => 12.5,
        'priority' => 1,
        'is_compound' => false,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('admin.billing.rate-cards.index'))
        ->assertSuccessful()
        ->assertSee('Global Rate Cards & Taxes')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin invoices index with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $tenant = Tenant::factory()->create();
    Invoice::create([
        'tenant_id' => $tenant->id,
        'number' => 'INV-1001',
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'due_at' => now()->addDays(7),
        'status' => Invoice::STATUS_DRAFT,
        'currency' => 'USD',
        'subtotal' => 200.00,
        'tax_total' => 0.00,
        'total' => 200.00,
    ]);

    $this->actingAs($user)
        ->get(route('admin.billing.invoices.index'))
        ->assertSuccessful()
        ->assertSee('System Invoices')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin invoices create view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    Tenant::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.billing.invoices.create'))
        ->assertSuccessful()
        ->assertSee('Create Ad-Hoc Invoice')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin invoices show view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->assignRole($role);

    $tenant = Tenant::factory()->create();
    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'number' => 'INV-2001',
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'due_at' => now()->addDays(14),
        'status' => Invoice::STATUS_DRAFT,
        'currency' => 'USD',
        'subtotal' => 350.00,
        'tax_total' => 0.00,
        'total' => 350.00,
        'tax_details' => [],
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Usage charges',
        'quantity' => 1,
        'unit_price' => 350.00,
        'subtotal' => 350.00,
    ]);

    $this->actingAs($user)
        ->get(route('admin.billing.invoices.show', $invoice->id))
        ->assertSuccessful()
        ->assertSee('Invoice #'.$invoice->number)
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin tenants index with the metronic layout', function (): void {
    $user = User::factory()->create();

    Permission::firstOrCreate([
        'name' => 'read tenant',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'tenant',
    ]);

    setPermissionsTeamId(null);
    $user->givePermissionTo('read tenant');

    $this->actingAs($user)
        ->get(route('tenants.index'))
        ->assertSuccessful()
        ->assertSee('Tenants')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin tenants create view with the metronic layout', function (): void {
    $user = User::factory()->create();

    Permission::firstOrCreate([
        'name' => 'create tenant',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'tenant',
    ]);

    setPermissionsTeamId(null);
    $user->givePermissionTo('create tenant');

    $this->actingAs($user)
        ->get(route('tenants.create'))
        ->assertSuccessful()
        ->assertSee('Add Tenant')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin tenants edit view with the metronic layout', function (): void {
    $user = User::factory()->create();

    Permission::firstOrCreate([
        'name' => 'update tenant',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'tenant',
    ]);

    setPermissionsTeamId(null);
    $user->givePermissionTo('update tenant');

    $tenant = Tenant::factory()->create();

    $this->actingAs($user)
        ->get(route('tenants.edit', $tenant->uuid))
        ->assertSuccessful()
        ->assertSee('Edit Tenant')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin tenants show view with the metronic layout', function (): void {
    $user = User::factory()->create();

    Permission::firstOrCreate([
        'name' => 'read tenant',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'tenant',
    ]);

    setPermissionsTeamId(null);
    $user->givePermissionTo('read tenant');

    $tenant = Tenant::factory()->create();

    $this->actingAs($user)
        ->get(route('tenants.show', $tenant->uuid))
        ->assertSuccessful()
        ->assertSee($tenant->name)
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the admin tenants team create view with the metronic layout', function (): void {
    $user = User::factory()->create();

    Permission::firstOrCreate([
        'name' => 'create team',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'team',
    ]);

    setPermissionsTeamId(null);
    $user->givePermissionTo('create team');

    $tenant = Tenant::factory()->create();

    $this->actingAs($user)
        ->get(route('tenants.team.create', $tenant->uuid))
        ->assertSuccessful()
        ->assertSee('Add Team Member')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the usage analytics view with the metronic layout', function (): void {
    $html = view('admin.analytics.usage', [
        'days' => 7,
        'tenants' => collect(),
        'selectedTenantId' => null,
        'start_date' => now()->subDays(7)->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'requestTrend' => ['labels' => ['01-01 00:00'], 'data' => [0]],
        'statusBreakdown' => ['labels' => ['200'], 'data' => [0]],
        'peakHours' => array_fill(0, 24, 0),
        'storageTrend' => ['labels' => ['01-01'], 'data' => [0]],
        'dbTrend' => ['labels' => ['01-01'], 'data' => [0]],
        'usageMetrics' => App\Enum\UsageMetric::cases(),
        'breadcrumbs' => [],
    ])->render();

    expect($html)->toContain('Usage Analytics');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the tenant dashboard with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('tenant.dashboard', ['subdomain' => $tenant->slug]))
        ->assertSuccessful()
        ->assertSee("Welcome to {$tenant->name}!")
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the tenant roles index with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Permission::firstOrCreate([
        'name' => 'read role',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'role',
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('read role');

    $role = Role::create([
        'name' => 'Operations Lead',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('tenant.roles.index', ['subdomain' => $tenant->slug]))
        ->assertSuccessful()
        ->assertSee('Organization Roles')
        ->assertSee($role->name)
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the tenant roles create view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Permission::firstOrCreate([
        'name' => 'create role',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'role',
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('create role');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('tenant.roles.create', ['subdomain' => $tenant->slug]))
        ->assertSuccessful()
        ->assertSee('Create Custom Role')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the tenant roles show view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Permission::firstOrCreate([
        'name' => 'read role',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'role',
    ]);

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo('read role');

    $role = Role::create([
        'name' => 'Clinical Lead',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('tenant.roles.show', ['subdomain' => $tenant->slug, 'role' => $role->id]))
        ->assertSuccessful()
        ->assertSee($role->name)
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the tenant subscription index view with the metronic layout', function (): void {
    $subscription = Subscription::factory()->create();

    $html = view('admin.tenants.subscription.index', [
        'subscriptions' => collect([$subscription]),
    ])->render();

    expect($html)->toContain('Tenant Subscriptions');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the tenant subscription show view with the metronic layout', function (): void {
    $subscription = Subscription::factory()->create();
    $log = Transaction::factory()->make([
        'status' => 'succeeded',
        'provider_transaction_id' => 'tx_test_123',
    ]);

    app('view')->share('errors', new ViewErrorBag());

    $html = view('admin.tenants.subscription.show', [
        'subscription' => $subscription,
        'subscription_logs' => collect([$log]),
        'subscription_transaction' => $log,
    ])->render();

    expect($html)->toContain('Subscription Overview');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the tenant subscription invoice preview with the metronic layout', function (): void {
    $tenant = Tenant::factory()->create();

    $invoice = (object) [
        'id' => 1,
        'tenant' => $tenant,
        'amount' => 12500,
        'credit_balance' => 500,
        'description' => 'Subscription payment',
        'created_at' => now(),
        'paid_at' => now(),
        'status' => 'paid',
    ];

    $html = view('admin.tenants.subscription.invoice-preview', [
        'subscriptionInvoice' => $invoice,
    ])->render();

    expect($html)->toContain('Invoice #1');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('does not render legacy bootstrap ui class patterns on key metronic routes', function (): void {
    $admin = User::factory()->create();
    $superadminRole = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $admin->assignRole($superadminRole);

    $tenantUser = User::factory()->create();
    $tenant = setActiveTenantForTest($tenantUser);

    Permission::firstOrCreate([
        'name' => 'access dashboard',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'dashboard',
    ]);

    setPermissionsTeamId($tenant->id);
    $tenantUser->givePermissionTo('access dashboard');

    $adminRoutes = [
        route('dashboard'),
        route('users.index'),
        route('roles.index'),
        route('tenants.index'),
        route('audit-trail.activity-logs.index'),
    ];

    setPermissionsTeamId(null);

    foreach ($adminRoutes as $url) {
        $content = $this->actingAs($admin)->get($url)->assertSuccessful()->getContent();

        expect($content)->not->toContain('btn btn-');
        expect($content)->not->toContain('modal fade');
        expect($content)->not->toContain('form-control form-control');
        expect($content)->not->toContain('data-bs-');
    }

    setPermissionsTeamId($tenant->id);

    $tenantContent = $this->actingAs($tenantUser)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('tenant.dashboard', ['subdomain' => $tenant->slug]))
        ->assertSuccessful()
        ->getContent();

    expect($tenantContent)->not->toContain('btn btn-');
    expect($tenantContent)->not->toContain('modal fade');
    expect($tenantContent)->not->toContain('form-control form-control');
    expect($tenantContent)->not->toContain('data-bs-');
});

it('renders the admin user show view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $subjectUser = User::factory()->create();

    Permission::firstOrCreate([
        'name' => 'read user',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'user',
    ]);

    setPermissionsTeamId(null);
    $user->givePermissionTo('read user');

    $this->actingAs($user)
        ->get(route('users.show', $subjectUser->uuid))
        ->assertSuccessful()
        ->assertSee($subjectUser->displayName())
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the tenant finance view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    URL::defaults(['subdomain' => $tenant->slug]);

    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'succeeded',
        'type' => 'payment',
        'amount' => 4500,
    ]);

    $transactions = Transaction::query()->paginate(1);

    $stats = [
        'total_volume' => 4500,
        'transaction_count' => 1,
    ];

    $monthlyStats = collect([
        ['label' => 'Jan', 'amount' => 4500],
    ]);

    $html = view('tenant.finance.index', [
        'stats' => $stats,
        'monthlyStats' => $monthlyStats,
        'transactions' => $transactions,
    ])->render();

    expect($html)->toContain('Finance & Sales');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the tenant settings view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    URL::defaults(['subdomain' => $tenant->slug]);

    app('view')->share('errors', new ViewErrorBag());

    $html = view('tenant.settings.index', [
        'tenant' => $tenant,
    ])->render();

    expect($html)->toContain('Organization Settings');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the tenant billing settings view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    URL::defaults(['subdomain' => $tenant->slug]);

    $html = view('tenant.settings.billing', [
        'billingEmail' => 'billing@example.com',
        'taxId' => 'GB123456789',
        'billingAddress' => '123 Main Street, Accra',
    ])->render();

    expect($html)->toContain('Billing Settings');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the tenant payment settings view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    URL::defaults(['subdomain' => $tenant->slug]);

    app('view')->share('errors', new ViewErrorBag());

    $html = view('tenant.settings.payments', [
        'tenant' => $tenant,
        'stripe' => null,
        'paystack' => null,
    ])->render();

    expect($html)->toContain('Merchant Payment Settings');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the enterprise leads index with the metronic layout', function (): void {
    $user = User::factory()->create();

    Lead::query()->create([
        'name' => 'Acme Lead',
        'email' => 'lead@acme.test',
        'message' => 'Enterprise request for migration support.',
    ]);

    $this->actingAs($user)
        ->get(route('admin.leads.index'))
        ->assertSuccessful()
        ->assertSee('Enterprise Leads')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the enterprise lead detail with the metronic layout', function (): void {
    $user = User::factory()->create();
    $lead = Lead::query()->create([
        'name' => 'Detail Lead',
        'email' => 'detail@lead.test',
        'message' => 'Testing lead detail page.',
    ]);

    $this->actingAs($user)
        ->get(route('admin.leads.show', $lead->id))
        ->assertSuccessful()
        ->assertSee('Lead Details')
        ->assertSee($lead->message)
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the tenant health view with the metronic layout', function (): void {
    $user = User::factory()->create();

    Permission::firstOrCreate([
        'name' => 'access-superadmin-dashboard',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'dashboard',
    ]);

    setPermissionsTeamId(null);
    $user->givePermissionTo('access-superadmin-dashboard');

    $package = Package::factory()->create();
    Tenant::factory()->create([
        'package_id' => $package->id,
        'custom_domain' => null,
    ]);

    $this->actingAs($user)
        ->get(route('health.tenants'))
        ->assertSuccessful()
        ->assertSee('Tenant Health')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the onboarding wizard with the metronic layout', function (): void {
    $tenant = Tenant::factory()->create([
        'name' => 'Launch Workspace',
    ]);

    $html = view('admin.onboarding.wizard', [
        'tenant' => $tenant,
    ])->render();

    expect($html)->toContain('Welcome to Launch Workspace!');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the global llm usage dashboard with the metronic layout', function (): void {
    $summary = (object) [
        'total_tokens' => 150000,
        'total_cost' => 89.14,
        'total_requests' => 320,
    ];

    $topTenants = collect([
        (object) [
            'tenant_name' => 'Purpledot',
            'tenant_slug' => 'purpledot',
            'total_tokens' => 80000,
            'total_cost' => 42.5678,
        ],
    ]);

    $topModels = collect([
        (object) [
            'model' => 'gpt-4o-mini',
            'total_tokens' => 120000,
        ],
    ]);

    $dailyTrend = collect([
        (object) [
            'day' => '2026-02-01',
            'total_tokens' => 5000,
            'total_cost' => 2.1345,
        ],
    ]);

    $html = view('admin.llm-usage.index', compact('summary', 'topTenants', 'topModels', 'dailyTrend'))->render();

    expect($html)->toContain('Global LLM Usage Dashboard');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the admin profile view with the metronic layout', function (): void {
    $user = User::factory()->create();

    $html = view('admin.profile.index', [
        'user' => $user,
        'loginSessions' => collect(),
        'activityLogs' => collect(),
        'breadcrumbs' => [],
    ])->render();

    expect($html)->toContain($user->displayName());
    expect($html)->toContain('Account Details');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the tenant api keys management view with the metronic layout', function (): void {
    $html = view('admin.settings.api-keys.index', [
        'apiKeys' => collect(),
        'breadcrumbs' => [],
    ])->render();

    expect($html)->toContain('API Keys');
    expect($html)->toContain('Generate API Key');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the tenant llm config view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    URL::defaults(['subdomain' => $tenant->slug]);

    $html = view('admin.tenant.llm-config.index', [
        'configs' => collect(),
    ])->render();

    expect($html)->toContain('LLM Configurations');
    expect($html)->toContain('OpenAI');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the tenant llm usage view with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    URL::defaults(['subdomain' => $tenant->slug]);

    $usageRow = (object) [
        'created_at' => Carbon::parse('2026-02-15 08:45:00'),
        'model' => 'gpt-4o-mini',
        'user' => (object) ['name' => 'Ops Bot'],
        'apiKey' => null,
        'total_tokens' => 1800,
        'prompt_tokens' => 1200,
        'completion_tokens' => 600,
        'cost_usd' => 0.01325,
    ];

    $recentUsage = new LengthAwarePaginator(
        [$usageRow],
        1,
        15,
        1,
        ['path' => '/']
    );

    $html = view('admin.tenant.llm-usage.index', [
        'totalUsage' => (object) [
            'total_tokens' => 1800,
            'total_cost' => 0.01325,
            'prompt_tokens' => 1200,
            'completion_tokens' => 600,
        ],
        'usageTrend' => collect(),
        'recentUsage' => $recentUsage,
        'tokenPacks' => [
            'starter' => ['name' => 'Starter Pack', 'tokens' => 10000, 'price' => 10],
        ],
        'topupBalance' => 25000,
        'breadcrumbs' => [],
    ])->render();

    expect($html)->toContain('LLM Usage');
    expect($html)->toContain('Purchase Token Pack');
    expect($html)->toContain('assets/metronic/css/styles.css');
});

it('renders the tenant billing dashboard with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'amount' => 12500,
        'status' => 'success',
    ]);

    $invoice = Invoice::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'number' => 'INV-METRONIC-001',
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'due_at' => now()->addDays(7),
        'status' => Invoice::STATUS_ISSUED,
        'currency' => 'USD',
        'subtotal' => 125.00,
        'tax_total' => 0,
        'total' => 125.00,
        'tax_details' => null,
        'meta' => null,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Plan charge',
        'quantity' => 1,
        'unit_price' => 125.00,
        'subtotal' => 125.00,
        'meta' => ['type' => 'base_plan'],
    ]);

    $this->actingAs($user)
        ->get(route('billing.index', ['subdomain' => $tenant->slug]))
        ->assertSuccessful()
        ->assertSee('Billing & Subscription')
        ->assertSee('Spending Analytics')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the tenant pricing page with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    $package = Package::factory()->create([
        'name' => 'Growth',
        'slug' => 'growth',
        'price' => 49,
        'interval' => 'monthly',
        'is_active' => true,
    ]);
    $tenant->update(['package_id' => $package->id]);

    $this->actingAs($user)
        ->get(route('tenant.pricing', ['subdomain' => $tenant->slug]))
        ->assertSuccessful()
        ->assertSee('Choose Your Plan')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders tenant invoice details with the metronic layout', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    $invoice = Invoice::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'number' => 'INV-METRONIC-002',
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'due_at' => now()->addDays(5),
        'status' => Invoice::STATUS_ISSUED,
        'currency' => 'USD',
        'subtotal' => 95.00,
        'tax_total' => 5.00,
        'total' => 100.00,
        'tax_details' => [
            ['name' => 'VAT', 'rate' => 5, 'amount' => 5.00],
        ],
        'meta' => null,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Usage adjustment',
        'quantity' => 1,
        'unit_price' => 95.00,
        'subtotal' => 95.00,
        'meta' => ['type' => 'adjustment'],
    ]);

    $this->actingAs($user)
        ->get(route('billing.invoices.show', ['invoice' => $invoice->id, 'subdomain' => $tenant->slug]))
        ->assertSuccessful()
        ->assertSee('Invoice #'.$invoice->number)
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the login screen with the metronic auth layout', function (): void {
    $tenant = setActiveTenantForTest();

    $this->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Sign in')
        ->assertSee('assets/metronic/css/styles.css');
});

it('renders the forums hub with the metronic layout', function (): void {
    $html = view('forums::hub', [
        'channels' => collect([(object) [
            'name' => 'Announcements',
            'slug' => 'announcements',
            'threads_count' => 3,
        ]]),
        'latestThreads' => collect([(object) [
            'title' => 'Welcome to forums',
            'status' => 'open',
            'channel' => (object) ['name' => 'Announcements'],
            'updated_at' => now(),
        ]]),
        'flaggedThreads' => collect(),
        'latestModerationLogs' => collect(),
    ])->render();

    expect($html)->toContain('Forums Hub');
    expect($html)->toContain('assets/metronic/css/styles.css');
});
