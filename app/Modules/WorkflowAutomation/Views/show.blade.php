@extends('layouts.metronic.app')

@section('title', 'Workflow Rule: ' . $rule->name)

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $rule->name }}</h1>
            <p class="text-xs text-muted-foreground">Trigger: {{ $rule->trigger_event }} · {{ $rule->is_active ? 'Active' : 'Disabled' }}</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('workflow-automation.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">All rules</a>
            <form action="{{ route('workflow-automation.toggle', $rule) }}" method="POST">@csrf<button type="submit" class="kt-btn kt-btn-sm kt-btn-outline">{{ $rule->is_active ? 'Deactivate' : 'Activate' }}</button></form></div>
    </div>

    @if (session('status'))
        <div class="rounded-lg p-3 text-sm {{ session('status') === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ session('message') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Rule configuration</h3></div>
            <div class="kt-card-content flex flex-col gap-4 text-sm">
                <div><div class="text-xs text-muted-foreground">Trigger event</div><div class="font-mono">{{ $rule->trigger_event }}</div></div>
                <div>
                    <div class="text-xs text-muted-foreground">Conditions</div>
                    @if (empty($rule->conditions))
                        <div>Always run (no conditions)</div>
                    @else
                        @foreach ($rule->conditions as $cond)<div class="mt-1 rounded-lg bg-muted/30 p-2 font-mono text-xs">{{ $cond['field'] ?? '' }} {{ $cond['operator'] ?? '=' }} "{{ $cond['value'] ?? '' }}"</div>@endforeach
                    @endif
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Actions</div>
                    @foreach ($rule->actions as $act)
                        <div class="mt-1 rounded-lg bg-muted/30 p-3 text-xs">
                            <div class="font-medium">{{ $act['type'] ?? 'action' }}</div>
                            @if (isset($act['title_template']))<div class="font-semibold">{{ $act['title_template'] }}</div>@endif
                            <div class="text-muted-foreground">{{ $act['body_template'] ?? ($act['message'] ?? '') }}</div>
                        </div>
                    @endforeach
                </div>
                <div><div class="text-xs text-muted-foreground">Successful runs</div><div class="text-lg font-semibold">{{ $rule->execution_count }}</div></div>
            </div>
        </div>

        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Test run</h3></div>
            <form action="{{ route('workflow-automation.test-run', $rule) }}" method="POST" class="kt-card-content flex flex-col gap-3">
                @csrf
                <p class="text-xs text-muted-foreground">Send a sample event through this rule to check its conditions and actions.</p>
                <textarea name="payload" class="kt-input font-mono text-xs" rows="6">{
  "title": "Simulated Generator Overheat",
  "impact": "critical",
  "reported_via": "portal"
}</textarea>
                <div><button type="submit" class="kt-btn kt-btn-primary">Run test</button></div>
            </form>
        </div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Run history ({{ $logs->total() }})</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>When</th><th>Event</th><th>Status</th><th>Depth</th><th>Actions</th><th>Payload</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->executed_at ? $log->executed_at->format('d/m/Y H:i:s') : '-' }}</td>
                            <td class="font-mono text-xs">{{ $log->trigger_event }}</td>
                            <td class="{{ $log->status === 'success' ? '' : 'text-destructive' }}">{{ $log->status }}@if ($log->error_message)<div class="text-xs">{{ $log->error_message }}</div>@endif</td>
                            <td>{{ $log->execution_depth }}</td>
                            <td>{{ count($log->actions_executed ?? []) }}</td>
                            <td class="font-mono text-xs text-muted-foreground">{{ Str::limit(json_encode($log->context_payload), 50) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted-foreground py-6">Nothing has run yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $logs->links() }}
</div>
@endsection
