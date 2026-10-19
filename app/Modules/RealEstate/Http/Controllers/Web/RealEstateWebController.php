<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Http\Controllers\Web;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\RealEstate\Models\ClientPlotAllocation;
use App\Modules\RealEstate\Models\InstallmentSchedule;
use App\Modules\RealEstate\Models\LandDocument;
use App\Modules\RealEstate\Models\LandParcel;
use App\Modules\RealEstate\Models\PropertyListing;
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
        abort_if(! $user || (! $user->can('realestate.view') && ! $user->can('realestate.manage')), 403);

        // All projects for dropdown selectors
        $allProjects = RealEstateProject::query()->orderBy('name')->get();

        // 1. Projects Query with Search, Filters and Pagination
        $projectsQuery = RealEstateProject::query()->withCount('parcels');
        if ($request->filled('project_search')) {
            $search = (string) $request->input('project_search');
            $projectsQuery->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%");
            });
        }
        if ($request->filled('project_status')) {
            $projectsQuery->where('status', $request->input('project_status'));
        }
        $projects = $projectsQuery->latest()->paginate(10, ['*'], 'projects_page')->withQueryString();

        // 2. Land Parcels Query with Search, Filters and Pagination
        $parcelsQuery = LandParcel::query()->with(['project', 'allocation.client', 'documents']);
        if ($request->filled('parcel_search')) {
            $search = (string) $request->input('parcel_search');
            $parcelsQuery->where(function ($q) use ($search): void {
                $q->where('plot_number', 'like', "%{$search}%")
                    ->orWhere('block_number', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($pq) use ($search): void {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }
        if ($request->filled('parcel_project_id')) {
            $parcelsQuery->where('real_estate_project_id', $request->input('parcel_project_id'));
        }
        if ($request->filled('parcel_stage')) {
            $parcelsQuery->where('current_stage', $request->input('parcel_stage'));
        }
        if ($request->filled('parcel_tenure')) {
            $parcelsQuery->where('tenure_type', $request->input('parcel_tenure'));
        }
        if ($request->filled('parcel_status')) {
            $parcelsQuery->where('status', $request->input('parcel_status'));
        }
        $parcels = $parcelsQuery->latest()->paginate(10, ['*'], 'parcels_page')->withQueryString();

        // 3. Allocations Query with Search, Filters and Pagination
        $allocationsQuery = ClientPlotAllocation::query()->with(['parcel.project', 'client', 'installments']);
        if ($request->filled('allocation_search')) {
            $search = (string) $request->input('allocation_search');
            $allocationsQuery->where(function ($q) use ($search): void {
                $q->where('allocation_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search): void {
                        $cq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('parcel', function ($pq) use ($search): void {
                        $pq->where('plot_number', 'like', "%{$search}%");
                    });
            });
        }
        if ($request->filled('allocation_status')) {
            $allocationsQuery->where('status', $request->input('allocation_status'));
        }
        $allocations = $allocationsQuery->latest()->paginate(10, ['*'], 'allocations_page')->withQueryString();

        // 4. Documents Query with Search, Filters and Pagination
        $documentsQuery = LandDocument::query()->with(['parcel.project', 'uploader']);
        if ($request->filled('doc_search')) {
            $search = (string) $request->input('doc_search');
            $documentsQuery->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('document_type', 'like', "%{$search}%")
                    ->orWhereHas('parcel', function ($pq) use ($search): void {
                        $pq->where('plot_number', 'like', "%{$search}%");
                    });
            });
        }
        if ($request->filled('doc_type')) {
            $documentsQuery->where('document_type', $request->input('doc_type'));
        }
        $documents = $documentsQuery->latest()->paginate(10, ['*'], 'documents_page')->withQueryString();

        // 5. Listings Query with Search, Filters and Pagination
        $listingsQuery = PropertyListing::query()->with(['parcel.project']);
        if ($request->filled('listing_search')) {
            $search = (string) $request->input('listing_search');
            $listingsQuery->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }
        $listings = $listingsQuery->latest()->paginate(10, ['*'], 'listings_page')->withQueryString();

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
            'allProjects' => $allProjects,
            'projects' => $projects,
            'parcels' => $parcels,
            'allocations' => $allocations,
            'documents' => $documents,
            'listings' => $listings,
            'metrics' => $metrics,
        ]);
    }

    public function storeProject(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant ? $tenant->id : '');

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

    public function editProject(Request $request, RealEstateProject $project): View
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        return view('real-estate::projects.edit', [
            'project' => $project,
        ]);
    }

    public function updateProject(Request $request, RealEstateProject $project): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant ? $tenant->id : '');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('tenant.real_estate_projects', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($project->id),
            ],
            'location' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'total_acreage' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(['active', 'planning', 'sold_out'])],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $project->update([
            'name' => $validated['name'],
            'code' => mb_strtoupper($validated['code']),
            'location' => $validated['location'],
            'region' => $validated['region'],
            'total_acreage' => $validated['total_acreage'],
            'status' => $validated['status'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Real Estate Project {$project->name} updated successfully.");
    }

    public function destroyProject(Request $request, RealEstateProject $project): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $name = $project->name;
        $project->delete();

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Project {$name} soft-deleted successfully.");
    }

    public function storeParcel(
        Request $request,
        SpatialOverlapDetectorService $overlapDetector,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant ? $tenant->id : '');

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

    public function editParcel(Request $request, LandParcel $parcel): View
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $allProjects = RealEstateProject::query()->orderBy('name')->get();

        return view('real-estate::parcels.edit', [
            'parcel' => $parcel,
            'projects' => $allProjects,
        ]);
    }

    public function updateParcel(
        Request $request,
        LandParcel $parcel,
        SpatialOverlapDetectorService $overlapDetector,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant ? $tenant->id : '');

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
            'status' => ['required', Rule::in(['available', 'reserved', 'allocated', 'sold'])],
            'is_legacy' => ['nullable', 'boolean'],
            'min_lat' => ['nullable', 'numeric'],
            'max_lat' => ['nullable', 'numeric'],
            'min_lng' => ['nullable', 'numeric'],
            'max_lng' => ['nullable', 'numeric'],
        ]);

        $projectId = (int) $validated['real_estate_project_id'];

        if (
            isset($validated['min_lat'], $validated['max_lat'], $validated['min_lng'], $validated['max_lng'])
        ) {
            try {
                $overlapDetector->assertNoOverlap($projectId, [
                    'min_lat' => (float) $validated['min_lat'],
                    'max_lat' => (float) $validated['max_lat'],
                    'min_lng' => (float) $validated['min_lng'],
                    'max_lng' => (float) $validated['max_lng'],
                ], $parcel->id);
            } catch (DomainException $e) {
                return back()->withInput()->withErrors(['spatial' => $e->getMessage()]);
            }
        }

        $pricePesewas = (int) round(((float) $validated['price_ghs']) * 100);

        $parcel->update([
            'real_estate_project_id' => $projectId,
            'plot_number' => mb_strtoupper($validated['plot_number']),
            'block_number' => ! empty($validated['block_number']) ? mb_strtoupper($validated['block_number']) : null,
            'size_dimension' => $validated['size_dimension'],
            'acreage' => $validated['acreage'],
            'price_pesewas' => $pricePesewas,
            'tenure_type' => $validated['tenure_type'],
            'status' => $validated['status'],
            'is_legacy' => ! empty($validated['is_legacy']),
            'min_lat' => $validated['min_lat'] ?? null,
            'max_lat' => $validated['max_lat'] ?? null,
            'min_lng' => $validated['min_lng'] ?? null,
            'max_lng' => $validated['max_lng'] ?? null,
        ]);

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Plot {$parcel->plot_number} updated successfully.");
    }

    public function destroyParcel(Request $request, LandParcel $parcel): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $plotNumber = $parcel->plot_number;
        $parcel->delete();

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Plot {$plotNumber} soft-deleted successfully.");
    }

    public function updateCompliance(Request $request, LandParcel $parcel): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.compliance.verify'), 403);

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
        abort_if(! $user || ! $user->can('realestate.compliance.verify'), 403);

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
        abort_if(! $user || ! $user->can('realestate.documents.manage'), 403);

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
        abort_if(! $user, 401);

        $isStaff = (bool) ($user->can('realestate.view') || $user->can('realestate.manage'));
        $isAllocatedClient = (bool) ($user->can('realestate.client.portal') && $document->parcel?->allocation?->client_user_id === (string) $user->uuid);
        abort_if(! $isStaff && ! $isAllocatedClient, 403);

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'Document vault asset file not found on disk.');
        }

        $fullPath = Storage::disk('local')->path($document->file_path);
        $response = response()->download($fullPath, basename($document->file_path));
        $response->headers->removeCacheControlDirective('public');
        $response->headers->addCacheControlDirective('private', true);
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('no-store', true);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function allocatePlot(Request $request, LandParcel $parcel): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant ? $tenant->id : '');

        $validated = $request->validate([
            'client_email' => ['required', 'email'],
            'client_name' => ['required', 'string', 'max:255'],
            'number_of_installments' => ['required', 'integer', 'min:1', 'max:24'],
        ]);

        // Find or provision client user without tenant scope since users is global
        $clientUser = User::withoutGlobalScopes()->where('email', $validated['client_email'])->first();
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

        // Bounded retry for unique allocation_number
        $allocationNumber = null;
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = 'ALC-'.mb_strtoupper(Str::random(6));
            if (! ClientPlotAllocation::withoutGlobalScopes()->where('allocation_number', $candidate)->exists()) {
                $allocationNumber = $candidate;
                break;
            }
        }
        $allocationNumber = $allocationNumber ?? ('ALC-'.mb_strtoupper(Str::random(10)));

        $allocation = ClientPlotAllocation::create([
            'land_parcel_id' => $parcel->id,
            'client_user_id' => (string) $clientUser->uuid,
            'allocation_number' => $allocationNumber,
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

    public function payInstallment(
        Request $request,
        InstallmentSchedule $installment,
        PaymentGateway $gateway
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user, 401);

        /** @var ClientPlotAllocation $allocation */
        $allocation = $installment->allocation()->firstOrFail();

        $isStaff = (bool) $user->can('realestate.manage');
        $isClient = (bool) ($user->can('realestate.client.portal') && $allocation->client_user_id === (string) $user->uuid);
        abort_if(! $isStaff && ! $isClient, 403);

        $due = (int) $installment->amount_due_pesewas;
        $alreadyPaid = (int) $installment->amount_paid_pesewas;
        $remainingDue = max(0, $due - $alreadyPaid);

        // If portal client, they CANNOT record payments directly or supply manual references.
        // Client payments MUST go exclusively through the payment gateway checkout session.
        if ($isClient && ! $isStaff) {
            $validated = $request->validate([
                'amount_ghs' => ['required', 'numeric', 'min:1'],
            ]);

            $pesewas = (int) round(((float) $validated['amount_ghs']) * 100);

            if ($pesewas > $remainingDue) {
                return back()->withErrors([
                    'amount_ghs' => 'Payment exceeds remaining installment due balance of GHS '.number_format($remainingDue / 100, 2),
                ]);
            }

            $redirectUrl = route('billing.callback');
            $checkoutUrl = $gateway->createOneTimeCheckoutSession(
                (string) ($user->email ?? 'customer@example.com'),
                $pesewas,
                'GHS',
                $redirectUrl,
                [
                    'type' => 'real_estate_installment',
                    'installment_id' => $installment->id,
                    'allocation_id' => $allocation->id,
                    'tenant_id' => (string) $installment->tenant_id,
                    'user_uuid' => (string) $user->uuid,
                ]
            );

            return redirect()->away($checkoutUrl);
        }

        // Staff payment path (realestate.manage)
        $validated = $request->validate([
            'amount_ghs' => ['required', 'numeric', 'min:1'],
            'payment_reference' => ['required_without:pay_online', 'nullable', 'string', 'max:120'],
            'pay_online' => ['nullable', 'boolean'],
        ]);

        $pesewas = (int) round(((float) $validated['amount_ghs']) * 100);

        if ($pesewas > $remainingDue) {
            return back()->withErrors([
                'amount_ghs' => 'Payment exceeds remaining installment due balance of GHS '.number_format($remainingDue / 100, 2),
            ]);
        }

        if ($request->boolean('pay_online')) {
            $redirectUrl = route('billing.callback');
            $checkoutUrl = $gateway->createOneTimeCheckoutSession(
                (string) ($user->email ?? 'staff@example.com'),
                $pesewas,
                'GHS',
                $redirectUrl,
                [
                    'type' => 'real_estate_installment',
                    'installment_id' => $installment->id,
                    'allocation_id' => $allocation->id,
                    'tenant_id' => (string) $installment->tenant_id,
                    'user_uuid' => (string) $user->uuid,
                ]
            );

            return redirect()->away($checkoutUrl);
        }

        if (empty($validated['payment_reference'])) {
            return back()->withErrors([
                'payment_reference' => 'Payment reference is mandatory for staff offline receipting.',
            ]);
        }

        $newPaid = $alreadyPaid + $pesewas;

        $installment->update([
            'amount_paid_pesewas' => $newPaid,
            'status' => $newPaid >= $due ? 'paid' : 'partially_paid',
            'paid_at' => now(),
            'payment_reference' => $validated['payment_reference'],
        ]);

        $allocation->increment('amount_paid_pesewas', $pesewas);

        if ($allocation->amount_paid_pesewas >= $allocation->total_price_pesewas) {
            $allocation->update(['status' => 'completed']);
            $allocation->parcel()->update(['status' => 'sold']);
        }

        activity('real_estate')
            ->performedOn($installment)
            ->causedBy($user)
            ->withProperties([
                'payment_reference' => $validated['payment_reference'],
                'amount_pesewas' => $pesewas,
                'installment_number' => $installment->installment_number,
                'allocation_id' => $allocation->id,
            ])
            ->log('Offline installment payment recorded');

        return back()
            ->with('status', 'success')
            ->with('message', "Payment of GHS {$validated['amount_ghs']} recorded against Installment #{$installment->installment_number}.");
    }

    public function receipt(Request $request, InstallmentSchedule $installment): View
    {
        $user = $request->user();
        abort_if(! $user, 401);

        /** @var ClientPlotAllocation $allocation */
        $allocation = $installment->allocation()->with(['parcel.project', 'client'])->firstOrFail();

        $isStaff = (bool) ($user->can('realestate.view') || $user->can('realestate.manage'));
        $isClient = (bool) ($user->can('realestate.client.portal') && (string) $allocation->client_user_id === (string) $user->uuid);
        abort_if(! $isStaff && ! $isClient, 403);

        abort_if($installment->status !== 'paid' && $installment->amount_paid_pesewas <= 0, 404, 'No payments recorded for this installment.');

        return view('real-estate::receipt', [
            'installment' => $installment,
            'allocation' => $allocation,
            'parcel' => $allocation->parcel,
            'project' => $allocation->parcel?->project,
            'client' => $allocation->client,
            'tenant' => app(TenantContext::class)->getTenant(),
        ]);
    }

    public function orderExpertOrMaterial(Request $request, LandParcel $parcel): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user, 401);

        $isStaff = (bool) $user->can('realestate.manage');
        $isAllocatedClient = (bool) ($user->can('realestate.client.portal') && $parcel->allocation?->client_user_id === (string) $user->uuid);
        abort_if(! $isStaff && ! $isAllocatedClient, 403);

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

        // Bounded retry for unique job_number
        $jobNumber = null;
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = 'JOB-RE-'.mb_strtoupper(Str::random(6));
            if (! ServiceJob::withoutGlobalScopes()->where('job_number', $candidate)->exists()) {
                $jobNumber = $candidate;
                break;
            }
        }
        $jobNumber = $jobNumber ?? ('JOB-RE-'.mb_strtoupper(Str::random(10)));

        $job = ServiceJob::create([
            'job_number' => $jobNumber,
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

    public function storeListing(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'price_ghs' => ['required', 'numeric', 'min:1'],
            'size_dimension' => ['required', 'string', 'max:64'],
            'tenure_type' => ['required', Rule::in(['STOOL', 'FAMILY', 'PRIVATE', 'STATE'])],
            'compliance_stage' => ['required', 'string', 'max:64'],
            'land_parcel_id' => ['nullable', 'integer'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'], // max 5MB
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')?->store('real-estate/listings', 'public');
        }

        $pricePesewas = (int) round(((float) $validated['price_ghs']) * 100);

        $listing = PropertyListing::create([
            'title' => $validated['title'],
            'location' => $validated['location'],
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'price_pesewas' => $pricePesewas,
            'size_dimension' => $validated['size_dimension'],
            'tenure_type' => $validated['tenure_type'],
            'compliance_stage' => $validated['compliance_stage'],
            'land_parcel_id' => ! empty($validated['land_parcel_id']) ? (int) $validated['land_parcel_id'] : null,
            'is_featured' => ! empty($validated['is_featured']),
            'is_published' => isset($validated['is_published']) ? (bool) $validated['is_published'] : true,
        ]);

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Listing '{$listing->title}' published successfully.");
    }

    public function editListing(Request $request, PropertyListing $listing): View
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $parcels = LandParcel::query()->with('project')->orderBy('plot_number')->get();

        return view('real-estate::listings.edit', [
            'listing' => $listing,
            'parcels' => $parcels,
        ]);
    }

    public function updateListing(Request $request, PropertyListing $listing): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'price_ghs' => ['required', 'numeric', 'min:1'],
            'size_dimension' => ['required', 'string', 'max:64'],
            'tenure_type' => ['required', Rule::in(['STOOL', 'FAMILY', 'PRIVATE', 'STATE'])],
            'compliance_stage' => ['required', 'string', 'max:64'],
            'land_parcel_id' => ['nullable', 'integer'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $updateData = [
            'title' => $validated['title'],
            'location' => $validated['location'],
            'description' => $validated['description'] ?? null,
            'price_pesewas' => (int) round(((float) $validated['price_ghs']) * 100),
            'size_dimension' => $validated['size_dimension'],
            'tenure_type' => $validated['tenure_type'],
            'compliance_stage' => $validated['compliance_stage'],
            'land_parcel_id' => ! empty($validated['land_parcel_id']) ? (int) $validated['land_parcel_id'] : null,
            'is_featured' => ! empty($validated['is_featured']),
            'is_published' => isset($validated['is_published']) ? (bool) $validated['is_published'] : false,
        ];

        if ($request->hasFile('image')) {
            $updateData['image_path'] = $request->file('image')?->store('real-estate/listings', 'public');
        }

        $listing->update($updateData);

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Listing '{$listing->title}' updated successfully.");
    }

    public function destroyListing(Request $request, PropertyListing $listing): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->can('realestate.manage'), 403);

        $title = $listing->title;
        $listing->delete();

        return redirect()
            ->route('real-estate.index')
            ->with('status', 'success')
            ->with('message', "Listing '{$title}' soft-deleted successfully.");
    }
}
