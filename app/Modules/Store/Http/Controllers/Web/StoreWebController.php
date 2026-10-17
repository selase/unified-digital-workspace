<?php

declare(strict_types=1);

namespace App\Modules\Store\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Store\Models\PlatformCreditPackage;
use App\Modules\Store\Models\StoreOrder;
use App\Modules\Store\Models\StoreProduct;
use App\Modules\Store\Models\TenantCreditWallet;
use App\Modules\Store\Services\PlatformCreditService;
use App\Modules\Store\Services\StoreInventoryService;
use App\Services\Tenancy\TenantContext;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreWebController extends Controller
{
    public function __construct(
        private readonly PlatformCreditService $creditService,
        private readonly StoreInventoryService $inventoryService,
        private readonly TenantContext $tenantContext
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('store.view'), 403);

        $tenant = $this->tenantContext->getTenant();
        $tenantId = $tenant ? (string) $tenant->id : '';

        $wallets = TenantCreditWallet::where('tenant_id', $tenantId)->get()->keyBy('credit_type');
        $productsCount = StoreProduct::where('tenant_id', $tenantId)->count();
        $ordersCount = StoreOrder::where('tenant_id', $tenantId)->count();
        $totalRevenueCents = StoreOrder::where('tenant_id', $tenantId)->where('status', 'paid')->sum('total_amount_cents');

        $recentOrders = StoreOrder::where('tenant_id', $tenantId)->with('items.product')->latest()->limit(5)->get();

        return view('store::index', [
            'wallets' => $wallets,
            'productsCount' => $productsCount,
            'ordersCount' => $ordersCount,
            'totalRevenueCents' => $totalRevenueCents,
            'recentOrders' => $recentOrders,
        ]);
    }

    public function platformPackages(Request $request): View
    {
        abort_if(! $request->user()?->can('store.platform.purchase'), 403);

        $packages = PlatformCreditPackage::where('is_active', true)->orderBy('price_cents')->get();

        $tenant = $this->tenantContext->getTenant();
        $wallets = $tenant ? TenantCreditWallet::where('tenant_id', $tenant->id)->get()->keyBy('credit_type') : collect();

        return view('store::platform-store', [
            'packages' => $packages,
            'wallets' => $wallets,
        ]);
    }

    public function purchasePlatformCredits(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('store.platform.purchase'), 403);

        $validated = $request->validate([
            'package_id' => ['required', 'integer', Rule::exists('platform_credit_packages', 'id')->where('is_active', true)],
        ]);

        $tenant = $this->tenantContext->getTenant();
        if (! $tenant) {
            abort(403, 'No active tenant context.');
        }

        $wallet = $this->creditService->purchasePackage((string) $tenant->id, $validated['package_id']);

        return back()
            ->with('status', 'success')
            ->with('message', "Successfully purchased credits. New {$wallet->credit_type} balance: ".number_format($wallet->balance).' units.');
    }

    public function products(Request $request): View
    {
        abort_if(! $request->user()?->can('store.view'), 403);

        $tenant = $this->tenantContext->getTenant();
        $tenantId = $tenant ? (string) $tenant->id : '';

        $products = StoreProduct::where('tenant_id', $tenantId)->latest()->paginate(15);

        return view('store::products', [
            'products' => $products,
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('store.manage'), 403);

        $tenant = $this->tenantContext->getTenant();
        if (! $tenant) {
            abort(403, 'No active tenant context.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'audience' => ['required', 'string', Rule::in(['staff_only', 'public'])],
        ]);

        $slug = $validated['slug'] ?: Str::slug($validated['name']);
        if (StoreProduct::where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            $slug .= '-'.uniqid();
        }

        StoreProduct::create([
            'tenant_id' => (string) $tenant->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'sku' => $validated['sku'] ?? null,
            'description' => $validated['description'] ?? null,
            'price_cents' => (int) round(((float) $validated['price']) * 100),
            'stock_quantity' => (int) $validated['stock_quantity'],
            'audience' => $validated['audience'],
            'is_active' => true,
        ]);

        return back()->with('status', 'success')->with('message', 'Product created successfully.');
    }

    public function orders(Request $request): View
    {
        abort_if(! $request->user()?->can('store.orders.manage'), 403);

        $tenant = $this->tenantContext->getTenant();
        $tenantId = $tenant ? (string) $tenant->id : '';

        $orders = StoreOrder::where('tenant_id', $tenantId)->with('items.product', 'user')->latest()->paginate(15);

        return view('store::orders', [
            'orders' => $orders,
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant) {
            abort(403, 'No active tenant context.');
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'payment_method' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $order = $this->inventoryService->placeOrder(
                (string) $tenant->id,
                [
                    'customer_name' => $validated['customer_name'],
                    'customer_email' => $validated['customer_email'],
                    'payment_method' => $validated['payment_method'] ?? 'portal_balance',
                ],
                $validated['items'],
                $request->user()?->uuid !== null ? (string) $request->user()->uuid : null
            );

            return back()
                ->with('status', 'success')
                ->with('message', "Order #{$order->order_number} confirmed! Total: GHS ".number_format($order->total_amount_cents / 100, 2));
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }
    }
}
