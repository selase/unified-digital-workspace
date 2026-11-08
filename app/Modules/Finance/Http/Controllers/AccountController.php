<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\SaveAccountRequest;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'finance.view');

        return view('finance::accounts.index', [
            'accounts' => FinAccount::query()->where('tenant_id', $tenantId)->orderBy('code')->get()->groupBy('type'),
            'types' => FinAccount::TYPES,
        ]);
    }

    public function store(SaveAccountRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        FinAccount::query()->create([...$request->validated(), 'tenant_id' => $tenantId]);

        return back()->with('success', 'Account added.');
    }

    public function update(SaveAccountRequest $request, string $account): RedirectResponse
    {
        $model = FinAccount::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $account)->firstOrFail();

        $data = $request->validated();
        if ($model->system_key !== null) {
            unset($data['type']);
        }
        $model->update($data);

        return back()->with('success', 'Account updated.');
    }

    public function toggle(Request $request, string $account): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.accounts.manage');
        $model = FinAccount::query()->where('tenant_id', $tenantId)->where('uuid', $account)->firstOrFail();

        if ($model->system_key !== null && $model->is_active) {
            return back()->with('error', 'System accounts cannot be switched off.');
        }

        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('success', $model->is_active ? 'Account switched on.' : 'Account switched off.');
    }
}
