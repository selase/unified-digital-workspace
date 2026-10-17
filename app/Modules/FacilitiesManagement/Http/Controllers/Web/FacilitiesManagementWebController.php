<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'open_work_orders' => FacilityWorkOrder::query()->whereIn('status', [FacilityWorkOrder::STATUS_OPEN, FacilityWorkOrder::STATUS_ASSIGNED, FacilityWorkOrder::STATUS_IN_PROGRESS])->count(),
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
        ]);

        $building = FacilityBuilding::create([
            'name' => $validated['name'],
            'code' => mb_strtoupper($validated['code']),
            'address' => $validated['address'] ?? null,
            'floors_count' => $validated['floors_count'],
            'total_area_sqm' => $validated['total_area_sqm'],
            'manager_id' => (string) $user->uuid,
            'status' => 'active',
        ]);

        return redirect()
            ->route('facilities-management.index')
            ->with('status', 'success')
            ->with('message', "Facility Building {$building->name} ({$building->code}) registered.");
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
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(self::ASSET_CATEGORIES))],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model_number' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost_ghs' => ['nullable', 'numeric', 'min:0'],
            'criticality' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'mission_critical'])],
        ]);

        $costPesewas = isset($validated['purchase_cost_ghs']) ? (int) round(((float) $validated['purchase_cost_ghs']) * 100) : 0;
        $assetTag = 'AST-'.mb_strtoupper(Str::random(6));

        $asset = FacilityAsset::create([
            'facility_building_id' => (int) $validated['facility_building_id'],
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

        return redirect()
            ->route('facilities-management.index')
            ->with('status', 'success')
            ->with('message', "Preventive Maintenance Schedule '{$validated['title']}' created for Asset {$asset->asset_tag}.");
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
                Rule::exists('tenant.facility_assets', 'id')->where('tenant_id', $tenantId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(['electrical', 'plumbing', 'hvac', 'landscaping', 'water_delivery', 'general_maintenance'])],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'emergency'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
        ]);

        $order = FacilityWorkOrder::create([
            'work_order_number' => 'WO-INST-'.mb_strtoupper(Str::random(6)),
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
            'work_order_number' => 'WO-OCC-'.mb_strtoupper(Str::random(6)),
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

    public function completeWorkOrder(Request $request, FacilityWorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('facilities.manage'), 403);

        $validated = $request->validate([
            'completion_notes' => ['required', 'string', 'max:2000'],
        ]);

        $workOrder->update([
            'status' => FacilityWorkOrder::STATUS_COMPLETED,
            'completed_at' => now(),
            'completion_notes' => $validated['completion_notes'],
        ]);

        if ($workOrder->serviceJob) {
            $workOrder->serviceJob->update(['status' => ServiceJob::STATUS_COMPLETED]);
        }

        return redirect()
            ->route('facilities-management.index')
            ->with('status', 'success')
            ->with('message', "Work Order {$workOrder->work_order_number} marked completed.");
    }
}
