<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\RealEstate\Models\PropertyListing;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PublicRealEstateWebController extends Controller
{
    public function catalog(Request $request): View
    {
        $listings = PropertyListing::query()
            ->where('is_published', true)
            ->with(['parcel.project'])
            ->latest()
            ->get();

        return view('real-estate::public.catalog', [
            'listings' => $listings,
        ]);
    }

    public function show(Request $request, PropertyListing $listing): View
    {
        abort_if(! $listing->is_published, 404);

        $listing->load(['parcel.project', 'parcel.documents']);

        return view('real-estate::public.show', [
            'listing' => $listing,
        ]);
    }

    public function inquire(Request $request, PropertyListing $listing): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        $moduleManager = app(ModuleManager::class);

        // Companion writer: if CRM module is active for tenant, persist as CrmLead
        if ($tenant && $moduleManager->isEnabledForTenant('crm', $tenant)) {
            CrmLead::create([
                'tenant_id' => (string) $tenant->id,
                'reference_code' => 'LEAD-PROP-'.mb_strtoupper(Str::random(6)),
                'source' => 'website_enquiry',
                'enquiry_type' => 'real_estate_listing',
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'message' => "Inquiry on {$listing->title} ({$listing->location}): ".($validated['message'] ?? 'Client requested details/inspection.'),
                'status' => CrmLead::STATUS_NEW,
            ]);
        }

        return back()
            ->with('status', 'success')
            ->with('message', 'Thank you! Your property inquiry has been received. Our sales advisory team will contact you shortly.');
    }
}
