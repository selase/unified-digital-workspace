<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\QualityMonitoring\Http\Requests\WorkplanStoreRequest;
use App\Modules\QualityMonitoring\Models\Alert;
use App\Modules\QualityMonitoring\Models\Kpi;
use App\Modules\QualityMonitoring\Models\KpiUpdate;
use App\Modules\QualityMonitoring\Models\Review;
use App\Modules\QualityMonitoring\Models\Variance;
use App\Modules\QualityMonitoring\Models\Workplan;
use App\Modules\QualityMonitoring\Models\WorkplanVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkplanWebController extends Controller
{
    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('qm.workplans.manage'), 403);

        $users = User::query()
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email'])
            ->orderBy('first_name')
            ->get();

        return view('quality-monitoring::create', [
            'users' => $users,
        ]);
    }

    public function store(WorkplanStoreRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['owner_id'] = $payload['owner_id'] ?? (string) $request->user()?->uuid;
        $payload['status'] = $payload['status'] ?? 'draft';

        /** @var Workplan $workplan */
        $workplan = DB::transaction(function () use ($payload, $request): Workplan {
            /** @var Workplan $created */
            $created = Workplan::create($payload);

            WorkplanVersion::create([
                'tenant_id' => $created->tenant_id,
                'workplan_id' => $created->id,
                'version_no' => 1,
                'status' => 'draft',
                'payload' => $payload,
                'submitted_at' => null,
                'created_by' => (string) $request->user()?->uuid,
            ]);

            return $created;
        });

        return redirect()
            ->route('quality-monitoring.workplans.show', $workplan)
            ->with('status', 'success')
            ->with('message', 'Quality workplan initialized successfully.');
    }

    public function show(Request $request, Workplan $workplan): View
    {
        abort_if(! $request->user()?->can('qm.workplans.view'), 403);

        $workplan->load([
            'owner:id,uuid,first_name,last_name,email',
            'versions' => fn ($q) => $q->orderByDesc('version_no'),
            'reviews' => fn ($q) => $q->with('reviewer:id,uuid,first_name,last_name,email')->latest('id'),
            'objectives' => fn ($q) => $q->orderBy('sort_order')->with([
                'activities' => fn ($aq) => $aq->orderBy('sort_order')->with([
                    'responsible:id,uuid,first_name,last_name,email',
                    'kpis.updates.capturedBy:id,uuid,first_name,last_name',
                ]),
            ]),
            'variances' => fn ($q) => $q->with(['kpi:id,name', 'reviewedBy:id,uuid,first_name,last_name'])->latest('id'),
            'alerts' => fn ($q) => $q->with('kpi:id,name')->latest('id'),
        ]);

        $users = User::query()
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email'])
            ->orderBy('first_name')
            ->get();

        return view('quality-monitoring::show', [
            'workplan' => $workplan,
            'users' => $users,
        ]);
    }

    public function submit(Request $request, Workplan $workplan): RedirectResponse
    {
        abort_if(! $request->user()?->can('qm.workplans.manage'), 403);

        $nextVersion = ($workplan->versions()->max('version_no') ?? 0) + 1;

        DB::transaction(function () use ($workplan, $nextVersion, $request): void {
            WorkplanVersion::create([
                'tenant_id' => $workplan->tenant_id,
                'workplan_id' => $workplan->id,
                'version_no' => $nextVersion,
                'status' => 'submitted',
                'payload' => $workplan->only(['title', 'period_start', 'period_end', 'owner_id', 'org_scope', 'metadata']),
                'submitted_at' => now(),
                'created_by' => (string) $request->user()->uuid,
            ]);

            $workplan->update([
                'status' => 'submitted',
            ]);
        });

        return redirect()
            ->route('quality-monitoring.workplans.show', $workplan)
            ->with('status', 'success')
            ->with('message', 'Workplan submitted for Quality & Accreditation Committee review.');
    }

    public function approve(Request $request, Workplan $workplan): RedirectResponse
    {
        abort_if(! $request->user()?->can('qm.approvals.manage'), 403);

        $comments = $request->string('comments', 'Approved by Quality Committee')->toString();

        DB::transaction(function () use ($workplan, $comments, $request): void {
            Review::create([
                'tenant_id' => $workplan->tenant_id,
                'workplan_id' => $workplan->id,
                'reviewer_id' => (string) $request->user()->uuid,
                'status' => 'approved',
                'comments' => $comments,
                'submitted_at' => now(),
                'approved_at' => now(),
            ]);

            $workplan->update([
                'status' => 'active',
            ]);
        });

        return redirect()
            ->route('quality-monitoring.workplans.show', $workplan)
            ->with('status', 'success')
            ->with('message', 'Workplan approved and activated for clinical/operational monitoring.');
    }

    public function reject(Request $request, Workplan $workplan): RedirectResponse
    {
        abort_if(! $request->user()?->can('qm.approvals.manage'), 403);

        $comments = $request->string('comments', 'Revision required by Committee')->toString();

        DB::transaction(function () use ($workplan, $comments, $request): void {
            Review::create([
                'tenant_id' => $workplan->tenant_id,
                'workplan_id' => $workplan->id,
                'reviewer_id' => (string) $request->user()->uuid,
                'status' => 'rejected',
                'comments' => $comments,
                'submitted_at' => now(),
                'approved_at' => null,
            ]);

            $workplan->update([
                'status' => 'draft',
            ]);
        });

        return redirect()
            ->route('quality-monitoring.workplans.show', $workplan)
            ->with('status', 'warning')
            ->with('message', 'Workplan returned for revision with feedback comments.');
    }

    public function storeKpiUpdate(Request $request, Workplan $workplan, Kpi $kpi): RedirectResponse
    {
        abort_if(! $request->user()?->can('qm.kpis.manage'), 403);

        $validated = $request->validate([
            'value' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:1000'],
            'captured_at' => ['nullable', 'date'],
        ]);

        KpiUpdate::create([
            'tenant_id' => $workplan->tenant_id,
            'kpi_id' => $kpi->id,
            'value' => $validated['value'],
            'captured_at' => $validated['captured_at'] ?? now(),
            'note' => $validated['note'] ?? null,
            'captured_by_id' => (string) $request->user()->uuid,
        ]);

        // If target exists and performance breached, generate an alert
        if ($kpi->target_value !== null) {
            $isBreached = ($kpi->direction === 'increase' && (float) $validated['value'] < (float) $kpi->target_value)
                || ($kpi->direction === 'decrease' && (float) $validated['value'] > (float) $kpi->target_value);

            if ($isBreached) {
                Alert::create([
                    'tenant_id' => $workplan->tenant_id,
                    'workplan_id' => $workplan->id,
                    'kpi_id' => $kpi->id,
                    'type' => 'threshold_breach',
                    'status' => 'open',
                    'metadata' => [
                        'target' => (float) $kpi->target_value,
                        'recorded_value' => (float) $validated['value'],
                        'unit' => $kpi->unit,
                    ],
                    'sent_at' => null,
                ]);
            }
        }

        return redirect()
            ->route('quality-monitoring.workplans.show', $workplan)
            ->with('status', 'success')
            ->with('message', 'KPI measurement update recorded.');
    }

    public function storeVariance(Request $request, Workplan $workplan): RedirectResponse
    {
        abort_if(! $request->user()?->can('qm.variances.manage'), 403);

        $validated = $request->validate([
            'kpi_id' => ['nullable', 'integer'],
            'activity_id' => ['nullable', 'integer'],
            'category' => ['required', 'string', 'max:100'],
            'impact_level' => ['required', 'string', 'max:50'],
            'narrative' => ['required', 'string', 'max:2000'],
            'corrective_action' => ['required', 'string', 'max:2000'],
            'revised_date' => ['nullable', 'date'],
        ]);

        Variance::create([
            'tenant_id' => $workplan->tenant_id,
            'workplan_id' => $workplan->id,
            'kpi_id' => $validated['kpi_id'] ?? null,
            'activity_id' => $validated['activity_id'] ?? null,
            'category' => $validated['category'],
            'impact_level' => $validated['impact_level'],
            'narrative' => $validated['narrative'],
            'corrective_action' => $validated['corrective_action'],
            'revised_date' => $validated['revised_date'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('quality-monitoring.workplans.show', $workplan)
            ->with('status', 'success')
            ->with('message', 'Quality variance and corrective action plan logged.');
    }

    public function uploadEvidence(Request $request, Workplan $workplan): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || (! $user->can('qm.workplans.manage') && ! $user->can('qm.workplans.view')), 403);

        $tenant = app(\App\Services\Tenancy\TenantContext::class)->getTenant();
        $tenantId = $tenant ? (string) $tenant->id : (string) $user->tenant_id;
        if ($workplan->tenant_id !== $tenantId) {
            abort(404, 'Workplan not found in this organization.');
        }

        $validated = $request->validate([
            'evidence_file' => [
                'required',
                'file',
                'mimes:pdf,png,jpg,jpeg,docx,xlsx,zip',
                'max:10240',
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['evidence_file'];
        $path = $file->store("evidence/{$workplan->tenant_id}/{$workplan->uuid}", 'local');

        $metadata = is_array($workplan->metadata) ? $workplan->metadata : [];
        /** @var array<int, array<string, mixed>> $evidenceFiles */
        $evidenceFiles = isset($metadata['evidence_files']) && is_array($metadata['evidence_files'])
            ? $metadata['evidence_files']
            : [];

        $evidenceFiles[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'description' => $validated['description'] ?? null,
            'uploaded_by' => (string) $user->uuid,
            'uploaded_by_name' => mb_trim($user->first_name.' '.$user->last_name),
            'uploaded_at' => now()->toIso8601String(),
        ];

        $metadata['evidence_files'] = $evidenceFiles;
        $workplan->metadata = $metadata;
        $workplan->save();

        \Illuminate\Support\Facades\Log::info("Quality Monitoring Evidence uploaded for Workplan [{$workplan->uuid}] by user [{$user->uuid}]: file [{$file->getClientOriginalName()}].");

        return redirect()
            ->route('quality-monitoring.workplans.show', $workplan)
            ->with('status', 'success')
            ->with('message', 'Evidence document securely uploaded to vault.');
    }

    public function downloadEvidence(Request $request, Workplan $workplan, string $fileId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        abort_if(! $user || (! $user->can('qm.workplans.view') && ! $user->can('qm.workplans.manage')), 403);

        $tenant = app(\App\Services\Tenancy\TenantContext::class)->getTenant();
        $tenantId = $tenant ? (string) $tenant->id : (string) $user->tenant_id;
        if ($workplan->tenant_id !== $tenantId) {
            abort(404, 'Workplan not found in this organization.');
        }

        $metadata = is_array($workplan->metadata) ? $workplan->metadata : [];
        /** @var array<int, array{id: string, name: string, path: string, mime_type?: string, size_bytes?: int}> $evidenceFiles */
        $evidenceFiles = isset($metadata['evidence_files']) && is_array($metadata['evidence_files'])
            ? $metadata['evidence_files']
            : [];

        $targetFile = collect($evidenceFiles)->firstWhere('id', $fileId);

        abort_if(! is_array($targetFile), 404, 'Evidence file not found in vault.');

        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($targetFile['path'])) {
            abort(404, 'Physical evidence file missing on disk.');
        }

        \Illuminate\Support\Facades\Log::info("Quality Monitoring Evidence downloaded for Workplan [{$workplan->uuid}] by user [{$user->uuid}]: file [{$targetFile['name']}].");

        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $targetFile['path'],
            $targetFile['name'],
            [
                'Cache-Control' => 'private, no-cache, must-revalidate',
                'Content-Type' => $targetFile['mime_type'] ?? 'application/octet-stream',
            ]
        );
    }
}
