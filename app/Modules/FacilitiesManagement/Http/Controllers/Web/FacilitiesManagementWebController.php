<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\FacilitiesManagement\Models\FacilityAsset;
use App\Modules\FacilitiesManagement\Models\FacilityBuilding;
use App\Modules\FacilitiesManagement\Models\FacilitySpace;
use App\Modules\FacilitiesManagement\Models\FacilityWorkOrder;
use App\Modules\FacilitiesManagement\Models\PreventiveMaintenanceSchedule;
use App\Modules\VendorMarketplace\Models\ServiceJob;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

final class FacilitiesManagementWebController extends Controller
{
    public const ASSET_CATEGORIES = [
        'hvac' => 'HVAC & Air Conditioning Systems',
        'electrical' => 'Electrical & Power Infrastructure',
        'plumbing' => 'Plumbing & Water Reticulation',
        'fire_safety' => 'Fire Alarm & Suppression Systems',
        'furniture' => 'Office & Facility Fixtures',
        'medical_device' => 'Clinical & Medical Equipment',
        'it_infrastructure' => 'Data Center & IT Network Infrastructure',
        'general_maintenance' => 'General Building & Civil Assets',
    ];

    public const SPACE_TYPES = [
        'office' => 'Administrative Office',
        'ward' => 'Clinical Ward / Patient Room',
        'conference_room' => 'Conference & Meeting Room',
        'laboratory' => 'Diagnostic / Research Laboratory',
        'residential_apartment' => 'Residential Suite / Staff Apartment',
        'common_area' => 'Common / Public Core Area',
    ];

    public const CRITICALITY_LEVELS = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'mission_critical' => 'Mission Critical',
    ];

    public const ASSET_STATUSES = [
        'operational' => 'Operational',
        'needs_repair' => 'Needs Repair',
        'under_maintenance' => 'Under Maintenance',
        'decommissioned' => 'Decommissioned',
    ];

    public const BUILDING_STATUSES = [
        'active' => 'Active & Operational',
        'maintenance' => 'Under Maintenance',
        'decommissioned' => 'Decommissioned',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(
            ! $user?->can('facilities.view')
            && ! $user?->can('facilities.manage')
            && ! $user?->can('facilities.occupant.request'),
            403
        );

        $buildings = FacilityBuilding::query()->with('manager')->latest()->get();
        $spaces = FacilitySpace::query()->with('building')->latest()->limit(50)->get();
        $assets = FacilityAsset::query()->with(['building', 'space'])->latest()->limit(50)->get();
        $workOrders = FacilityWorkOrder::query()->with(['building', 'asset', 'requester', 'technician', 'serviceJob'])->latest()->limit(50)->get();
        $schedules = PreventiveMaintenanceSchedule::query()->with('asset.building')->latest()->limit(30)->get();

        $metrics = [
            'buildings_count' => FacilityBuilding::query()->count(),
            'assets_count' => FacilityAsset::query()->count(),
            'operational_assets' => FacilityAsset::query()->where('status', 'operational')->count(),
            'open_work_orders' => FacilityWorkOrder::query()->whereIn('status', [
                FacilityWorkOrder::STATUS_OPEN,
                FacilityWorkOrder::STATUS_ASSIGNED,
                FacilityWorkOrder::STATUS_IN_PROGRESS,
            ])->count(),
            'occupant_marketplace_orders' => FacilityWorkOrder::query()->where('mode', FacilityWorkOrder::MODE_OCCUPANT_MARKETPLACE)->count(),
            'due_schedules' => PreventiveMaintenanceSchedule::query()->where('is_active', true)->where('next_due_date', '<=', now()->addDays(7))->count(),
        ];

        return view('facilities-management::index', [
            'categories' => self::ASSET_CATEGORIES,
            'buildings' => $buildings,
            'spaces' => $spaces,
            'assets' => $assets,
            'workOrders' => $workOrders,
            'schedules' => $schedules,
            'metrics' => $metrics,
        ]);
    }

    public function storeBuilding(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant !== null ? $tenant->id : '');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('tenant.facility_buildings', 'code')->where('tenant_id', $tenantId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'floors_count' => ['required', 'integer', 'min:1', 'max:120'],
            'total_area_sqm' => ['required', 'numeric', 'min:0'],
            'manager_id' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
        ]);

        $building = FacilityBuilding::create([
            'name' => $validated['name'],
            'code' => mb_strtoupper($validated['code']),
            'address' => $validated['address'] ?? null,
            'floors_count' => (int) $validated['floors_count'],
            'total_area_sqm' => (float) $validated['total_area_sqm'],
            'manager_id' => $validated['manager_id'] ?? (string) $user->uuid,
            'status' => 'active',
        ]);

        return redirect()
            ->route('facilities-management.index')
            ->with('status', 'success')
            ->with('message', "Facility Building {$building->name} ({$building->code}) registered.");
    }

    public function showBuilding(Request $request, FacilityBuilding $building): View
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.view') && ! $user?->can('facilities.manage'), 403);

        $building->load([
            'manager:id,uuid,first_name,last_name,email',
            'spaces' => fn ($q) => $q->with('occupant:id,uuid,first_name,last_name,email')->latest('id'),
            'assets' => fn ($q) => $q->with('space')->latest('id'),
        ]);

        $workOrders = FacilityWorkOrder::query()
            ->where('facility_building_id', $building->id)
            ->with(['requester:id,uuid,first_name,last_name,email', 'technician:id,uuid,first_name,last_name,email', 'asset'])
            ->latest('id')
            ->limit(25)
            ->get();

        $tenantId = (string) $building->tenant_id;
        $users = User::query()
            ->where(function ($q) use ($tenantId): void {
                $q->where('tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($t) => $t->where('tenants.id', $tenantId));
            })
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email'])
            ->orderBy('first_name')
            ->get();

        return view('facilities-management::building-detail', [
            'building' => $building,
            'workOrders' => $workOrders,
            'users' => $users,
            'spaceTypes' => self::SPACE_TYPES,
            'categories' => self::ASSET_CATEGORIES,
            'buildingStatuses' => self::BUILDING_STATUSES,
        ]);
    }

    public function updateBuilding(Request $request, FacilityBuilding $building): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant !== null ? $tenant->id : '');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('tenant.facility_buildings', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($building->id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'floors_count' => ['required', 'integer', 'min:1', 'max:120'],
            'total_area_sqm' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(array_keys(self::BUILDING_STATUSES))],
            'manager_id' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
        ]);

        $building->update([
            'name' => $validated['name'],
            'code' => mb_strtoupper($validated['code']),
            'address' => $validated['address'] ?? null,
            'floors_count' => (int) $validated['floors_count'],
            'total_area_sqm' => (float) $validated['total_area_sqm'],
            'status' => $validated['status'],
            'manager_id' => $validated['manager_id'] ?? $building->manager_id,
        ]);

        return redirect()
            ->route('facilities-management.buildings.show', $building->uuid)
            ->with('status', 'success')
            ->with('message', "Facility Building {$building->name} updated successfully.");
    }

    public function destroyBuilding(Request $request, FacilityBuilding $building): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.manage'), 403);

        $name = $building->name;
        $building->delete();

        return redirect()
            ->route('facilities-management.index')
            ->with('status', 'success')
            ->with('message', "Building {$name} archived.");
    }

    public function storeSpace(Request $request, FacilityBuilding $building): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant !== null ? $tenant->id : '');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('tenant.facility_spaces', 'code')
                    ->where('tenant_id', $tenantId)
                    ->where('facility_building_id', $building->id),
            ],
            'floor_number' => ['required', 'integer', 'min:0', 'max:120'],
            'space_type' => ['required', 'string', Rule::in(array_keys(self::SPACE_TYPES))],
            'occupant_user_id' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
        ]);

        $space = FacilitySpace::create([
            'facility_building_id' => $building->id,
            'name' => $validated['name'],
            'code' => mb_strtoupper($validated['code']),
            'floor_number' => (int) $validated['floor_number'],
            'space_type' => $validated['space_type'],
            'occupant_user_id' => $validated['occupant_user_id'] ?? null,
            'is_occupied' => ! empty($validated['occupant_user_id']),
        ]);

        return redirect()
            ->route('facilities-management.buildings.show', $building->uuid)
            ->with('status', 'success')
            ->with('message', "Space {$space->name} ({$space->code}) added to {$building->name}.");
    }

    public function destroySpace(Request $request, FacilitySpace $space): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.manage'), 403);

        $building = $space->building;
        $name = $space->name;
        $space->delete();

        $redirectRoute = $building !== null
            ? route('facilities-management.buildings.show', $building->uuid)
            : route('facilities-management.index');

        return redirect($redirectRoute)
            ->with('status', 'success')
            ->with('message', "Space {$name} removed.");
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.assets.manage') && ! $user?->can('facilities.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant !== null ? $tenant->id : '');

        $validated = $request->validate([
            'facility_building_id' => [
                'required',
                'integer',
                Rule::exists('tenant.facility_buildings', 'id')->where('tenant_id', $tenantId),
            ],
            'facility_space_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant.facility_spaces', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('facility_building_id', $request->input('facility_building_id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(self::ASSET_CATEGORIES))],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model_number' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost_ghs' => ['nullable', 'numeric', 'min:0'],
            'criticality' => ['required', 'string', Rule::in(array_keys(self::CRITICALITY_LEVELS))],
        ]);

        $costPesewas = isset($validated['purchase_cost_ghs']) ? (int) round(((float) $validated['purchase_cost_ghs']) * 100) : 0;

        $assetTag = null;
        for ($i = 0; $i < 10; $i++) {
            $candidate = 'AST-'.mb_strtoupper(Str::random(6));
            if (! FacilityAsset::query()->where('asset_tag', $candidate)->exists()) {
                $assetTag = $candidate;
                break;
            }
        }
        if ($assetTag === null) {
            throw new RuntimeException('Failed to generate a unique asset tag after 10 attempts.');
        }

        $asset = FacilityAsset::create([
            'facility_building_id' => (int) $validated['facility_building_id'],
            'facility_space_id' => ! empty($validated['facility_space_id']) ? (int) $validated['facility_space_id'] : null,
            'asset_tag' => $assetTag,
            'name' => $validated['name'],
            'category' => $validated['category'],
            'serial_number' => $validated['serial_number'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'model_number' => $validated['model_number'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_cost_pesewas' => $costPesewas,
            'criticality' => $validated['criticality'],
            'status' => 'operational',
        ]);

        return redirect()
            ->route('facilities-management.index')
            ->with('status', 'success')
            ->with('message', "Asset {$asset->name} (Tag: {$asset->asset_tag}) registered in Asset Master.");
    }

    public function showAsset(Request $request, FacilityAsset $asset): View
    {
        $user = $request->user();
        abort_if(
            ! $user?->can('facilities.view')
            && ! $user?->can('facilities.manage')
            && ! $user?->can('facilities.assets.manage'),
            403
        );

        $asset->load([
            'building.manager',
            'space.occupant',
            'schedules' => fn ($q) => $q->latest('id'),
            'workOrders' => fn ($q) => $q->with(['requester:id,uuid,first_name,last_name,email', 'technician:id,uuid,first_name,last_name,email'])->latest('id'),
        ]);

        $spaces = FacilitySpace::query()
            ->where('facility_building_id', $asset->facility_building_id)
            ->get();

        $tenantId = (string) $asset->tenant_id;
        $users = User::query()
            ->where(function ($q) use ($tenantId): void {
                $q->where('tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($t) => $t->where('tenants.id', $tenantId));
            })
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email'])
            ->orderBy('first_name')
            ->get();

        return view('facilities-management::asset-detail', [
            'asset' => $asset,
            'spaces' => $spaces,
            'users' => $users,
            'categories' => self::ASSET_CATEGORIES,
            'criticalityLevels' => self::CRITICALITY_LEVELS,
            'assetStatuses' => self::ASSET_STATUSES,
        ]);
    }

    public function updateAsset(Request $request, FacilityAsset $asset): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.assets.manage') && ! $user?->can('facilities.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant !== null ? $tenant->id : '');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(self::ASSET_CATEGORIES))],
            'criticality' => ['required', 'string', Rule::in(array_keys(self::CRITICALITY_LEVELS))],
            'status' => ['required', 'string', Rule::in(array_keys(self::ASSET_STATUSES))],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model_number' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost_ghs' => ['nullable', 'numeric', 'min:0'],
            'warranty_expiry_date' => ['nullable', 'date'],
            'facility_space_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant.facility_spaces', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('facility_building_id', $asset->facility_building_id),
            ],
        ]);

        $costPesewas = isset($validated['purchase_cost_ghs']) && $validated['purchase_cost_ghs'] !== ''
            ? (int) round(((float) $validated['purchase_cost_ghs']) * 100)
            : $asset->purchase_cost_pesewas;

        $asset->update([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'criticality' => $validated['criticality'],
            'status' => $validated['status'],
            'serial_number' => $validated['serial_number'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'model_number' => $validated['model_number'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_cost_pesewas' => $costPesewas,
            'warranty_expiry_date' => $validated['warranty_expiry_date'] ?? null,
            'facility_space_id' => ! empty($validated['facility_space_id']) ? (int) $validated['facility_space_id'] : null,
        ]);

        return redirect()
            ->route('facilities-management.assets.show', $asset->uuid)
            ->with('status', 'success')
            ->with('message', "Asset {$asset->name} updated successfully.");
    }

    public function destroyAsset(Request $request, FacilityAsset $asset): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.assets.manage') && ! $user?->can('facilities.manage'), 403);

        $name = $asset->name;
        $tag = $asset->asset_tag;
        $asset->delete();

        return redirect()
            ->route('facilities-management.index')
            ->with('status', 'success')
            ->with('message', "Asset {$name} ({$tag}) archived.");
    }

    public function storeSchedule(Request $request, FacilityAsset $asset): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.maintenance.manage') && ! $user?->can('facilities.manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'biannual', 'annual'])],
            'interval_days' => ['required', 'integer', 'min:1', 'max:365'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $intervalDays = (int) $validated['interval_days'];

        PreventiveMaintenanceSchedule::create([
            'facility_asset_id' => $asset->id,
            'title' => $validated['title'],
            'frequency' => $validated['frequency'],
            'interval_days' => $intervalDays,
            'description' => $validated['description'] ?? null,
            'next_due_date' => now()->addDays($intervalDays)->toDateString(),
            'is_active' => true,
        ]);

        $redirect = $request->filled('redirect_to')
            ? redirect($request->input('redirect_to'))
            : redirect()->route('facilities-management.index');

        return $redirect
            ->with('status', 'success')
            ->with('message', "Preventive Maintenance Schedule '{$validated['title']}' created for Asset {$asset->asset_tag}.");
    }

    public function toggleSchedule(Request $request, PreventiveMaintenanceSchedule $schedule): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.maintenance.manage') && ! $user?->can('facilities.manage'), 403);

        $schedule->update([
            'is_active' => ! $schedule->is_active,
        ]);

        $statusText = $schedule->is_active ? 'activated' : 'paused';

        $asset = $schedule->asset;
        $redirectUrl = $asset !== null
            ? route('facilities-management.assets.show', $asset->uuid)
            : route('facilities-management.index');

        return redirect($redirectUrl)
            ->with('status', 'success')
            ->with('message', "Schedule '{$schedule->title}' has been {$statusText}.");
    }

    public function destroySchedule(Request $request, PreventiveMaintenanceSchedule $schedule): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.maintenance.manage') && ! $user?->can('facilities.manage'), 403);

        $asset = $schedule->asset;
        $title = $schedule->title;
        $schedule->delete();

        $redirectUrl = $asset !== null
            ? route('facilities-management.assets.show', $asset->uuid)
            : route('facilities-management.index');

        return redirect($redirectUrl)
            ->with('status', 'success')
            ->with('message', "Schedule '{$title}' removed.");
    }

    public function storeInstitutionalWorkOrder(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $tenantId = (string) ($tenant !== null ? $tenant->id : '');

        $validated = $request->validate([
            'facility_building_id' => [
                'required',
                'integer',
                Rule::exists('tenant.facility_buildings', 'id')->where('tenant_id', $tenantId),
            ],
            'facility_asset_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant.facility_assets', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('facility_building_id', $request->input('facility_building_id')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(['electrical', 'plumbing', 'hvac', 'landscaping', 'water_delivery', 'general_maintenance'])],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'emergency'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
        ]);

        $workOrderNumber = null;
        for ($i = 0; $i < 10; $i++) {
            $candidate = 'WO-INST-'.mb_strtoupper(Str::random(6));
            if (! FacilityWorkOrder::query()->where('work_order_number', $candidate)->exists()) {
                $workOrderNumber = $candidate;
                break;
            }
        }
        if ($workOrderNumber === null) {
            throw new RuntimeException('Failed to generate a unique work order number after 10 attempts.');
        }

        $order = FacilityWorkOrder::create([
            'work_order_number' => $workOrderNumber,
            'mode' => FacilityWorkOrder::MODE_INSTITUTIONAL,
            'facility_building_id' => (int) $validated['facility_building_id'],
            'facility_asset_id' => ! empty($validated['facility_asset_id']) ? (int) $validated['facility_asset_id'] : null,
            'requester_id' => (string) $user->uuid,
            'category' => $validated['category'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'status' => FacilityWorkOrder::STATUS_OPEN,
            'due_date' => $validated['due_date'] ?? now()->addDays(3)->toDateString(),
        ]);

        \App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent::dispatch(
            (string) $order->tenant_id,
            'facilities.request.created',
            [
                'id' => $order->id,
                'work_order_number' => $order->work_order_number,
                'mode' => $order->mode,
                'title' => $order->title,
                'category' => $order->category,
                'priority' => $order->priority,
                'status' => $order->status,
            ]
        );

        return redirect()
            ->route('facilities-management.index')
            ->with('status', 'success')
            ->with('message', "Institutional Work Order {$order->work_order_number} created.");
    }

    public function storeOccupantWorkOrder(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.occupant.request') && ! $user?->can('facilities.manage'), 403);

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(['plumbing', 'electrical', 'landscaping', 'water_delivery', 'general_maintenance'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        $workOrderNumber = null;
        for ($i = 0; $i < 10; $i++) {
            $candidate = 'WO-OCC-'.mb_strtoupper(Str::random(6));
            if (! FacilityWorkOrder::query()->where('work_order_number', $candidate)->exists()) {
                $workOrderNumber = $candidate;
                break;
            }
        }
        if ($workOrderNumber === null) {
            throw new RuntimeException('Failed to generate a unique work order number after 10 attempts.');
        }

        /** @var array{0: ServiceJob, 1: FacilityWorkOrder} $created */
        $created = DB::transaction(function () use ($validated, $user, $workOrderNumber): array {
            // 1. Dispatch ServiceJob in VendorMarketplace
            $job = ServiceJob::create([
                'job_number' => 'JOB-FAC-'.mb_strtoupper(Str::random(6)),
                'requester_id' => (string) $user->uuid,
                'source_module' => 'facilities',
                'category' => $validated['category'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'location' => $validated['location'],
                'status' => ServiceJob::STATUS_OPEN,
            ]);

            // 2. Create FacilityWorkOrder linking ServiceJob
            $order = FacilityWorkOrder::create([
                'work_order_number' => $workOrderNumber,
                'mode' => FacilityWorkOrder::MODE_OCCUPANT_MARKETPLACE,
                'requester_id' => (string) $user->uuid,
                'category' => $validated['category'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'priority' => 'medium',
                'status' => FacilityWorkOrder::STATUS_OPEN,
                'due_date' => now()->addDays(2)->toDateString(),
                'service_job_id' => $job->id,
            ]);

            return [$job, $order];
        });

        [$job, $order] = $created;

        \App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent::dispatch(
            (string) $order->tenant_id,
            'facilities.request.created',
            [
                'id' => $order->id,
                'work_order_number' => $order->work_order_number,
                'mode' => $order->mode,
                'title' => $order->title,
                'category' => $order->category,
                'priority' => $order->priority,
                'status' => $order->status,
            ]
        );

        return redirect()
            ->route('facilities-management.index')
            ->with('status', 'success')
            ->with('message', "Artisan Request {$order->work_order_number} dispatched to Marketplace (Job: {$job->job_number}).");
    }

    public function showWorkOrder(Request $request, FacilityWorkOrder $workOrder): View
    {
        $user = $request->user();
        $canManage = $user?->can('facilities.manage') ?? false;
        $canView = $user?->can('facilities.view') ?? false;
        $canRequest = $user?->can('facilities.occupant.request') ?? false;

        abort_if(! $canManage && ! $canView && ! $canRequest, 403);

        // Occupant requesting users can only view their own work orders
        if (! $canManage && ! $canView && $canRequest) {
            abort_if((string) $workOrder->requester_id !== (string) $user->uuid, 403);
        }

        $workOrder->load([
            'building:id,uuid,name,code',
            'space:id,uuid,name,code',
            'asset:id,uuid,name,asset_tag,category',
            'requester:id,uuid,first_name,last_name,email',
            'technician:id,uuid,first_name,last_name,email',
            'serviceJob',
        ]);

        $tenantId = (string) $workOrder->tenant_id;
        $technicians = User::query()
            ->where(function ($q) use ($tenantId): void {
                $q->where('tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($t) => $t->where('tenants.id', $tenantId));
            })
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email'])
            ->orderBy('first_name')
            ->get();

        return view('facilities-management::work-order-detail', [
            'workOrder' => $workOrder,
            'technicians' => $technicians,
            'categories' => self::ASSET_CATEGORIES,
        ]);
    }

    public function assignWorkOrder(Request $request, FacilityWorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.manage'), 403);
        abort_if($workOrder->status === FacilityWorkOrder::STATUS_COMPLETED, 422, 'Cannot assign a completed work order.');

        $validated = $request->validate([
            'assigned_technician_id' => ['required', 'uuid', Rule::exists('users', 'uuid')],
        ]);

        $technicianExists = User::query()
            ->where('uuid', $validated['assigned_technician_id'])
            ->where(function ($q) use ($workOrder): void {
                $q->where('tenant_id', $workOrder->tenant_id)
                    ->orWhereHas('tenants', fn ($t) => $t->where('tenants.id', $workOrder->tenant_id));
            })
            ->exists();

        abort_if(! $technicianExists, 404, 'The selected technician does not belong to this tenant.');

        $status = $workOrder->status === FacilityWorkOrder::STATUS_OPEN
            ? FacilityWorkOrder::STATUS_ASSIGNED
            : $workOrder->status;

        $workOrder->update([
            'assigned_technician_id' => $validated['assigned_technician_id'],
            'status' => $status,
        ]);

        return redirect()
            ->route('facilities-management.work-orders.show', $workOrder->uuid)
            ->with('status', 'success')
            ->with('message', "Technician assigned to Work Order {$workOrder->work_order_number}.");
    }

    public function updateWorkOrderStatus(Request $request, FacilityWorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.manage'), 403);
        abort_if($workOrder->status === FacilityWorkOrder::STATUS_COMPLETED, 422, 'Cannot update a completed work order.');

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                FacilityWorkOrder::STATUS_OPEN,
                FacilityWorkOrder::STATUS_ASSIGNED,
                FacilityWorkOrder::STATUS_IN_PROGRESS,
                FacilityWorkOrder::STATUS_CANCELLED,
            ])],
        ]);

        $workOrder->update([
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('facilities-management.work-orders.show', $workOrder->uuid)
            ->with('status', 'success')
            ->with('message', "Work Order status updated to {$validated['status']}.");
    }

    public function completeWorkOrder(Request $request, FacilityWorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.manage'), 403);
        abort_if($workOrder->status === FacilityWorkOrder::STATUS_COMPLETED, 422, 'Work order is already completed.');

        $validated = $request->validate([
            'completion_notes' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($workOrder, $validated): void {
            $workOrder->update([
                'status' => FacilityWorkOrder::STATUS_COMPLETED,
                'completed_at' => now(),
                'completion_notes' => $validated['completion_notes'],
            ]);

            if ($workOrder->serviceJob) {
                $workOrder->serviceJob->update(['status' => ServiceJob::STATUS_COMPLETED]);
            }
        });

        $redirect = $request->filled('redirect_to')
            ? redirect($request->input('redirect_to'))
            : redirect()->route('facilities-management.index');

        return $redirect
            ->with('status', 'success')
            ->with('message', "Work Order {$workOrder->work_order_number} marked completed.");
    }
}
