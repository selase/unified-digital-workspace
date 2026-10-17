<?php

declare(strict_types=1);

namespace App\Modules\VendorMarketplace\Http\Controllers\Web;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Modules\VendorMarketplace\Models\CommissionRateCard;
use App\Modules\VendorMarketplace\Models\EscrowAccount;
use App\Modules\VendorMarketplace\Models\EscrowDispute;
use App\Modules\VendorMarketplace\Models\EscrowLedgerEntry;
use App\Modules\VendorMarketplace\Models\ProformaInvoice;
use App\Modules\VendorMarketplace\Models\ServiceJob;
use App\Modules\VendorMarketplace\Models\VendorProfile;
use App\Modules\VendorMarketplace\Services\EscrowSettlementService;
use App\Services\Tenancy\TenantContext;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class VendorMarketplaceWebController extends Controller
{
    public const CATEGORIES = [
        'surveying' => 'Geomatic & Land Surveying',
        'land_registration' => 'Land Title & Registration Legal Experts',
        'architecture_engineering' => 'Architecture & Structural Engineering (GIA/GhIE)',
        'building_materials' => 'Building Materials (Sand, Cement, Steel, Blocks)',
        'electrical' => 'Electrical Artisans & Power Systems',
        'plumbing' => 'Plumbing & Water Reticulation Artisans',
        'landscaping' => 'Landscaping & Grounds Maintenance',
        'water_delivery' => 'Potable Water Tanker & Borehole Services',
        'general_maintenance' => 'General Facility & Civil Maintenance',
    ];

    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('vendors.view') && ! $request->user()?->can('vendors.portal.access'), 403);

        $rateCards = CommissionRateCard::query()->orderBy('category')->get();
        $vendors = VendorProfile::query()->with('user:uuid,first_name,last_name,email')->latest()->limit(25)->get();
        $jobs = ServiceJob::query()->with(['vendorProfile', 'requester:uuid,first_name,last_name,email'])->latest()->limit(25)->get();
        $proformas = ProformaInvoice::query()->with(['serviceJob', 'vendorProfile', 'escrowAccount'])->latest()->limit(25)->get();
        $escrows = EscrowAccount::query()->with(['proformaInvoice', 'vendorProfile', 'serviceJob', 'disputes'])->latest()->limit(25)->get();
        $ledgerEntries = EscrowLedgerEntry::query()->with('escrowAccount')->latest('occurred_at')->limit(20)->get();

        $heldPesewas = (int) EscrowAccount::query()
            ->whereIn('status', [EscrowAccount::STATUS_FUNDED, EscrowAccount::STATUS_FROZEN_DISPUTED])
            ->sum('held_balance_pesewas');

        $tenantCommissionPesewas = (int) EscrowAccount::query()
            ->where('status', EscrowAccount::STATUS_RELEASED)
            ->sum('released_tenant_commission_pesewas');

        $platformCommissionPesewas = (int) EscrowAccount::query()
            ->where('status', EscrowAccount::STATUS_RELEASED)
            ->sum('released_platform_commission_pesewas');

        $vendorPayoutPesewas = (int) EscrowAccount::query()
            ->where('status', EscrowAccount::STATUS_RELEASED)
            ->sum('released_vendor_pesewas');

        return view('vendor-marketplace::index', [
            'categories' => self::CATEGORIES,
            'rateCards' => $rateCards,
            'vendors' => $vendors,
            'jobs' => $jobs,
            'proformas' => $proformas,
            'escrows' => $escrows,
            'ledgerEntries' => $ledgerEntries,
            'metrics' => [
                'vendors_count' => VendorProfile::query()->count(),
                'held_ghs' => number_format($heldPesewas / 100, 2),
                'vendor_payout_ghs' => number_format($vendorPayoutPesewas / 100, 2),
                'tenant_commission_ghs' => number_format($tenantCommissionPesewas / 100, 2),
                'platform_commission_ghs' => number_format($platformCommissionPesewas / 100, 2),
                'active_disputes' => EscrowDispute::query()->where('status', EscrowDispute::STATUS_OPEN)->count(),
            ],
        ]);
    }

    public function upsertRateCard(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('commissions.configure'), 403);

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(array_keys(self::CATEGORIES))],
            'tenant_commission_percent' => ['required', 'numeric', 'min:0', 'max:25'],
            'platform_commission_percent' => ['required', 'numeric', 'min:0', 'max:25'],
        ]);

        $tenantBps = (int) round(((float) $validated['tenant_commission_percent']) * 100);
        $platformBps = (int) round(((float) $validated['platform_commission_percent']) * 100);
        $tenantId = (string) (app(TenantContext::class)->getTenant()?->id ?? '');

        CommissionRateCard::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'category' => $validated['category'],
        ], [
            'label' => self::CATEGORIES[$validated['category']],
            'tenant_commission_bps' => $tenantBps,
            'platform_commission_bps' => $platformBps,
            'is_active' => true,
            'approved_by_id' => (string) $user->uuid,
            'approved_at' => now(),
        ]);

        return redirect()
            ->route('vendor-marketplace.index')
            ->with('status', 'success')
            ->with('message', 'Commission rate card saved and approved.');
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('vendors.manage'), 403);

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(self::CATEGORIES))],
            'license_number' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $vendor = VendorProfile::create([
            'user_id' => (string) $user->uuid,
            'business_name' => $validated['business_name'],
            'code' => 'VND-'.mb_strtoupper(Str::random(6)),
            'category' => $validated['category'],
            'license_number' => $validated['license_number'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => 'verified',
            'rating' => 5.00,
        ]);

        return redirect()
            ->route('vendor-marketplace.index')
            ->with('status', 'success')
            ->with('message', "Service provider {$vendor->business_name} ({$vendor->code}) onboarded.");
    }

    public function storeJob(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('vendors.manage') && ! $user?->can('vendors.portal.access'), 403);

        $tenantId = (string) (app(TenantContext::class)->getTenant()?->id ?? '');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(self::CATEGORIES))],
            'source_module' => ['required', 'string', Rule::in(['real_estate', 'facilities', 'direct'])],
            'vendor_profile_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant.vendor_profiles', 'id')->where('tenant_id', $tenantId),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $job = ServiceJob::create([
            'job_number' => 'JOB-'.mb_strtoupper(Str::random(6)),
            'vendor_profile_id' => isset($validated['vendor_profile_id']) ? (int) $validated['vendor_profile_id'] : null,
            'requester_id' => (string) $user->uuid,
            'source_module' => $validated['source_module'],
            'category' => $validated['category'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'status' => ! empty($validated['vendor_profile_id']) ? ServiceJob::STATUS_ASSIGNED : ServiceJob::STATUS_OPEN,
        ]);

        return redirect()
            ->route('vendor-marketplace.index')
            ->with('status', 'success')
            ->with('message', "Service job {$job->job_number} dispatched.");
    }

    public function storeProforma(Request $request, ServiceJob $job): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('vendors.manage') && ! $user?->can('vendors.portal.access'), 403);

        $tenantId = (string) (app(TenantContext::class)->getTenant()?->id ?? '');

        $validated = $request->validate([
            'vendor_profile_id' => [
                'required',
                'integer',
                Rule::exists('tenant.vendor_profiles', 'id')->where('tenant_id', $tenantId),
            ],
            'gross_amount_ghs' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'valid_until' => ['nullable', 'date'],
        ]);

        $grossPesewas = (int) round(((float) $validated['gross_amount_ghs']) * 100);
        $split = CommissionRateCard::computeSplitForCategory($job->category, $grossPesewas);

        $proforma = ProformaInvoice::create([
            'invoice_number' => 'PFI-'.mb_strtoupper(Str::random(6)),
            'service_job_id' => $job->id,
            'vendor_profile_id' => (int) $validated['vendor_profile_id'],
            'client_id' => $job->requester_id,
            'currency' => 'GHS',
            'gross_amount_pesewas' => $grossPesewas,
            'tenant_commission_bps' => $split['tenant_commission_bps'],
            'platform_commission_bps' => $split['platform_commission_bps'],
            'tenant_commission_pesewas' => $split['tenant_commission_pesewas'],
            'platform_commission_pesewas' => $split['platform_commission_pesewas'],
            'net_vendor_payout_pesewas' => $split['net_vendor_payout_pesewas'],
            'notes' => $validated['notes'] ?? null,
            'status' => ProformaInvoice::STATUS_SUBMITTED,
            'valid_until' => $validated['valid_until'] ?? now()->addDays(14)->toDateString(),
        ]);

        $job->update([
            'vendor_profile_id' => (int) $validated['vendor_profile_id'],
            'status' => ServiceJob::STATUS_QUOTED,
        ]);

        return redirect()
            ->route('vendor-marketplace.index')
            ->with('status', 'success')
            ->with('message', "Proforma Invoice {$proforma->invoice_number} submitted with transparent commission disclosure.");
    }

    public function fundEscrow(
        Request $request,
        ProformaInvoice $proforma,
        EscrowSettlementService $settlement,
        PaymentGateway $gateway,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user?->can('escrow.manage'), 403);

        if ($request->boolean('pay_online')) {
            $checkoutUrl = $gateway->createOneTimeCheckoutSession(
                (string) ($user->email ?? 'buyer@example.com'),
                (int) $proforma->gross_amount_pesewas,
                (string) ($proforma->currency ?: 'GHS'),
                route('vendor-marketplace.index'),
                [
                    'type' => 'escrow_funding',
                    'proforma_id' => $proforma->id,
                    'user_uuid' => (string) $user->uuid,
                ]
            );

            return redirect()->away($checkoutUrl);
        }

        $idempotencyKey = (string) ($request->input('idempotency_key') ?: "fund:{$proforma->uuid}");

        try {
            $escrow = $settlement->fundFromProforma($proforma, (string) $user->uuid, $idempotencyKey);
        } catch (DomainException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return redirect()
            ->route('vendor-marketplace.index')
            ->with('status', 'success')
            ->with('message', "Escrow {$escrow->escrow_number} funded ({$escrow->formattedHeldBalance()}).");
    }

    public function releaseEscrow(
        Request $request,
        EscrowAccount $escrow,
        EscrowSettlementService $settlement,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user?->can('escrow.manage'), 403);

        $idempotencyKey = (string) ($request->input('idempotency_key') ?: "release:{$escrow->uuid}");
        $holdPayout = $request->boolean('hold_payout_for_disbursement');

        try {
            $settlement->releaseEscrow($escrow, (string) $user->uuid, $idempotencyKey, $holdPayout);
        } catch (DomainException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        $msg = $holdPayout
            ? "Escrow {$escrow->escrow_number} approved for payout; pending finance disbursement."
            : "Escrow {$escrow->escrow_number} settled via 3-way split.";

        return redirect()
            ->route('vendor-marketplace.index')
            ->with('status', 'success')
            ->with('message', $msg);
    }

    public function settlePayout(
        Request $request,
        EscrowAccount $escrow,
        EscrowSettlementService $settlement,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user?->can('escrow.manage'), 403);

        $validated = $request->validate([
            'payout_reference' => ['required', 'string', 'max:120'],
        ]);

        try {
            $settlement->settlePayout($escrow, (string) $user->uuid, $validated['payout_reference']);
        } catch (DomainException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return redirect()
            ->route('vendor-marketplace.index')
            ->with('status', 'success')
            ->with('message', "Vendor payout disbursed for Escrow {$escrow->escrow_number}.");
    }

    public function openDispute(
        Request $request,
        EscrowAccount $escrow,
        EscrowSettlementService $settlement,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user?->can('escrow.manage') && ! $user?->can('vendors.portal.access'), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $settlement->openDispute($escrow, (string) $user->uuid, $validated['reason']);
        } catch (DomainException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return redirect()
            ->route('vendor-marketplace.index')
            ->with('status', 'warning')
            ->with('message', "Escrow {$escrow->escrow_number} frozen due to active dispute.");
    }

    public function resolveDispute(
        Request $request,
        EscrowDispute $dispute,
        EscrowSettlementService $settlement,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user?->can('escrow.disputes.resolve'), 403);

        $validated = $request->validate([
            'resolution_type' => ['required', 'string', Rule::in([
                EscrowDispute::RESOLUTION_RELEASE_TO_VENDOR,
                EscrowDispute::RESOLUTION_REFUND_TO_BUYER,
            ])],
            'resolution_notes' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $idempotencyKey = (string) ($request->input('idempotency_key') ?: "resolve:{$dispute->uuid}");

        try {
            $settlement->resolveDispute(
                $dispute,
                (string) $user->uuid,
                $validated['resolution_type'],
                $validated['resolution_notes'],
                $idempotencyKey
            );
        } catch (DomainException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return redirect()
            ->route('vendor-marketplace.index')
            ->with('status', 'success')
            ->with('message', 'Escrow dispute resolved and ledger settled.');
    }
}
