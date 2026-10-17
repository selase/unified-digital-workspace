<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\RealEstate\Models\ClientPlotAllocation;
use App\Modules\RealEstate\Models\InstallmentSchedule;
use App\Modules\RealEstate\Models\LandDocument;
use App\Modules\RealEstate\Models\LandParcel;
use App\Modules\RealEstate\Models\RealEstateProject;
use App\Modules\RealEstate\Services\LandAcquisitionStateMachine;
use App\Modules\RealEstate\Services\SpatialOverlapDetectorService;
use App\Modules\VendorMarketplace\Models\ServiceJob;
use App\Services\Tenancy\TenantContext;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class RealEstateWebController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user?->can('realestate.view') && ! $user?->can('realestate.manage'), 403);

        $projects = RealEstateProject::query()->latest()->get();
        $parcels = LandParcel::query()
            ->with(['project', 'allocation.client', 'documents'])
            ->latest()
            ->limit(30)
            ->get();
        $allocations = ClientPlotAllocation::query()
            ->with(['parcel.project', 'client', 'installments'])
            ->latest()
            ->limit(20)
            ->get();
        $documents = LandDocument::query()
            ->with(['parcel.project', 'uploader'])
            ->latest()
            ->limit(20)
            ->get();

        $metrics = [
            'total_projects' => RealEstateProject::query()->count(),
            'total_parcels' => LandParcel::query()->count(),
            'parcels_in_due_diligence' => LandParcel::query()->where('current_stage', LandParcel::STAGE_DUE_DILIGENCE)->count(),
            'parcels_ready_to_build' => LandParcel::query()->where('current_stage', LandParcel::STAGE_READY_TO_BUILD)->count(),
            'unsecured_boundary_warnings' => LandParcel::query()
                ->whereIn('current_stage', [LandParcel::STAGE_REGISTRATION, LandParcel::STAGE_PERMITTING, LandParcel::STAGE_READY_TO_BUILD])
                ->where('pillars_and_wall_secured', false)
                ->count(),
            'total_allocated_ghs' => number_format((float) ClientPlotAllocation::query()->sum('total_price_pesewas') / 100, 2),
            'total_collected_ghs' => number_format((float) ClientPlotAllocation::query()->sum('amount_paid_pesewas') / 100, 2),
        ];

        return view('real-estate::index', [
            'projects' => $projects,
            'parcels' => $parcels,
            'allocations' => $allocations,
            'documents' => $documents,
            'metrics' => $metrics,
        ]);
    }

    public function storeProject(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('realestate.manage'), 403);

        $tenantId = (string) (app(TenantContext::class)->getTenant()?->id ?? '');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('tenant.real_estate_projects', 'code')->where('tenant_id', $tenantId),
            ],
            'location' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'total_acreage' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $project = RealEstateProject::create([
            'name' => $validated['name'],
            'code' => mb_strtoupper($validated['code']),
            'location' => $validated['location'],
            'region' => $validated['region'],
            'total_acreage' => $validated['total_acreage'],
            'description' => $validated['description'] ?? null,
            'status' => 'active',
        ]);

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Real Estate Project {$project->name} ({$project->code}) created.");
    }

    public function storeParcel(
        Request $request,
        SpatialOverlapDetectorService $overlapDetector,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user?->can('realestate.manage'), 403);

        $tenantId = (string) (app(TenantContext::class)->getTenant()?->id ?? '');

        $validated = $request->validate([
            'real_estate_project_id' => [
                'required',
                'integer',
                Rule::exists('tenant.real_estate_projects', 'id')->where('tenant_id', $tenantId),
            ],
            'plot_number' => ['required', 'string', 'max:64'],
            'block_number' => ['nullable', 'string', 'max:64'],
            'size_dimension' => ['required', 'string', 'max:64'],
            'acreage' => ['required', 'numeric', 'min:0.01'],
            'price_ghs' => ['required', 'numeric', 'min:1'],
            'tenure_type' => ['required', Rule::in(['STOOL', 'FAMILY', 'PRIVATE', 'STATE'])],
            'is_legacy' => ['nullable', 'boolean'],
            'min_lat' => ['nullable', 'numeric'],
            'max_lat' => ['nullable', 'numeric'],
            'min_lng' => ['nullable', 'numeric'],
            'max_lng' => ['nullable', 'numeric'],
        ]);

        $projectId = (int) $validated['real_estate_project_id'];

        // Enforce spatial overlap verification if coordinates are supplied
        if (
            isset($validated['min_lat'], $validated['max_lat'], $validated['min_lng'], $validated['max_lng'])
        ) {
            try {
                $overlapDetector->assertNoOverlap($projectId, [
                    'min_lat' => (float) $validated['min_lat'],
                    'max_lat' => (float) $validated['max_lat'],
                    'min_lng' => (float) $validated['min_lng'],
                    'max_lng' => (float) $validated['max_lng'],
                ]);
            } catch (DomainException $e) {
                return back()->withInput()->withErrors(['spatial' => $e->getMessage()]);
            }
        }

        $pricePesewas = (int) round(((float) $validated['price_ghs']) * 100);

        $parcel = LandParcel::create([
            'real_estate_project_id' => $projectId,
            'plot_number' => mb_strtoupper($validated['plot_number']),
            'block_number' => ! empty($validated['block_number']) ? mb_strtoupper($validated['block_number']) : null,
            'size_dimension' => $validated['size_dimension'],
            'acreage' => $validated['acreage'],
            'price_pesewas' => $pricePesewas,
            'tenure_type' => $validated['tenure_type'],
            'is_legacy' => ! empty($validated['is_legacy']),
            'current_stage' => LandParcel::STAGE_DUE_DILIGENCE,
            'status' => 'available',
            'min_lat' => $validated['min_lat'] ?? null,
            'max_lat' => $validated['max_lat'] ?? null,
            'min_lng' => $validated['min_lng'] ?? null,
            'max_lng' => $validated['max_lng'] ?? null,
        ]);

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Plot {$parcel->plot_number} registered in Due Diligence stage.");
    }

    public function updateCompliance(Request $request, LandParcel $parcel): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('realestate.compliance.verify'), 403);

        $validated = $request->validate([
            // Due Diligence Fields
            'inspection_date' => ['nullable', 'date'],
            'lands_commission_search_number' => ['nullable', 'string', 'max:120'],
            'search_issue_date' => ['nullable', 'date'],
            'has_traditional_consent_letter' => ['nullable', 'boolean'],
            'has_state_allocation_note' => ['nullable', 'boolean'],
            'is_due_diligence_verified' => ['nullable', 'boolean'],

            // Registration Fields
            'indenture_reference' => ['nullable', 'string', 'max:120'],
            'land_title_certificate_number' => ['nullable', 'string', 'max:120'],
            'stamp_duty_paid' => ['nullable', 'boolean'],
            'pillars_and_wall_secured' => ['nullable', 'boolean'],
            'registration_date' => ['nullable', 'date'],

            // Permitting Fields
            'architect_gia_number' => ['nullable', 'string', 'max:64'],
            'structural_engineer_ghie_number' => ['nullable', 'string', 'max:64'],
            'mmda_permit_number' => ['nullable', 'string', 'max:120'],
            'permit_issue_date' => ['nullable', 'date'],
            'requires_epa_clearance' => ['nullable', 'boolean'],
            'epa_clearance_number' => ['nullable', 'string', 'max:120'],
            'is_permit_active' => ['nullable', 'boolean'],
        ]);

        $updateData = [];

        foreach ($validated as $key => $val) {
            $updateData[$key] = $val;
        }

        if (! empty($validated['is_due_diligence_verified'])) {
            $updateData['due_diligence_verified_by_id'] = (string) $user->uuid;
            $updateData['due_diligence_verified_at'] = now();
        }

        $parcel->update($updateData);

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Compliance records updated for Plot {$parcel->plot_number}.");
    }

    public function transitionStage(
        Request $request,
        LandParcel $parcel,
        LandAcquisitionStateMachine $stateMachine,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user?->can('realestate.compliance.verify'), 403);

        $validated = $request->validate([
            'target_stage' => ['required', 'string', Rule::in([
                LandParcel::STAGE_REGISTRATION,
                LandParcel::STAGE_PERMITTING,
                LandParcel::STAGE_READY_TO_BUILD,
            ])],
        ]);

        try {
            $stateMachine->transitionTo($parcel, $validated['target_stage'], (string) $user->uuid);
        } catch (DomainException $e) {
            return back()->withErrors(['stage' => $e->getMessage()]);
        }

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Plot {$parcel->plot_number} transitioned to {$validated['target_stage']}.");
    }

    public function storeDocument(Request $request, LandParcel $parcel): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('realestate.documents.manage'), 403);

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:20480'], // max 20MB
            'is_legacy' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $storedPath = $file ? $file->store("tenants/{$parcel->tenant_id}/real-estate/{$parcel->uuid}", 'local') : '';
        $hash = $file ? hash_file('sha256', $file->getRealPath()) : null;

        $doc = LandDocument::create([
            'land_parcel_id' => $parcel->id,
            'workflow_stage' => $parcel->current_stage,
            'document_type' => $validated['document_type'],
            'title' => $validated['title'],
            'file_path' => $storedPath,
            'file_hash' => $hash,
            'is_legacy' => ! empty($validated['is_legacy']),
            'verification_status' => 'VERIFIED',
            'uploaded_by_id' => (string) $user->uuid,
            'verified_by_id' => (string) $user->uuid,
        ]);

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Document '{$doc->title}' securely vaulted for Plot {$parcel->plot_number}.");
    }

    public function downloadDocument(Request $request, LandDocument $document): BinaryFileResponse
    {
        $user = $request->user();

        // Security check: must have realestate view/manage permission OR be the allocated client
        $isAllocatedClient = $document->parcel?->allocation?->client_user_id === (string) $user?->uuid;
        abort_if(! $user?->can('realestate.view') && ! $user?->can('realestate.client.portal') && ! $isAllocatedClient, 403);

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'Document vault asset file not found on disk.');
        }

        $fullPath = Storage::disk('local')->path($document->file_path);

        $response = response()->download($fullPath, basename($document->file_path));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store', true);
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }

    public function allocatePlot(Request $request, LandParcel $parcel): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('realestate.manage'), 403);

        $tenantId = (string) (app(TenantContext::class)->getTenant()?->id ?? '');

        $validated = $request->validate([
            'client_email' => ['required', 'email'],
            'client_name' => ['required', 'string', 'max:255'],
            'number_of_installments' => ['required', 'integer', 'min:1', 'max:24'],
        ]);

        // Find or provision client user
        $clientUser = User::query()->where('email', $validated['client_email'])->first();
        if (! $clientUser instanceof User) {
            $nameParts = explode(' ', mb_trim($validated['client_name']), 2);
            $clientUser = new User();
            $clientUser->uuid = (string) Str::uuid();
            $clientUser->tenant_id = $tenantId;
            $clientUser->first_name = $nameParts[0];
            $clientUser->last_name = $nameParts[1] ?? 'Client';
            $clientUser->email = $validated['client_email'];
            $clientUser->password = bcrypt(Str::random(16));
            $clientUser->forceFill(['email_verified_at' => now()])->save();
            $clientUser->tenants()->syncWithoutDetaching([$tenantId]);
        }

        $totalPrice = (int) $parcel->price_pesewas;
        $numInstallments = (int) $validated['number_of_installments'];
        $installmentAmount = (int) intdiv($totalPrice, $numInstallments);

        $allocation = ClientPlotAllocation::create([
            'land_parcel_id' => $parcel->id,
            'client_user_id' => (string) $clientUser->uuid,
            'allocation_number' => 'ALC-'.mb_strtoupper(Str::random(6)),
            'total_price_pesewas' => $totalPrice,
            'amount_paid_pesewas' => 0,
            'status' => 'active',
            'allocated_at' => now(),
        ]);

        $parcel->update(['status' => 'allocated']);

        // Generate installments
        for ($i = 1; $i <= $numInstallments; $i++) {
            $dueAmount = ($i === $numInstallments)
                ? ($totalPrice - ($installmentAmount * ($numInstallments - 1)))
                : $installmentAmount;

            InstallmentSchedule::create([
                'client_plot_allocation_id' => $allocation->id,
                'installment_number' => $i,
                'due_date' => now()->addMonths($i)->toDateString(),
                'amount_due_pesewas' => $dueAmount,
                'amount_paid_pesewas' => 0,
                'status' => 'pending',
            ]);
        }

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Plot {$parcel->plot_number} allocated to {$validated['client_name']} ({$allocation->allocation_number}).");
    }

    public function payInstallment(Request $request, InstallmentSchedule $installment): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('realestate.manage') && ! $user?->can('realestate.client.portal'), 403);

        $validated = $request->validate([
            'amount_ghs' => ['required', 'numeric', 'min:1'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $pesewas = (int) round(((float) $validated['amount_ghs']) * 100);
        $due = (int) $installment->amount_due_pesewas;
        $newPaid = $installment->amount_paid_pesewas + $pesewas;

        $installment->update([
            'amount_paid_pesewas' => $newPaid,
            'status' => $newPaid >= $due ? 'paid' : 'partially_paid',
            'paid_at' => now(),
            'payment_reference' => $validated['payment_reference'] ?? 'PAY-'.mb_strtoupper(Str::random(8)),
        ]);

        /** @var ClientPlotAllocation $allocation */
        $allocation = $installment->allocation()->firstOrFail();
        $allocation->increment('amount_paid_pesewas', $pesewas);

        if ($allocation->amount_paid_pesewas >= $allocation->total_price_pesewas) {
            $allocation->update(['status' => 'completed']);
            $allocation->parcel()->update(['status' => 'sold']);
        }

        return back()
            ->with('status', 'success')
            ->with('message', "Payment of GHS {$validated['amount_ghs']} recorded against Installment #{$installment->installment_number}.");
    }

    public function orderExpertOrMaterial(Request $request, LandParcel $parcel): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('realestate.manage') && ! $user?->can('realestate.client.portal'), 403);

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in([
                'surveying',
                'land_registration',
                'architecture_engineering',
                'building_materials',
            ])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $job = ServiceJob::create([
            'job_number' => 'JOB-RE-'.mb_strtoupper(Str::random(6)),
            'requester_id' => (string) $user->uuid,
            'source_module' => 'real_estate',
            'category' => $validated['category'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? "Work for Plot {$parcel->plot_number} ({$parcel->project?->name})",
            'location' => "Plot {$parcel->plot_number}, {$parcel->project?->location}",
            'status' => ServiceJob::STATUS_OPEN,
        ]);

        return back()
            ->with('status', 'success')
            ->with('message', "Service request {$job->job_number} dispatched to Vendor Marketplace.");
    }
}
