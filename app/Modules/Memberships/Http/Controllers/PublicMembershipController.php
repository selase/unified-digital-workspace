<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\OnlinePaymentService;
use App\Modules\Finance\Support\ActivateTenant;
use App\Modules\Memberships\Models\MemType;
use App\Modules\Memberships\Services\MembershipService;
use App\Services\ModuleManager;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The open "Become a member" page. It can only create a pending member and send them to pay the first dues. */
final class PublicMembershipController extends Controller
{
    public function show(string $tenant, FinanceSettings $settings): View
    {
        $model = $this->tenant($tenant);

        return view('memberships::public.join', ['tenant' => $model, 'profile' => $settings->for($model->id), 'types' => $this->types($model), 'done' => false]);
    }

    public function store(Request $request, string $tenant, MembershipService $memberships, OnlinePaymentService $online, FinanceSettings $settings): View|RedirectResponse
    {
        $model = $this->tenant($tenant);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'type_id' => ['required', 'integer'],
            'website' => ['nullable', 'max:0'],
        ], ['name.required' => 'Please tell us your name.', 'email.required' => 'We need an email to reach you.', 'type_id.required' => 'Choose a membership.']);

        $type = $this->types($model)->firstWhere('id', (int) $data['type_id']);
        abort_unless($type instanceof MemType, 422);

        try {
            $member = $memberships->join($model->id, $type, $data['name'], ['email' => $data['email'], 'phone' => $data['phone'] ?? null]);
            $invoice = $member->renewals()->with('invoice')->latest('id')->first()?->invoice;

            if ($invoice !== null && $online->canCollect($invoice)) {
                return redirect()->to($online->paymentUrl($invoice));
            }
        } catch (DomainException) {
            // A repeat application looks the same to the visitor, so the form cannot be used to discover who is a member.
        }

        return view('memberships::public.join', ['tenant' => $model, 'profile' => $settings->for($model->id), 'types' => $this->types($model), 'done' => true]);
    }

    /** @return \Illuminate\Support\Collection<int, MemType> */
    private function types(Tenant $tenant): \Illuminate\Support\Collection
    {
        return MemType::query()->where('tenant_id', $tenant->id)->where('is_active', true)->where('is_public', true)->orderBy('fee')->get();
    }

    private function tenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();
        abort_unless(app(ModuleManager::class)->isEnabledForTenant('memberships', $tenant), 404);
        ActivateTenant::for($tenant);

        return $tenant;
    }
}
