@extends('layouts.metronic.app')

@section('title', 'Grants')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Grants</h1>
        <p class="text-xs text-muted-foreground">Money promised by funders, what has arrived, and how much of it has been spent on the work.</p>
    </div>

    @include('grants::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Awarded (open grants)</div><div class="mt-1 text-lg font-semibold">{{ Money::format($rows->sum(fn ($r) => $r['position']['awarded']), $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Received so far</div><div class="mt-1 text-lg font-semibold">{{ Money::format($rows->sum(fn ($r) => $r['position']['received']), $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Spent on grants</div><div class="mt-1 text-lg font-semibold">{{ Money::format($rows->sum(fn ($r) => $r['position']['spent']), $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">In the pipeline</div><div class="mt-1 text-lg font-semibold">{{ Money::format($pipelineTotal, $currency) }}</div></div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Open grants</h3><a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('grants.grants.index') }}">All grants</a></div>
        <div class="kt-card-content flex flex-col gap-4">
            @forelse($rows as $row)
                <div>
                    <div class="flex flex-wrap justify-between gap-2 text-sm">
                        <a class="text-primary font-medium" href="{{ route('grants.grants.show', $row['grant']->uuid) }}">{{ $row['grant']->title }}</a>
                        <span class="text-muted-foreground">{{ $row['grant']->funder->name }} · spent {{ Money::format($row['position']['spent'], $currency) }} of {{ Money::format($row['position']['awarded'], $currency) }}</span>
                    </div>
                    <div class="mt-1 h-2 rounded bg-muted"><div class="h-2 rounded bg-primary" style="width: {{ $row['position']['spent_percent'] }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-muted-foreground">No awarded grants yet. Add a funder, then a grant, and mark it awarded.</p>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Money expected</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Due</th><th>Grant</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @forelse($upcoming as $item)
                    <tr><td class="{{ $item->isOverdue() ? 'text-destructive' : '' }}">{{ $item->expected_on->toInstitutionalDate() }}{{ $item->isOverdue() ? ' (late)' : '' }}</td><td><a class="text-primary" href="{{ route('grants.grants.show', $item->grant->uuid) }}">{{ $item->grant->title }}</a></td><td class="text-end">{{ Money::format($item->amount, $currency) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted-foreground py-6">Nothing expected.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Reports due to funders</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Due</th><th>Report</th><th>Grant</th></tr></thead>
                <tbody>
                @forelse($reports as $report)
                    <tr><td class="{{ $report->isOverdue() ? 'text-destructive' : '' }}">{{ $report->due_on->toInstitutionalDate() }}{{ $report->isOverdue() ? ' (overdue)' : '' }}</td><td>{{ $report->title }}</td><td>{{ $report->grant->title }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted-foreground py-6">No reports pending.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
