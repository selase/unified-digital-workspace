@extends('layouts.metronic.app')

@section('title', 'Report: ' . $report->name)

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $report->name }}</h1>
            <p class="text-xs text-muted-foreground">{{ $report->description ?? 'Custom report for '.ucfirst(str_replace('_', ' ', $report->data_source)) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">All reports</a>
            <a href="{{ route('reports.export.csv', $report) }}" class="kt-btn kt-btn-sm kt-btn-outline">Export CSV</a>
            <a href="{{ route('reports.export.pdf', $report) }}" class="kt-btn kt-btn-sm kt-btn-primary">Export PDF</a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg p-3 text-sm {{ session('status') === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ session('message') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Data source</div><div class="mt-1 text-lg font-semibold">{{ ucfirst(str_replace('_', ' ', $report->data_source)) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Total matches</div><div class="mt-1 text-lg font-semibold">{{ number_format($preview['total']) }} records</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Columns</div><div class="mt-1 text-lg font-semibold">{{ count($preview['headers']) }} selected</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Created</div><div class="mt-1 text-lg font-semibold">{{ $report->created_at ? $report->created_at->format('d/m/Y') : '-' }}</div></div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Preview (first {{ count($preview['rows']) }} of {{ $preview['total'] }} records)</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr>@foreach ($preview['headers'] as $col => $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($preview['rows'] as $row)
                        <tr>
                            @foreach ($preview['headers'] as $col => $label)
                                <td>
                                    @php $val = $row[$col] ?? null; @endphp
                                    @if ($val === null || $val === '')
                                        <span class="text-muted-foreground">-</span>
                                    @elseif (is_bool($val))
                                        {{ $val ? 'Yes' : 'No' }}
                                    @elseif (str_contains($col, 'price') || str_contains($col, 'amount'))
                                        GHS {{ number_format(((float) $val) / 100, 2) }}
                                    @elseif (str_contains($col, 'status') || str_contains($col, 'severity'))
                                        {{ ucfirst((string) $val) }}
                                    @else
                                        {{ (string) $val }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ max(1, count($preview['headers'])) }}" class="text-center text-muted-foreground py-6">No records matched the report filters for this organisation.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
