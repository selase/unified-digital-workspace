<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\Incidents\IncidentAssigned;
use App\Mail\Incidents\IncidentEscalated;
use App\Models\User;
use App\Modules\IncidentManagement\Http\Requests\IncidentAssignRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentCloseRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentEscalateRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentResolveRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentStoreRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentUpdateRequest;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentAssignment;
use App\Modules\IncidentManagement\Models\IncidentCategory;
use App\Modules\IncidentManagement\Models\IncidentEscalation;
use App\Modules\IncidentManagement\Models\IncidentPriority;
use App\Modules\IncidentManagement\Models\IncidentStatus;
use App\Modules\IncidentManagement\Services\IncidentSlaService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

final class IncidentListController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Incident::class);

        $search = mb_trim((string) $request->string('search'));
        $statusId = $request->integer('status_id');
        $priorityId = $request->integer('priority_id');
        $categoryId = $request->integer('category_id');

        $incidents = Incident::query()
            ->visibleTo($request->user())
            ->with(['status', 'priority', 'category'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('reference_code', 'like', "%{$search}%");
                });
            })
            ->when($statusId > 0, fn ($query) => $query->where('status_id', $statusId))
            ->when($priorityId > 0, fn ($query) => $query->where('priority_id', $priorityId))
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('incident-management::incidents', [
            'incidents' => $incidents,
            'search' => $search,
            'statusId' => $statusId,
            'priorityId' => $priorityId,
            'categoryId' => $categoryId,
            'statuses' => IncidentStatus::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'priorities' => IncidentPriority::query()->orderBy('level')->orderBy('name')->get(['id', 'name']),
            'categories' => IncidentCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('incidents.create'), 403);

        return view('incident-management::incidents-create', $this->formOptions());
    }

    public function store(IncidentStoreRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['reported_by_id'] = (string) $request->user()?->uuid;
        $payload['reported_via'] = 'internal';
        $payload['status_id'] = $payload['status_id'] ?? $this->defaultStatusId();

        $incident = Incident::create($payload);
        app(IncidentSlaService::class)->createOrUpdate($incident);

        if (! empty($payload['assigned_to_id'])) {
            IncidentAssignment::create([
                'incident_id' => $incident->id,
                'assigned_to_id' => $payload['assigned_to_id'],
                'assigned_by_id' => (string) $request->user()?->uuid,
                'assigned_at' => now(),
                'is_active' => true,
                'note' => 'Auto-assigned at incident creation',
            ]);

            $this->notifyAssignedUser($incident, (string) $payload['assigned_to_id']);
        }

        $this->logIncidentActivity($incident, 'created', (string) $request->user()?->uuid, [
            'title' => $incident->title,
            'priority_id' => $incident->priority_id,
            'status_id' => $incident->status_id,
        ]);

        return redirect()
            ->route('incident-management.incidents.show', $incident)
            ->with('status', 'success')
            ->with('message', 'Incident created successfully.');
    }

    public function show(Request $request, Incident $incident): View
    {
        $this->authorize('view', $incident);

        $incident->load([
            'category',
            'priority',
            'status',
            'reporter',
            'reporterUser:id,uuid,first_name,last_name,email',
            'tasks.assignedTo:id,uuid,first_name,last_name,email',
            'comments.user:id,uuid,first_name,last_name,email',
            'attachments',
            'escalations',
            'sla',
        ]);

        $activities = Activity::query()
            ->where('subject_type', Incident::class)
            ->where('subject_id', $incident->id)
            ->latest('id')
            ->limit(30)
            ->get();

        return view('incident-management::incidents-show', [
            'incident' => $incident,
            'activities' => $activities,
            ...$this->formOptions(),
        ]);
    }

    public function edit(Request $request, Incident $incident): View|Factory
    {
        abort_if(! $request->user()?->can('incidents.update'), 403);

        return view('incident-management::incidents-edit', [
            'incident' => $incident,
            ...$this->formOptions(),
        ]);
    }

    public function update(IncidentUpdateRequest $request, Incident $incident): RedirectResponse
    {
        $payload = $request->validated();

        $incident->fill($payload);
        $incident->save();

        app(IncidentSlaService::class)->createOrUpdate($incident);

        $this->logIncidentActivity($incident, 'updated', (string) $request->user()?->uuid, [
            'changes' => array_keys($payload),
        ]);

        return redirect()
            ->route('incident-management.incidents.show', $incident)
            ->with('status', 'success')
            ->with('message', 'Incident updated successfully.');
    }

    public function destroy(Request $request, Incident $incident): RedirectResponse
    {
        abort_if(! $request->user()?->can('incidents.delete'), 403);

        $incident->delete();

        return redirect()
            ->route('incident-management.incidents.index')
            ->with('status', 'success')
            ->with('message', 'Incident archived successfully.');
    }

    public function assign(IncidentAssignRequest $request, Incident $incident): RedirectResponse
    {
        $payload = $request->validated();

        IncidentAssignment::create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $payload['assigned_to_id'],
            'assigned_by_id' => (string) $request->user()?->uuid,
            'assigned_at' => now(),
            'is_active' => true,
            'note' => $payload['note'] ?? null,
        ]);

        $incident->assigned_to_id = $payload['assigned_to_id'];
        $incident->save();

        $this->notifyAssignedUser($incident, (string) $payload['assigned_to_id']);

        $this->logIncidentActivity($incident, 'assigned', (string) $request->user()?->uuid, [
            'assigned_to_id' => $payload['assigned_to_id'],
            'note' => $payload['note'] ?? null,
        ]);

        return redirect()
            ->route('incident-management.incidents.show', $incident)
            ->with('status', 'success')
            ->with('message', 'Incident assignment updated.');
    }

    public function escalate(IncidentEscalateRequest $request, Incident $incident): RedirectResponse
    {
        $payload = $request->validated();
        $fromPriority = $incident->priority_id;

        IncidentEscalation::create([
            'incident_id' => $incident->id,
            'from_priority_id' => $fromPriority,
            'to_priority_id' => $payload['to_priority_id'],
            'escalated_by_id' => (string) $request->user()?->uuid,
            'reason' => $payload['reason'] ?? null,
            'escalated_at' => now(),
        ]);

        $incident->priority_id = $payload['to_priority_id'];
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
            'from_priority_id' => $fromPriority,
            'to_priority_id' => $payload['to_priority_id'],
            'reason' => $payload['reason'] ?? null,
        ]);

        return redirect()
            ->route('incident-management.incidents.show', $incident)
            ->with('status', 'success')
            ->with('message', 'Incident escalated successfully.');
    }

    public function resolve(IncidentResolveRequest $request, Incident $incident): RedirectResponse
    {
        $payload = $request->validated();

        if (array_key_exists('status_id', $payload)) {
            $incident->status_id = $payload['status_id'];
        }

        $incident->resolved_at = $payload['resolved_at'] ?? now();
        $incident->save();

        app(IncidentSlaService::class)->markResolved($incident);

        $this->logIncidentActivity($incident, 'resolved', (string) $request->user()?->uuid, [
            'status_id' => $incident->status_id,
            'resolved_at' => $incident->resolved_at,
        ]);

        return redirect()
            ->route('incident-management.incidents.show', $incident)
            ->with('status', 'success')
            ->with('message', 'Incident marked as resolved.');
    }

    public function close(IncidentCloseRequest $request, Incident $incident): RedirectResponse
    {
        $payload = $request->validated();

        if (array_key_exists('status_id', $payload)) {
            $incident->status_id = $payload['status_id'];
        }

        $incident->closed_at = $payload['closed_at'] ?? now();
        $incident->save();

        $this->logIncidentActivity($incident, 'closed', (string) $request->user()?->uuid, [
            'status_id' => $incident->status_id,
            'closed_at' => $incident->closed_at,
        ]);

        return redirect()
            ->route('incident-management.incidents.show', $incident)
            ->with('status', 'success')
            ->with('message', 'Incident closed.');
    }

    /**
     * @return array{
     *     statuses: \Illuminate\Database\Eloquent\Collection<int, IncidentStatus>,
     *     priorities: \Illuminate\Database\Eloquent\Collection<int, IncidentPriority>,
     *     categories: \Illuminate\Database\Eloquent\Collection<int, IncidentCategory>,
     *     assignees: \Illuminate\Support\Collection<int, User>
     * }
     */
    private function formOptions(): array
    {
        $tenant = app(TenantContext::class)->getTenant();

        return [
            'statuses' => IncidentStatus::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'priorities' => IncidentPriority::query()->orderBy('level')->orderBy('name')->get(['id', 'name']),
            'categories' => IncidentCategory::query()->orderBy('name')->get(['id', 'name']),
            'assignees' => $tenant
                ? $tenant->users()->orderBy('users.first_name')->get(['users.uuid', 'users.first_name', 'users.last_name', 'users.email'])
                : collect(),
        ];
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

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logIncidentActivity(Incident $incident, string $event, ?string $userId, array $properties = []): void
    {
        activity()
            ->performedOn($incident)
            ->causedBy($userId ? User::query()->where('uuid', $userId)->first() : null)
            ->withProperties($properties)
            ->event($event)
            ->log($event);
    }
}
