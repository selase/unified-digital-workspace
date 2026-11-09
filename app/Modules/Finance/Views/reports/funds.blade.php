@extends('layouts.metronic.app')

@section('title', 'Fund report')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Fund report</h1>
        <p class="text-xs text-muted-foreground">Income and spending by fund from {{ $from->toInstitutionalDate() }} to {{ $to->toInstitutionalDate() }}. The balance is everything raised less everything spent up to the end date. Restricted funds can only be spent on their purpose.</p>
    </div>

    @include('finance::partials.nav')
    @include('finance::reports.tabs')

    <form method="GET" class="flex gap-2">
        <input class="kt-input w-44" type="date" name="from" value="{{ $from->toDateString() }}">
        <input class="kt-input w-44" type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="kt-btn kt-btn-outline" type="submit">Show</button>
    </form>

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Fund</th><th>Type</th><th class="text-end">Income</th><th class="text-end">Spent</th><th class="text-end">Net in period</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                @foreach($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['fund'] === null ? '' : ($row['fund']->is_restricted ? 'Restricted' : 'Unrestricted') }}</td>
                        <td class="text-end">{{ Money::format($row['income'], '') }}</td>
                        <td class="text-end">{{ Money::format($row['expenses'], '') }}</td>
                        <td class="text-end">{{ Money::format($row['net'], '') }}</td>
                        <td class="text-end">{{ Money::format($row['balance'], '') }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr class="font-semibold"><td colspan="2">Total ({{ $currency }})</td><td class="text-end">{{ Money::format($report['totals']['income'], '') }}</td><td class="text-end">{{ Money::format($report['totals']['expenses'], '') }}</td><td class="text-end">{{ Money::format($report['totals']['net'], '') }}</td><td class="text-end">{{ Money::format($report['totals']['balance'], '') }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
