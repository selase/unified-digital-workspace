@extends('layouts.metronic.app')

@section('title', 'Cash flow')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Cash flow</h1>
        <p class="text-xs text-muted-foreground">Where cash came from and went between {{ $from->toInstitutionalDate() }} and {{ $to->toInstitutionalDate() }}. Cash means your bank accounts and the Paystack clearing account. Investing and financing are picked by account code (15xx equipment, 25xx loans, and equity).</p>
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
                <thead><tr><th>&nbsp;</th><th class="text-end">{{ $currency }}</th></tr></thead>
                <tbody>
                    <tr class="font-semibold"><td>Cash at the start</td><td class="text-end">{{ Money::format($report['opening'], '') }}</td></tr>
                    @foreach($report['sections'] as $section)
                        <tr><td colspan="2" class="font-semibold">{{ $section['label'] }}</td></tr>
                        @forelse($section['rows'] as $row)
                            <tr><td class="ps-6">{{ $row['account']->code }} · {{ $row['account']->name }}</td><td class="text-end">{{ Money::format($row['amount'], '') }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="ps-6 text-muted-foreground">None.</td></tr>
                        @endforelse
                        <tr class="font-semibold"><td>Net from {{ mb_strtolower($section['label']) }}</td><td class="text-end">{{ Money::format($section['total'], '') }}</td></tr>
                    @endforeach
                    <tr class="font-semibold"><td>Net change in cash</td><td class="text-end">{{ Money::format($report['net_change'], '') }}</td></tr>
                </tbody>
                <tfoot><tr class="font-semibold"><td>Cash at the end</td><td class="text-end">{{ Money::format($report['closing'], '') }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
