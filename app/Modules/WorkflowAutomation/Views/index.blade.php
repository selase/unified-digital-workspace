@extends('layouts.metronic.app')

@section('title', 'Workflow & Business Process Automation')

@section('content')
<div class="d-flex flex-column gap-7">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Workflow & Business Process Automation"
        description="Event-Condition-Action automation engine with safety recursion circuit breakers.">
        <x-slot:actions>
            <a href="{{ route('workflow-automation.create') }}" class="btn btn-sm btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i> Create Automation Rule
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if (session('status'))
        <div class="alert alert-{{ session('status') === 'success' ? 'success' : 'danger' }} d-flex align-items-center p-5 mb-5">
            <i class="ki-duotone ki-information-5 fs-2hx text-{{ session('status') === 'success' ? 'success' : 'danger' }} me-4"></i>
            <div class="d-flex flex-column">
                <span>{{ session('message') }}</span>
            </div>
        </div>
    @endif

    {{-- Rules Registry Card --}}
    <x-metronic.card title="Active Automation Rules ({{ $rules->total() }})">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                        <th>Rule Name</th>
                        <th>Trigger Event</th>
                        <th>Conditions</th>
                        <th>Actions</th>
                        <th>Executions</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="fw-semibold text-gray-600">
                    @forelse ($rules as $r)
                        <tr>
                            <td>
                                <a href="{{ route('workflow-automation.show', $r) }}" class="text-gray-900 text-hover-primary fw-bold">
                                    {{ $r->name }}
                                </a>
                                @if ($r->description)
                                    <div class="text-muted fs-7">{{ Str::limit($r->description, 50) }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-light-primary fw-bold font-monospace">{{ $r->trigger_event }}</span>
                            </td>
                            <td>
                                @if (empty($r->conditions))
                                    <span class="badge badge-light">Always Match</span>
                                @else
                                    <span class="badge badge-light-info">{{ count($r->conditions) }} condition(s)</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-light-secondary">{{ count($r->actions ?? []) }} action(s)</span>
                            </td>
                            <td>
                                <span class="badge badge-light-success fw-bold">{{ $r->execution_count }}</span>
                            </td>
                            <td>
                                <form action="{{ route('workflow-automation.toggle', $r) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="badge badge-light-{{ $r->is_active ? 'success' : 'danger' }} border-0 cursor-pointer">
                                        {{ $r->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('workflow-automation.show', $r) }}" class="btn btn-icon btn-light-primary btn-sm me-1" title="View / Test">
                                    <i class="ki-duotone ki-eye fs-4"></i>
                                </a>
                                <form action="{{ route('workflow-automation.destroy', $r) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this automation rule?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-light-danger btn-sm" title="Delete">
                                        <i class="ki-duotone ki-trash fs-4"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-10 text-muted">
                                <i class="ki-duotone ki-abstract-26 fs-3x text-muted mb-3 d-block"></i>
                                No automation rules configured yet. Click "Create Automation Rule" to automate cross-module events.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rules->links() }}
        </div>
    </x-metronic.card>

    {{-- Recent Execution Log Card --}}
    @if ($recentLogs->isNotEmpty())
        <x-metronic.card title="Recent Execution Telemetry">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-3">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-8 text-uppercase">
                            <th>Time</th>
                            <th>Rule</th>
                            <th>Event</th>
                            <th>Status</th>
                            <th>Depth</th>
                            <th>Actions Count</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        @foreach ($recentLogs as $log)
                            <tr>
                                <td>{{ $log->executed_at ? $log->executed_at->format('d/m/Y H:i:s') : '-' }}</td>
                                <td>{{ $log->rule ? $log->rule->name : '#' . $log->workflow_rule_id }}</td>
                                <td><span class="font-monospace fs-8">{{ $log->trigger_event }}</span></td>
                                <td>
                                    <span class="badge badge-light-{{ $log->status === 'success' ? 'success' : ($log->status === 'loop_aborted' ? 'warning' : 'danger') }}">
                                        {{ $log->status }}
                                    </span>
                                </td>
                                <td>Depth {{ $log->execution_depth }}</td>
                                <td>{{ count($log->actions_executed ?? []) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    @endif
</div>
@endsection

