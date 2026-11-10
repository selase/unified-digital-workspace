<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\Portal\PortalLinkMail;
use App\Models\Tenant;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\OnlinePaymentService;
use App\Modules\Finance\Support\ActivateTenant;
use App\Modules\Memberships\Models\MemMember;
use App\Modules\Memberships\Models\MemRenewal;
use App\Modules\Memberships\Services\MembershipService;
use App\Services\ModuleManager;
use App\Services\Portal\PortalLinkService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** A member's own page: membership status, the dues invoice with a payment link, and a way to renew. */
final class MemberPortalController extends Controller
{
    private const KIND = 'member';

    public function login(string $tenant, FinanceSettings $settings): View
    {
        $model = $this->tenant($tenant);

        return view('memberships::portal.login', ['tenant' => $model, 'profile' => $settings->for($model->id), 'sent' => false]);
    }

    public function sendLink(Request $request, string $tenant, PortalLinkService $portal, FinanceSettings $settings): View
    {
        $model = $this->tenant($tenant);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'website' => ['nullable', 'max:0']], ['email.required' => 'Enter the email address you joined with.']);
        $profile = $settings->for($model->id);

        $member = MemMember::query()->where('tenant_id', $model->id)->where('status', '!=', 'cancelled')->where('email', mb_strtolower($data['email']))->first();
        if ($portal->allowRequest($request) && $member instanceof MemMember) {
            try {
                Mail::to($member->email)->send(new PortalLinkMail(
                    $member->name,
                    $profile->legal_name ?: $model->name,
                    $portal->link('memberships.portal.enter', ['tenant' => $model->slug, 'member' => $member->uuid]),
                    'see your membership, pay your dues and renew',
                    PortalLinkService::LINK_MINUTES,
                ));
            } catch (Throwable $error) {
                report($error);
            }
        }

        // The same answer whether or not the address is known, so the form cannot be used to find out who is a member.
        return view('memberships::portal.login', ['tenant' => $model, 'profile' => $profile, 'sent' => true]);
    }

    public function enter(Request $request, string $tenant, string $member, PortalLinkService $portal, FinanceSettings $settings): Response|RedirectResponse
    {
        $model = $this->tenant($tenant);
        $record = MemMember::query()->where('tenant_id', $model->id)->where('status', '!=', 'cancelled')->where('uuid', $member)->first();

        if (! $record instanceof MemMember || ! $portal->consume($request)) {
            return response()->view('memberships::portal.login', ['tenant' => $model, 'profile' => $settings->for($model->id), 'sent' => false, 'problem' => 'That link has already been used or has expired. Request a new one.'], 403);
        }

        $portal->establish($request, self::KIND, $model->id, $record->id);

        return redirect()->route('memberships.portal.me', ['tenant' => $model->slug]);
    }

    public function me(Request $request, string $tenant, PortalLinkService $portal, MembershipService $memberships, OnlinePaymentService $online, FinanceSettings $settings): View|RedirectResponse
    {
        $model = $this->tenant($tenant);
        $memberships->applyPayments($model->id);
        $member = $this->signedIn($request, $model, $portal);
        if (! $member instanceof MemMember) {
            return redirect()->route('memberships.portal.login', ['tenant' => $model->slug]);
        }

        $member->load('type');
        $renewals = $member->renewals()->with('invoice')->latest('period_start')->get();
        $open = $renewals->firstWhere('status', MemRenewal::BILLED);

        return view('memberships::portal.me', [
            'tenant' => $model,
            'profile' => $settings->for($model->id),
            'member' => $member,
            'renewals' => $renewals,
            'open' => $open,
            'payUrl' => $open?->invoice && $online->canCollect($open->invoice) ? $online->paymentUrl($open->invoice) : null,
            'canRenew' => $open === null && $member->type->fee > 0,
        ]);
    }

    public function renew(Request $request, string $tenant, PortalLinkService $portal, MembershipService $memberships): RedirectResponse
    {
        $model = $this->tenant($tenant);
        $member = $this->signedIn($request, $model, $portal);
        abort_unless($member instanceof MemMember, 403);

        try {
            $memberships->billDues($member->load(['type', 'contact']));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Your dues invoice has been created. You can pay it below.');
    }

    public function logout(Request $request, string $tenant, PortalLinkService $portal): RedirectResponse
    {
        $model = $this->tenant($tenant);
        $portal->forget($request, self::KIND, $model->id);

        return redirect()->route('memberships.portal.login', ['tenant' => $model->slug]);
    }

    private function signedIn(Request $request, Tenant $tenant, PortalLinkService $portal): ?MemMember
    {
        $id = $portal->current($request, self::KIND, $tenant->id);

        return $id === null ? null : MemMember::query()->where('tenant_id', $tenant->id)->where('status', '!=', 'cancelled')->find($id);
    }

    private function tenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();
        abort_unless(app(ModuleManager::class)->isEnabledForTenant('memberships', $tenant), 404);
        ActivateTenant::for($tenant);

        return $tenant;
    }
}
