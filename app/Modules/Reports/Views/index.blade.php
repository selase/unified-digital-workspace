@extends('layouts.metronic.app')

@section('title', 'Custom Reports & Analytics')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Custom Reports & Analytics</h1>
            <p class="text-xs text-muted-foreground">Cross-module data reporting, dynamic filtering, and streaming exports.</p>
        </div>
        <a href="{{ route('reports.create') }}" class="kt-btn kt-btn-sm kt-btn-primary">Create report</a>
    </div>

    @if (session('status'))
        <div class="rounded-lg p-3 text-sm {{ session('status') === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ session('message') }}</div>
    @endif

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Saved reports ({{ $reports->total() }})</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr><th>Report</th><th>Data source</th><th>Columns</th><th>Created by</th><th class="text-end">Runs</th><th>Created</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($reports as $rep)
                        <tr>
                            <td>
                                <a href="{{ route('reports.show', $rep) }}" class="text-primary font-medium">{{ $rep->name }}</a>
                                @if ($rep->description)<div class="text-xs text-muted-foreground">{{ Str::limit($rep->description, 60) }}</div>@endif
                            </td>
                            <td>{{ ucfirst(str_replace('_', ' ', $rep->data_source)) }}</td>
                            <td>{{ count($rep->selected_columns ?? []) }}</td>
                            <td>{{ $rep->creator ? $rep->creator->name : 'System / Admin' }}</td>
                            <td class="text-end">{{ $rep->execution_logs_count }}</td>
                            <td>{{ $rep->created_at ? $rep->created_at->format('d/m/Y') : '-' }}</td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('reports.show', $rep) }}" class="kt-btn kt-btn-xs kt-btn-outline">View</a>
                                    <a href="{{ route('reports.export.csv', $rep) }}" class="kt-btn kt-btn-xs kt-btn-outline">CSV</a>
                                    <form action="{{ route('reports.destroy', $rep) }}" method="POST" onsubmit="return confirm('Delete this report?');">@csrf @method('DELETE')<button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">Delete</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted-foreground py-6">No custom reports yet. Choose "Create report" to build one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $reports->links() }}
</div>
@endsection
