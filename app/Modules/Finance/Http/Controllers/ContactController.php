<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\SaveContactRequest;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ContactController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $search = $request->string('search')->trim()->toString();

        return view('finance::contacts.index', [
            'contacts' => FinContact::query()->where('tenant_id', $tenantId)
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%")))
                ->orderBy('name')->paginate(25)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function store(SaveContactRequest $request): RedirectResponse
    {
        FinContact::query()->create([
            ...$request->safe()->except(['is_customer', 'is_vendor']),
            'tenant_id' => $this->tenantId($request),
            'is_customer' => $request->boolean('is_customer', true),
            'is_vendor' => $request->boolean('is_vendor'),
        ]);

        return back()->with('success', 'Contact saved.');
    }

    public function update(SaveContactRequest $request, string $contact): RedirectResponse
    {
        $model = FinContact::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $contact)->firstOrFail();
        $model->update([
            ...$request->safe()->except(['is_customer', 'is_vendor']),
            'is_customer' => $request->boolean('is_customer', true),
            'is_vendor' => $request->boolean('is_vendor'),
        ]);

        return back()->with('success', 'Contact updated.');
    }

    public function destroy(Request $request, string $contact): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.contacts.manage');
        $model = FinContact::query()->where('tenant_id', $tenantId)->where('uuid', $contact)->firstOrFail();

        if ($model->invoices()->exists()) {
            $model->update(['is_active' => false]);

            return back()->with('success', 'This contact has documents, so it was switched off instead of deleted.');
        }

        $model->delete();

        return back()->with('success', 'Contact deleted.');
    }
}
