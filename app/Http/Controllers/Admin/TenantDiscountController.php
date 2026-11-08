<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTenantDiscountRequest;
use App\Models\Tenant;
use App\Models\TenantDiscount;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class TenantDiscountController extends Controller
{
    public function index(): View
    {
        $this->authorize('access-superadmin-dashboard');

        return view('admin.billing.discounts.index', [
            'discounts' => TenantDiscount::query()->with('tenant:id,name')->latest()->get(),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveTenantDiscountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        TenantDiscount::create([
            ...$validated,
            'currency' => $validated['type'] === TenantDiscount::TYPE_FIXED ? $validated['currency'] : null,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Discount saved. It applies to invoices generated from now on; draft invoices already generated are unchanged.');
    }

    public function toggle(TenantDiscount $discount): RedirectResponse
    {
        $this->authorize('access-superadmin-dashboard');

        $discount->update(['is_active' => ! $discount->is_active]);

        return back()->with('success', $discount->is_active ? 'Discount reactivated.' : 'Discount switched off.');
    }
}
