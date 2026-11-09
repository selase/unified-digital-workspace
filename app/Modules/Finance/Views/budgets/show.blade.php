@extends('layouts.metronic.app')

@section('title', $budget->name)

@section('content')
@php use App\Modules\Finance\Support\Money; $t = $report['totals']; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $budget->name }}</h1>
            <p class="text-xs text-muted-foreground">{{ $budget->starts_on->toInstitutionalDate() }} to {{ $budget->ends_on->toInstitutionalDate() }} · {{ $report['elapsed_months'] }} of {{ $report['total_months'] }} months used. Variance is positive when you are doing better than plan (more income, or less spent).</p>
        </div>
        @can('finance.budgets.manage')
        <form method="POST" action="{{ route('finance.budgets.destroy', $budget->uuid) }}" onsubmit="return confirm('Delete this budget?')">@csrf @method('DELETE')<button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Delete</button></form>
        @endcan
    </div>

    @include('finance::partials.nav')

    <form method="GET" class="flex gap-2"><input class="kt-input w-48" type="date" name="as_of" value="{{ $asOf->toDateString() }}"><button class="kt-btn kt-btn-outline" type="submit">Compare as at</button></form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Income budget</div><div class="mt-1 font-semibold">{{ Money::format($t['income_budget'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Income so far</div><div class="mt-1 font-semibold">{{ Money::format($t['income_actual'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Expense budget</div><div class="mt-1 font-semibold">{{ Money::format($t['expense_budget'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Spent so far</div><div class="mt-1 font-semibold">{{ Money::format($t['expense_actual'], $currency) }}</div></div>
    </div>

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Account</th><th>Fund</th><th class="text-end">Budget</th><th class="text-end">Planned to date</th><th class="text-end">Actual</th><th class="text-end">Variance</th><th class="text-end">% of budget</th></tr></thead>
                <tbody>
                @foreach($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['line']->account->code }} · {{ $row['line']->account->name }}</td>
                        <td>{{ $row['line']->fund?->name ?? 'All funds' }}</td>
                        <td class="text-end">{{ Money::format($row['budget'], '') }}</td>
                        <td class="text-end">{{ Money::format($row['to_date'], '') }}</td>
                        <td class="text-end">{{ Money::format($row['actual'], '') }}</td>
                        <td class="text-end {{ $row['variance'] < 0 ? 'text-destructive' : 'text-success' }}">{{ Money::format($row['variance'], '') }}</td>
                        <td class="text-end">{{ $row['percent'] }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
