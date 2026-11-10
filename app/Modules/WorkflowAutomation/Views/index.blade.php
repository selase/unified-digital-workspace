@extends('layouts.metronic.app')

@section('title', 'Workflow & Business Process Automation')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Workflow & Business Process Automation</h1>
            <p class="text-xs text-muted-foreground">When something happens in the workspace, do something automatically. A safety limit stops rules from triggering each other endlessly.</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('workflow-automation.create') }}" class="kt-btn kt-btn-sm kt-btn-primary">Create rule</a></div>
    </div>

    @if (session('status'))
        <div class="rounded-lg p-3 text-sm {{ session('status') === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ session('message') }}</div>
    @endif

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Automation rules ({{ $rules->total() }})</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Rule</th><th>Trigger event</th><th>Conditions</th><th>Actions</th><th class="text-end">Runs</th><th>Status</th><th class="text-end"></th></tr></thead>
                <tbody>
                    @forelse ($rules as $r)
                        <tr>
                            <td><a href="{{ route('workflow-automation.show', $r) }}" class="text-primary font-medium">{{ $r->name }}</a>@if ($r->description)<div class="text-xs text-muted-foreground">{{ Str::limit($r->description, 50) }}</div>@endif</td>
                            <td class="font-mono text-xs">{{ $r->trigger_event }}</td>
                            <td>{{ empty($r->conditions) ? 'Always' : count($r->conditions).' condition(s)' }}</td>
                            <td>{{ count($r->actions ?? []) }}</td>
                            <td class="text-end">{{ $r->execution_count }}</td>
                            <td><form action="{{ route('workflow-automation.toggle', $r) }}" method="POST">@csrf<button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">{{ $r->is_active ? 'Active' : 'Disabled' }}</button></form></td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('workflow-automation.show', $r) }}" class="kt-btn kt-btn-xs kt-btn-outline">View</a>
                                    <form action="{{ route('workflow-automation.destroy', $r) }}" method="POST" onsubmit="return confirm('Delete this rule?');">@csrf @method('DELETE')<button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">Delete</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted-foreground py-6">No rules yet. Choose "Create rule" to automate something.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $rules->links() }}

    @if ($recentLogs->isNotEmpty())
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Recent runs</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Time</th><th>Rule</th><th>Event</th><th>Status</th><th>Depth</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @foreach ($recentLogs as $log)
                        <tr>
                            <td>{{ $log->executed_at ? $log->executed_at->format('d/m/Y H:i:s') : '-' }}</td>
                            <td>{{ $log->rule ? $log->rule->name : '#'.$log->workflow_rule_id }}</td>
                            <td class="font-mono text-xs">{{ $log->trigger_event }}</td>
                            <td class="{{ $log->status === 'success' ? '' : 'text-destructive' }}">{{ $log->status }}</td>
                            <td>{{ $log->execution_depth }}</td>
                            <td class="text-end">{{ count($log->actions_executed ?? []) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
