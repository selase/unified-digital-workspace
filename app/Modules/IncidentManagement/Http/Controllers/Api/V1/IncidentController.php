<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\Incidents\IncidentAssigned;
use App\Mail\Incidents\IncidentEscalated;
use App\Models\User;
use App\Modules\IncidentManagement\Http\Requests\IncidentAssignRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentCloseRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentDelegateRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentEscalateRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentResolveRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentStoreRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentUpdateRequest;
use App\Modules\IncidentManagement\Http\Resources\IncidentResource;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentAssignment;
use App\Modules\IncidentManagement\Models\IncidentEscalation;
use App\Modules\IncidentManagement\Models\IncidentStatus;
use App\Modules\IncidentManagement\Services\IncidentSlaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Incident::class);

        $perPage = $request->integer('per_page', 15);

        $query = Incident::query()
            ->visibleTo($request->user())
            ->with(['category', 'priority', 'status']);

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->integer('status_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('priority_id')) {
            $query->where('priority_id', $request->integer('priority_id'));
        }

        if ($request->filled('assigned_to_id')) {
            $query->where('assigned_to_id', $request->input('assigned_to_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->date('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->date('to_date'));
        }

        $incidents = $query->paginate($perPage);

        return IncidentResource::collection($incidents)
            ->response();
    }

    public function show(Incident $incident): IncidentResource
    {
        $this->authorize('view', $incident);

        $incident->load([
            'category',
            'priority',
            'status',
            'reporter',
            'tasks',
            'comments',
            'attachments',
            'escalations',
            'reminders',
            'sla',
            'progressReports.comments',
            'progressReports.attachments',
        ]);

        return new IncidentResource($incident);
    }

    public function store(IncidentStoreRequest $request): JsonResponse
    {
        $this->authorize('incidents.create');

        $data = $request->validated();
        $data['reported_by_id'] = (string) $request->user()?->uuid;
        $data['reported_via'] = 'internal';

        if (empty($data['status_id'])) {
            $data['status_id'] = $this->defaultStatusId();
        }

        $incident = Incident::create($data);

        app(IncidentSlaService::class)->createOrUpdate($incident);

        $this->logIncidentActivity($incident, 'created', (string) $request->user()?->uuid, [
            'title' => $incident->title,
            'priority_id' => $incident->priority_id,
            'status_id' => $incident->status_id,
        ]);

        return (new IncidentResource($incident->load(['category', 'priority', 'status'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(IncidentUpdateRequest $request, Incident $incident): IncidentResource
    {
        $this->authorize('incidents.update');

        $data = $request->validated();

        $incident->fill($data);
        $incident->save();

        $this->logIncidentActivity($incident, 'updated', (string) $request->user()?->uuid, [
            'changes' => array_keys($data),
        ]);

        return new IncidentResource($incident->load(['category', 'priority', 'status']));
    }

    public function destroy(Incident $incident): JsonResponse
    {
        $this->authorize('incidents.delete');

        $incident->delete();

        return response()->json([], 204);
    }

    public function assign(IncidentAssignRequest $request, Incident $incident): IncidentResource
    {
        $this->authorize('incidents.assign');

        $data = $request->validated();

        IncidentAssignment::create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $data['assigned_to_id'],
            'assigned_by_id' => (string) $request->user()?->uuid,
            'assigned_at' => now(),
            'is_active' => true,
            'note' => $data['note'] ?? null,
        ]);

        $incident->assigned_to_id = $data['assigned_to_id'];
        $incident->save();

        $this->notifyAssignedUser($incident, $data['assigned_to_id']);

        $this->logIncidentActivity($incident, 'assigned', (string) $request->user()?->uuid, [
            'assigned_to_id' => $data['assigned_to_id'],
            'note' => $data['note'] ?? null,
        ]);

        return new IncidentResource($incident->load(['category', 'priority', 'status']));
    }

    public function delegate(IncidentDelegateRequest $request, Incident $incident): IncidentResource
    {
        $this->authorize('incidents.delegate');

        $data = $request->validated();

        IncidentAssignment::create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $data['assigned_to_id'],
            'assigned_by_id' => (string) $request->user()?->uuid,
            'delegated_from_id' => $incident->assigned_to_id,
            'assigned_at' => now(),
            'is_active' => true,
            'note' => $data['note'] ?? null,
        ]);

        $incident->assigned_to_id = $data['assigned_to_id'];
        $incident->save();

        $this->notifyAssignedUser($incident, $data['assigned_to_id']);

        $this->logIncidentActivity($incident, 'delegated', (string) $request->user()?->uuid, [
            'assigned_to_id' => $data['assigned_to_id'],
            'delegated_from_id' => $incident->assigned_to_id,
            'note' => $data['note'] ?? null,
        ]);

        return new IncidentResource($incident->load(['category', 'priority', 'status']));
    }

    public function escalate(IncidentEscalateRequest $request, Incident $incident): IncidentResource
    {
        $this->authorize('incidents.escalate');

        $data = $request->validated();

        IncidentEscalation::create([
            'incident_id' => $incident->id,
            'from_priority_id' => $incident->priority_id,
            'to_priority_id' => $data['to_priority_id'],
            'escalated_by_id' => (string) $request->user()?->uuid,
            'reason' => $data['reason'] ?? null,
            'escalated_at' => now(),
        ]);

        $incident->priority_id = $data['to_priority_id'];
        $incident->save();

        app(IncidentSlaService::class)->createOrUpdate($incident);

        if ($incident->assigned_to_id) {
            $assignee = User::query()
                ->where('uuid', $incident->assigned_to_id)
                ->first();

            if ($assignee && $assignee->email) {
                Mail::to($assignee->email)->queue(new IncidentEscalated($incident));
            }
        }

        $this->logIncidentActivity($incident, 'escalated', (string) $request->user()?->uuid, [
            'from_priority_id' => $incident->getOriginal('priority_id'),
            'to_priority_id' => $data['to_priority_id'],
            'reason' => $data['reason'] ?? null,
        ]);

        return new IncidentResource($incident->load(['category', 'priority', 'status']));
    }

    public function resolve(IncidentResolveRequest $request, Incident $incident): IncidentResource
    {
        $this->authorize('incidents.update');

        $data = $request->validated();

        if (array_key_exists('status_id', $data)) {
            $incident->status_id = $data['status_id'];
        }

        $incident->resolved_at = $data['resolved_at'] ?? now();
        $incident->save();

        app(IncidentSlaService::class)->markResolved($incident);

        $this->logIncidentActivity($incident, 'resolved', (string) $request->user()?->uuid, [
            'status_id' => $incident->status_id,
            'resolved_at' => $incident->resolved_at,
        ]);

        return new IncidentResource($incident->load(['category', 'priority', 'status']));
    }

    public function close(IncidentCloseRequest $request, Incident $incident): IncidentResource
    {
        $this->authorize('incidents.update');

        $data = $request->validated();

        if (array_key_exists('status_id', $data)) {
            $incident->status_id = $data['status_id'];
        }

        $incident->closed_at = $data['closed_at'] ?? now();
        $incident->save();

        $this->logIncidentActivity($incident, 'closed', (string) $request->user()?->uuid, [
            'status_id' => $incident->status_id,
            'closed_at' => $incident->closed_at,
        ]);

        return new IncidentResource($incident->load(['category', 'priority', 'status']));
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Incident::class);

        $baseQuery = Incident::query()
            ->visibleTo($request->user());

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status_id, count(*) as total')
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        $priorityCounts = (clone $baseQuery)
            ->selectRaw('priority_id, count(*) as total')
            ->groupBy('priority_id')
            ->pluck('total', 'priority_id');

        $overdueCount = (clone $baseQuery)
            ->whereNotNull('due_at')
            ->whereNull('resolved_at')
            ->whereNull('closed_at')
            ->where('due_at', '<', now())
            ->count();

        $slaBreaches = (clone $baseQuery)
            ->whereHas('sla', function ($query): void {
                $query->where('is_breached', true);
            })
            ->count();

        return response()->json([
            'status_counts' => $statusCounts,
            'priority_counts' => $priorityCounts,
            'overdue' => $overdueCount,
            'sla_breaches' => $slaBreaches,
        ]);
    }

    public function exportAudit(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Incident::class);

        $filename = 'incidents-audit.csv';

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'id',
                'incident_id',
                'event',
                'description',
                'causer_id',
                'properties',
                'created_at',
            ]);

            Activity::query()
                ->where('subject_type', Incident::class)
                ->orderByDesc('created_at')
                ->chunk(200, function ($activities) use ($output): void {
                    foreach ($activities as $activity) {
                        fputcsv($output, [
                            $activity->id,
                            $activity->subject_id,
                            $activity->event,
                            $activity->description,
                            $activity->causer_id,
                            json_encode($activity->properties ?? []),
                            optional($activity->created_at)->toIso8601String(),
                        ]);
                    }
                });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function logIncidentActivity(Incident $incident, string $event, ?string $userId, array $properties = [], ?string $description = null): void
    {
        activity()
            ->performedOn($incident)
            ->causedBy($userId ? User::query()->where('uuid', $userId)->first() : null)
            ->withProperties($properties)
            ->event($event)
            ->log($description ?? $event);
    }

    private function defaultStatusId(): ?int
    {
        return IncidentStatus::query()
            ->where('is_default', true)
            ->value('id');
    }

    private function notifyAssignedUser(Incident $incident, string $assigneeId): void
    {
        $assignee = User::query()
            ->where('uuid', $assigneeId)
            ->first();

        if ($assignee && $assignee->email) {
            Mail::to($assignee->email)->queue(new IncidentAssigned($incident));
        }
    }
}
