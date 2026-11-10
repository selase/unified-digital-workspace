@extends('layouts.metronic.app')

@section('title', 'VAT summary')

@section('content')
@php
    use App\Modules\Finance\Support\Money;
    $labels = ['standard' => 'Standard-rated', 'zero_rated' => 'Zero-rated', 'exempt' => 'Exempt', 'uncoded' => 'No tax code'];
@endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">VAT summary</h1>
        <p class="text-xs text-muted-foreground">Output tax on sales less input tax on purchases, from {{ $report['from']->toInstitutionalDate() }} to {{ $report['to']->toInstitutionalDate() }}. These are the figures a return is prepared from; it is not a filing.</p>
    </div>

    @include('finance::partials.nav')
    @include('finance::reports.tabs')

    @unless($profile->tax_enabled)
        <div class="rounded-lg bg-muted/30 p-3 text-sm text-muted-foreground">VAT is switched off, so nothing is being coded. Switch it on under Settings to plan as if you are registered.</div>
    @endunless

    <form method="GET" class="flex gap-2 print:hidden">
        <input class="kt-input w-44" type="date" name="from" value="{{ $report['from']->toDateString() }}">
        <input class="kt-input w-44" type="date" name="to" value="{{ $report['to']->toDateString() }}">
        <button class="kt-btn kt-btn-outline" type="submit">Show</button>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Output tax (sales)</div><div class="mt-1 text-lg font-semibold">{{ Money::format($report['output']['total'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Input tax (purchases)</div><div class="mt-1 text-lg font-semibold">{{ Money::format($report['input']['total'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">{{ $report['net_payable'] >= 0 ? 'Net tax payable' : 'Net tax refundable' }}</div><div class="mt-1 text-lg font-semibold">{{ Money::format(abs($report['net_payable']), $currency) }}</div></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach(['sales' => ['Sales', 'output'], 'purchases' => ['Purchases', 'input']] as $key => [$title, $taxKey])
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">{{ $title }}</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <thead><tr><th>{{ $title }} before tax</th><th class="text-end">{{ $currency }}</th></tr></thead>
                <tbody>
                    @foreach($labels as $code => $label)<tr><td>{{ $label }}</td><td class="text-end">{{ Money::format($report[$key][$code], '') }}</td></tr>@endforeach
                    <tr class="font-semibold"><td>{{ $taxKey === 'output' ? 'Output' : 'Input' }} tax</td><td class="text-end">{{ Money::format($report[$taxKey]['total'], '') }}</td></tr>
                    @foreach($report[$taxKey]['components'] as $name => $amount)<tr><td class="ps-6">{{ $name }}</td><td class="text-end">{{ Money::format($amount, '') }}</td></tr>@endforeach
                </tbody>
            </table></div>
        </div>
        @endforeach
    </div>

    <p class="text-xs {{ $report['reconciled'] ? 'text-muted-foreground' : 'text-destructive' }}">
        Ledger check: tax posted to the books this period is {{ Money::format($report['ledger']['output'], $currency) }} output and {{ Money::format($report['ledger']['input'], $currency) }} input. {{ $report['reconciled'] ? 'It matches the documents above.' : 'It differs from the documents above; a manual journal may have touched the tax accounts.' }}
    </p>
</div>
@endsection
