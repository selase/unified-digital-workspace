@extends('layouts.metronic.app')

@section('title', 'Workflow Rule: ' . $rule->name)

@section('content')
<div class="d-flex flex-column gap-7">
    {{-- Page Header --}}
    <x-metronic.page-header
        :title="$rule->name"
        :description="'Trigger: ' . $rule->trigger_event . ' | Status: ' . ($rule->is_active ? 'Active' : 'Disabled')">
        <x-slot:actions>
            <a href="{{ route('workflow-automation.index') }}" class="btn btn-sm btn-light me-2">
                <i class="ki-duotone ki-arrow-left fs-2"></i> Back to Rules
            </a>
            <form action="{{ route('workflow-automation.toggle', $rule) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-{{ $rule->is_active ? 'light-danger' : 'light-success' }} me-2">
                    {{ $rule->is_active ? 'Deactivate Rule' : 'Activate Rule' }}
                </button>
            </form>
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

    {{-- Details & Test Card --}}
    <div class="row g-7">
        <div class="col-lg-6">
            <x-metronic.card title="Rule Configuration">
                <div class="mb-4">
                    <span class="text-muted fs-7 d-block">Trigger Event</span>
                    <span class="badge badge-light-primary fw-bold font-monospace mt-1">{{ $rule->trigger_event }}</span>
                </div>

                <div class="mb-4">
                    <span class="text-muted fs-7 d-block">Conditions</span>
                    @if (empty($rule->conditions))
                        <span class="badge badge-light mt-1">Always Execute (No conditions)</span>
                    @else
                        <div class="mt-2">
                            @foreach ($rule->conditions as $cond)
                                <div class="p-2 bg-light rounded border border-gray-200 fs-7 mb-2 font-monospace">
                                    {{ $cond['field'] ?? '' }} {{ $cond['operator'] ?? '=' }} "{{ $cond['value'] ?? '' }}"
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mb-4">
                    <span class="text-muted fs-7 d-block">Actions</span>
                    <div class="mt-2">
                        @foreach ($rule->actions as $act)
                            <div class="p-3 bg-light rounded border border-gray-200 fs-7 mb-2">
                                <span class="badge badge-light-success mb-1">{{ $act['type'] ?? 'action' }}</span>
                                @if (isset($act['title_template']))
                                    <div class="fw-bold">{{ $act['title_template'] }}</div>
                                @endif
                                <div class="text-gray-600 fs-8">{{ $act['body_template'] ?? ($act['message'] ?? '') }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <span class="text-muted fs-7 d-block">Total Successful Executions</span>
                    <span class="fs-2 fw-bolder text-primary">{{ $rule->execution_count }}</span>
                </div>
            </x-metronic.card>
        </div>

        <div class="col-lg-6">
            <x-metronic.card title="Test Run Simulation">
                <p class="text-muted fs-7 mb-4">Trigger a test event in this organization to verify condition evaluation and action dispatching.</p>

                <form action="{{ route('workflow-automation.test-run', $rule) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-bold">Test Context Payload (JSON)</label>
                        <textarea name="payload" class="form-control font-monospace fs-8" rows="6">{
  "title": "Simulated Generator Overheat",
  "impact": "critical",
  "reported_via": "portal"
}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ki-duotone ki-triangle fs-2"></i> Run Test Simulation
                    </button>
                </form>
            </x-metronic.card>
        </div>
    </div>

    {{-- Execution Logs Card --}}
    <x-metronic.card title="Execution Telemetry & Audit Logs ({{ $logs->total() }})">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                        <th>Executed At</th>
                        <th>Trigger Event</th>
                        <th>Status</th>
                        <th>Depth</th>
                        <th>Actions Dispatched</th>
                        <th>Payload Summary</th>
                    </tr>
                </thead>
                <tbody class="fw-semibold text-gray-700">
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->executed_at ? $log->executed_at->format('d/m/Y H:i:s') : '-' }}</td>
                            <td><span class="badge badge-light-primary font-monospace">{{ $log->trigger_event }}</span></td>
                            <td>
                                <span class="badge badge-light-{{ $log->status === 'success' ? 'success' : ($log->status === 'loop_aborted' ? 'warning' : 'danger') }}">
                                    {{ $log->status }}
                                </span>
                                @if ($log->error_message)
                                    <div class="text-danger fs-8 mt-1">{{ $log->error_message }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-light-secondary">Depth {{ $log->execution_depth }}</span>
                            </td>
                            <td>
                                <span class="badge badge-light-info">{{ count($log->actions_executed ?? []) }} action(s)</span>
                            </td>
                            <td>
                                <div class="text-muted fs-8 font-monospace">{{ Str::limit(json_encode($log->context_payload), 50) }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-10 text-muted">
                                <i class="ki-duotone ki-information fs-3x text-muted mb-3 d-block"></i>
                                No executions recorded yet for this workflow rule.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection

