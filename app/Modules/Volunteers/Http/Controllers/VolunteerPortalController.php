<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\Portal\PortalLinkMail;
use App\Models\Tenant;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\ActivateTenant;
use App\Modules\Volunteers\Models\VolHour;
use App\Modules\Volunteers\Models\VolOpportunity;
use App\Modules\Volunteers\Models\VolVolunteer;
use App\Modules\Volunteers\Services\VolunteerCertificatePdf;
use App\Modules\Volunteers\Services\VolunteerService;
use App\Services\ModuleManager;
use App\Services\Portal\PortalLinkService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** A volunteer's own page: see their hours, log new ones for approval, sign up for opportunities, and print a certificate. */
final class VolunteerPortalController extends Controller
{
    private const KIND = 'volunteer';

    public function login(string $tenant, FinanceSettings $settings): View
    {
        $model = $this->tenant($tenant);

        return view('volunteers::portal.login', ['tenant' => $model, 'profile' => $settings->for($model->id), 'sent' => false]);
    }

    public function sendLink(Request $request, string $tenant, PortalLinkService $portal, FinanceSettings $settings): View
    {
        $model = $this->tenant($tenant);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'website' => ['nullable', 'max:0']], ['email.required' => 'Enter the email address you signed up with.']);
        $profile = $settings->for($model->id);

        $volunteer = VolVolunteer::query()->where('tenant_id', $model->id)->where('status', 'active')->where('email', mb_strtolower($data['email']))->first();
        if ($portal->allowRequest($request) && $volunteer instanceof VolVolunteer) {
            try {
                Mail::to($volunteer->email)->send(new PortalLinkMail(
                    $volunteer->name,
                    $profile->legal_name ?: $model->name,
                    $portal->link('volunteers.portal.enter', ['tenant' => $model->slug, 'volunteer' => $volunteer->uuid]),
                    'see your volunteer hours and sign up for opportunities',
                    PortalLinkService::LINK_MINUTES,
                ));
            } catch (Throwable $error) {
                report($error);
            }
        }

        // The same answer whether or not the address is known, so the form cannot be used to find out who volunteers.
        return view('volunteers::portal.login', ['tenant' => $model, 'profile' => $profile, 'sent' => true]);
    }

    public function enter(Request $request, string $tenant, string $volunteer, PortalLinkService $portal, FinanceSettings $settings): Response|RedirectResponse
    {
        $model = $this->tenant($tenant);
        $record = VolVolunteer::query()->where('tenant_id', $model->id)->where('status', 'active')->where('uuid', $volunteer)->first();

        if (! $record instanceof VolVolunteer || ! $portal->consume($request)) {
            return response()->view('volunteers::portal.login', ['tenant' => $model, 'profile' => $settings->for($model->id), 'sent' => false, 'problem' => 'That link has already been used or has expired. Request a new one.'], 403);
        }

        $portal->establish($request, self::KIND, $model->id, $record->id);

        return redirect()->route('volunteers.portal.me', ['tenant' => $model->slug]);
    }

    public function me(Request $request, string $tenant, PortalLinkService $portal, FinanceSettings $settings): View|RedirectResponse
    {
        [$model, $volunteer] = $this->signedIn($request, $tenant, $portal);
        if (! $volunteer instanceof VolVolunteer) {
            return redirect()->route('volunteers.portal.login', ['tenant' => $model->slug]);
        }

        $hours = VolHour::query()->where('volunteer_id', $volunteer->id)->with('opportunity')->latest('worked_on')->latest('id')->limit(30)->get();

        return view('volunteers::portal.me', [
            'tenant' => $model,
            'profile' => $settings->for($model->id),
            'volunteer' => $volunteer,
            'hours' => $hours,
            'approvedMinutes' => (int) VolHour::query()->where('volunteer_id', $volunteer->id)->where('status', 'approved')->sum('minutes'),
            'pendingMinutes' => (int) VolHour::query()->where('volunteer_id', $volunteer->id)->where('status', 'pending')->sum('minutes'),
            'signedUpIds' => $volunteer->opportunities()->pluck('vol_opportunities.id')->all(),
            'opportunities' => VolOpportunity::query()->where('tenant_id', $model->id)->where('status', 'open')->whereDate('starts_on', '>=', today()->toDateString())->withCount('volunteers')->orderBy('starts_on')->get(),
        ]);
    }

    public function logHours(Request $request, string $tenant, PortalLinkService $portal, VolunteerService $volunteers): RedirectResponse
    {
        [$model, $volunteer] = $this->signedIn($request, $tenant, $portal);
        abort_unless($volunteer instanceof VolVolunteer, 403);
        $data = $request->validate([
            'worked_on' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:24'],
            'activity' => ['nullable', 'string', 'max:255'],
            'opportunity_id' => ['nullable', 'integer'],
        ], ['hours.gt' => 'Enter more than 0 hours.', 'hours.max' => 'A day has 24 hours at most.']);
        $opportunity = ! empty($data['opportunity_id']) ? VolOpportunity::query()->where('tenant_id', $model->id)->find((int) $data['opportunity_id']) : null;

        try {
            $volunteers->logHours($volunteer, Carbon::parse($data['worked_on']), (float) $data['hours'], $data['activity'] ?? null, $opportunity);
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Thank you. Your hours are recorded and waiting for approval.');
    }

    public function signUp(Request $request, string $tenant, int $opportunity, PortalLinkService $portal, VolunteerService $volunteers): RedirectResponse
    {
        [$model, $volunteer] = $this->signedIn($request, $tenant, $portal);
        abort_unless($volunteer instanceof VolVolunteer, 403);
        $record = VolOpportunity::query()->where('tenant_id', $model->id)->findOrFail($opportunity);

        try {
            $volunteers->signUp($record, $volunteer);
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'You are signed up for '.$record->title.'.');
    }

    public function certificate(Request $request, string $tenant, PortalLinkService $portal, VolunteerCertificatePdf $certificates): Response|RedirectResponse
    {
        [, $volunteer] = $this->signedIn($request, $tenant, $portal);
        abort_unless($volunteer instanceof VolVolunteer, 403);

        try {
            $output = $certificates->output($volunteer);
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return response($output, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$certificates->filename($volunteer).'"']);
    }

    public function logout(Request $request, string $tenant, PortalLinkService $portal): RedirectResponse
    {
        $model = $this->tenant($tenant);
        $portal->forget($request, self::KIND, $model->id);

        return redirect()->route('volunteers.portal.login', ['tenant' => $model->slug]);
    }

    /** @return array{0: Tenant, 1: VolVolunteer|null} */
    private function signedIn(Request $request, string $tenant, PortalLinkService $portal): array
    {
        $model = $this->tenant($tenant);
        $id = $portal->current($request, self::KIND, $model->id);
        $volunteer = $id === null ? null : VolVolunteer::query()->where('tenant_id', $model->id)->where('status', 'active')->find($id);

        return [$model, $volunteer];
    }

    private function tenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();
        abort_unless(app(ModuleManager::class)->isEnabledForTenant('volunteers', $tenant), 404);
        ActivateTenant::for($tenant);

        return $tenant;
    }
}
