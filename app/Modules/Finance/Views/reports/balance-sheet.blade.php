@extends('layouts.metronic.app')

@section('title', 'Balance sheet')

@section('content')
@php
    use App\Modules\Finance\Support\Money;
    $sections = ['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'];
@endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Balance sheet</h1>
        <p class="text-xs text-muted-foreground">What you own, owe and are worth at {{ $asOf->toInstitutionalDate() }}.</p>
    </div>

    @include('finance::partials.nav')
    @include('finance::reports.tabs')

    <form method="GET" class="flex gap-2">
        <input class="kt-input w-48" type="date" name="as_of" value="{{ $asOf->toDateString() }}">
        <button class="kt-btn kt-btn-outline" type="submit">As at date</button>
    </form>

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Account</th><th class="text-end">{{ $currency }}</th></tr></thead>
                <tbody>
                @foreach($sections as $key => $label)
                    <tr><td colspan="2" class="font-semibold">{{ $label }}</td></tr>
                    @forelse($report[$key] as $row)
                        <tr><td class="ps-6">{{ $row['account']->code }} · {{ $row['account']->name }}</td><td class="text-end">{{ Money::format($row['amount'], '') }}</td></tr>
                    @empty
                        @if($key !== 'equity')<tr><td colspan="2" class="ps-6 text-muted-foreground">None.</td></tr>@endif
                    @endforelse
                    @if($key === 'equity')
                        <tr><td class="ps-6">Profit this financial year</td><td class="text-end">{{ Money::format($report['current_year_profit'], '') }}</td></tr>
                        <tr><td class="ps-6">Profit from earlier years (not yet moved to retained earnings)</td><td class="text-end">{{ Money::format($report['prior_years_profit'], '') }}</td></tr>
                    @endif
                    <tr class="font-semibold"><td>Total {{ mb_strtolower($label) }}</td><td class="text-end">{{ Money::format($report['total_'.$key], '') }}</td></tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-semibold"><td>Liabilities and equity</td><td class="text-end">{{ Money::format($report['total_liabilities'] + $report['total_equity'], '') }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
    @unless($report['balanced'])
        <div class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">The balance sheet does not balance. This should never happen; please report it.</div>
    @endunless
</div>
@endsection
