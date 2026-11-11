<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinItem;
use App\Modules\Finance\Models\FinStockMovement;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Finance\Services\StockService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant, 2: FinContact}
 */
function createStockContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = config('modules.finance.permissions');
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'finance', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-finance', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(ModuleManager::class)->enableForTenant('finance', $tenant);
    Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path('Modules/Finance/Database/Migrations'), '--realpath' => true, '--force' => true]);

    return [$user, $tenant, FinContact::query()->create(['tenant_id' => $tenant->id, 'name' => 'Acme Ltd', 'email' => 'acme@example.test', 'is_customer' => true, 'is_active' => true])];
}

function stockItem(Tenant $tenant, string $name = 'Water filter', bool $track = true, float $opening = 10, float $reorder = 3): FinItem
{
    $item = FinItem::query()->create(['tenant_id' => $tenant->id, 'name' => $name, 'unit_price' => 5000, 'track_stock' => $track, 'reorder_level_thousandths' => (int) ($reorder * 1000)]);
    if ($track && $opening > 0) {
        app(StockService::class)->record($item, 'opening', $opening, CarbonImmutable::today());
    }

    return $item;
}

function issueStockInvoice(Tenant $tenant, FinContact $contact, array $lines, string $type = FinInvoice::TYPE_INVOICE): FinInvoice
{
    $service = app(InvoiceService::class);
    $draft = $service->saveDraft($tenant->id, $contact, ['type' => $type, 'issue_date' => today()->toDateString()], array_map(fn (array $line): array => ['description' => $line[0], 'quantity' => $line[1], 'unit_price' => 5000, 'item_id' => $line[2]], $lines));

    return $service->send($draft);
}

describe('Stock rules', function (): void {
    test('stock is received and adjusted with validation, and never goes below zero', function (): void {
        [, $tenant] = createStockContext();
        $stock = app(StockService::class);
        $item = stockItem($tenant, opening: 0);
        $untracked = stockItem($tenant, 'Consulting', false);

        expect($stock->onHand($item))->toBe(0)
            ->and(fn () => $stock->record($untracked, 'purchase', 5, CarbonImmutable::today()))->toThrow(DomainException::class, 'tracking')
            ->and(fn () => $stock->record($item, 'purchase', 0, CarbonImmutable::today()))->toThrow(DomainException::class, 'greater than zero')
            ->and(fn () => $stock->record($item, 'purchase', -2, CarbonImmutable::today()))->toThrow(DomainException::class)
            ->and(fn () => $stock->record($item, 'sale', 2, CarbonImmutable::today()))->toThrow(DomainException::class, 'Choose')
            ->and(fn () => $stock->record($item, 'purchase', 2, CarbonImmutable::today()->addDay()))->toThrow(DomainException::class, 'future');

        $stock->record($item, 'purchase', 12.5, CarbonImmutable::today(), 3000, 'Supplier delivery');
        $stock->record($item, 'adjustment', -2.5, CarbonImmutable::today(), null, 'Damaged');
        expect($stock->onHand($item))->toBe(10_000)->and(fn () => $stock->record($item, 'adjustment', -11, CarbonImmutable::today()))->toThrow(DomainException::class, 'below zero');
    });

    test('issuing an invoice takes stock out, overselling is refused without side effects, credit notes return stock and voiding undoes it once', function (): void {
        [, $tenant, $contact] = createStockContext();
        $stock = app(StockService::class);
        $item = stockItem($tenant);
        $service = stockItem($tenant, 'Consulting', false);

        $invoice = issueStockInvoice($tenant, $contact, [['Filters', 4, $item->id], ['Advice', 1, $service->id]]);
        expect($stock->onHand($item))->toBe(6000)->and(FinStockMovement::query()->where('item_id', $service->id)->count())->toBe(0);

        expect(fn () => issueStockInvoice($tenant, $contact, [['Too many', 7, $item->id]]))->toThrow(DomainException::class, 'Not enough stock of Water filter');
        expect($stock->onHand($item))->toBe(6000)->and(FinInvoice::query()->where('status', 'sent')->count())->toBe(1);

        $second = issueStockInvoice($tenant, $contact, [['Filters', 2, $item->id]]);
        expect($stock->onHand($item))->toBe(4000);
        $credit = app(InvoiceService::class)->createCreditNote($second->fresh());
        app(InvoiceService::class)->send($credit);
        expect($stock->onHand($item))->toBe(6000)->and(FinStockMovement::query()->where('kind', 'return')->count())->toBe(1);

        app(InvoiceService::class)->void($invoice->fresh());
        expect($stock->onHand($item))->toBe(10_000);
    });

    test('voiding an invoice returns exactly what it took, and repeating the void changes nothing', function (): void {
        [, $tenant, $contact] = createStockContext();
        $stock = app(StockService::class);
        $item = stockItem($tenant);
        $invoice = issueStockInvoice($tenant, $contact, [['Filters', 4, $item->id]]);
        expect($stock->onHand($item))->toBe(6000);

        app(InvoiceService::class)->void($invoice);
        expect($stock->onHand($item))->toBe(10_000);
        $stock->reverseDocument($invoice->fresh());
        expect($stock->onHand($item))->toBe(10_000)->and(FinStockMovement::query()->where('kind', 'void')->count())->toBe(1);
    });

    test('levels flag items at or below the reorder point and ignore untracked and switched-off ones', function (): void {
        [, $tenant, $contact] = createStockContext();
        $stock = app(StockService::class);
        $item = stockItem($tenant);
        stockItem($tenant, 'Untracked', false);
        $off = stockItem($tenant, 'Retired', true, 1, 5);
        $off->update(['is_active' => false]);

        expect($stock->levels($tenant->id))->toHaveCount(2)->and($stock->levels($tenant->id)->firstWhere('item.name', 'Water filter')['low'])->toBeFalse();
        issueStockInvoice($tenant, $contact, [['Filters', 8, $item->id]]);
        $levels = $stock->levels($tenant->id);
        expect($levels->firstWhere('item.name', 'Water filter')['low'])->toBeTrue()->and($levels->firstWhere('item.name', 'Retired')['low'])->toBeFalse();
    });
});

describe('Stock screens', function (): void {
    test('staff add a tracked item with opening stock, receive more, see levels and export them', function (): void {
        [$user, $tenant] = createStockContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('finance.items.store'), ['name' => 'Water filter', 'unit_price' => '50', 'track_stock' => 1, 'opening_quantity' => 8, 'reorder_level' => 2])->assertSessionHas('success');
        $item = FinItem::query()->firstOrFail();
        expect($item->track_stock)->toBeTrue()->and(app(StockService::class)->onHand($item))->toBe(8000);

        $this->get(route('finance.stock.index'))->assertOk()->assertSee('Water filter')->assertSee('8.000');
        $this->post(route('finance.stock.store', $item->uuid), ['kind' => 'purchase', 'quantity' => 4, 'moved_on' => today()->toDateString(), 'note' => 'Delivery'])->assertSessionHas('success');
        $this->post(route('finance.stock.store', $item->uuid), ['kind' => 'adjustment', 'quantity' => -50, 'moved_on' => today()->toDateString()])->assertSessionHas('error');
        $this->post(route('finance.stock.store', $item->uuid), ['kind' => 'sale', 'quantity' => 1, 'moved_on' => today()->toDateString()])->assertSessionHasErrors('kind');
        $this->get(route('finance.stock.show', $item->uuid))->assertOk()->assertSee('Delivery')->assertSee('Opening stock');

        $csv = $this->get(route('finance.stock.index', ['export' => 'csv']));
        $csv->assertOk()->assertDownload();
        expect($csv->streamedContent())->toContain('Water filter', '12');
    });

    test('recording stock needs the invoices permission and viewing needs finance access', function (): void {
        [$user, $tenant] = createStockContext();
        $item = stockItem($tenant);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $user->revokePermissionTo('finance.invoices.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->post(route('finance.stock.store', $item->uuid), ['kind' => 'purchase', 'quantity' => 1, 'moved_on' => today()->toDateString()])->assertForbidden();
        $this->get(route('finance.stock.index'))->assertOk();

        $user->revokePermissionTo('finance.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('finance.stock.index'))->assertForbidden();
    });
});
