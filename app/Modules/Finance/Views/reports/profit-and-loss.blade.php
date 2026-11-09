@extends('layouts.metronic.app')

@section('title', 'Profit and loss')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Profit and loss</h1>
        <p class="text-xs text-muted-foreground">Income less expenses from {{ $from->toInstitutionalDate() }} to {{ $to->toInstitutionalDate() }}.</p>
    </div>

    @include('finance::partials.nav')
    @include('finance::reports.tabs')

    <form method="GET" class="flex flex-wrap gap-2">
        <input class="kt-input w-44" type="date" name="from" value="{{ $from->toDateString() }}">
        <input class="kt-input w-44" type="date" name="to" value="{{ $to->toDateString() }}">
        @if($funds->isNotEmpty())
        <select class="kt-select w-56" name="fund_id">
            <option value="">All funds</option>
            @foreach($funds as $fund)<option value="{{ $fund->id }}" @selected($fundId === $fund->id)>{{ $fund->name }}</option>@endforeach
        </select>
        @endif
        <button class="kt-btn kt-btn-outline" type="submit">Show</button>
    </form>

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Account</th><th class="text-end">{{ $currency }}</th></tr></thead>
                <tbody>
                    <tr><td colspan="2" class="font-semibold">Income</td></tr>
                    @forelse($report['income'] as $row)
                        <tr><td class="ps-6">{{ $row['account']->code }} · {{ $row['account']->name }}</td><td class="text-end">{{ Money::format($row['amount'], '') }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="ps-6 text-muted-foreground">No income in this period.</td></tr>
                    @endforelse
                    <tr class="font-semibold"><td>Total income</td><td class="text-end">{{ Money::format($report['total_income'], '') }}</td></tr>

                    <tr><td colspan="2" class="font-semibold">Expenses</td></tr>
                    @forelse($report['expenses'] as $row)
                        <tr><td class="ps-6">{{ $row['account']->code }} · {{ $row['account']->name }}</td><td class="text-end">{{ Money::format($row['amount'], '') }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="ps-6 text-muted-foreground">No expenses in this period.</td></tr>
                    @endforelse
                    <tr class="font-semibold"><td>Total expenses</td><td class="text-end">{{ Money::format($report['total_expenses'], '') }}</td></tr>
                </tbody>
                <tfoot><tr class="font-semibold"><td>{{ $report['net_profit'] >= 0 ? 'Net profit' : 'Net loss' }}</td><td class="text-end">{{ Money::format($report['net_profit'], '') }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
