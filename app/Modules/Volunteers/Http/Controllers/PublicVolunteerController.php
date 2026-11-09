<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\ActivateTenant;
use App\Modules\Volunteers\Services\VolunteerService;
use App\Services\ModuleManager;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The open "Volunteer with us" page. Sign-ups wait for staff approval. */
final class PublicVolunteerController extends Controller
{
    public function show(string $tenant, FinanceSettings $settings): View
    {
        $model = $this->tenant($tenant);

        return view('volunteers::public.signup', ['tenant' => $model, 'profile' => $settings->for($model->id), 'done' => false]);
    }

    public function store(Request $request, string $tenant, VolunteerService $volunteers, FinanceSettings $settings): View|RedirectResponse
    {
        $model = $this->tenant($tenant);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'max:0'],
        ], ['name.required' => 'Please tell us your name.', 'email.required' => 'We need an email to reach you.']);

        try {
            $volunteers->register($model->id, $data['name'], ['email' => $data['email'], 'phone' => $data['phone'] ?? null, 'skills' => $data['skills'] ?? null], false);
        } catch (DomainException) {
            // A repeat sign-up looks the same to the visitor, so the form cannot be used to discover who volunteers.
        }

        return view('volunteers::public.signup', ['tenant' => $model, 'profile' => $settings->for($model->id), 'done' => true]);
    }

    private function tenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();
        abort_unless(app(ModuleManager::class)->isEnabledForTenant('volunteers', $tenant), 404);
        ActivateTenant::for($tenant);

        return $tenant;
    }
}
