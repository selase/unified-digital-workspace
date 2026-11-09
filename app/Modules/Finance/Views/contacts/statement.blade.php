@extends('layouts.metronic.app')

@section('title', 'Statement: '.$report['contact']->name)

@section('content')
@php use App\Modules\Finance\Support\Money; $contact = $report['contact']; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Statement of account</h1>
        <p class="text-xs text-muted-foreground">{{ $contact->name }} · {{ $report['from']->toInstitutionalDate() }} to {{ $report['to']->toInstitutionalDate() }}</p>
    </div>

    @include('finance::partials.nav')

    <form method="GET" class="flex flex-wrap gap-2 print:hidden">
        <input class="kt-input w-44" type="date" name="from" value="{{ $report['from']->toDateString() }}">
        <input class="kt-input w-44" type="date" name="to" value="{{ $report['to']->toDateString() }}">
        <button class="kt-btn kt-btn-outline" type="submit">Show</button>
        <a class="kt-btn kt-btn-ghost" href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}">Download CSV</a>
        <button type="button" class="kt-btn kt-btn-ghost" onclick="window.print()">Print / save as PDF</button>
    </form>

    <div class="hidden print:block text-sm"><strong>{{ $profile->legal_name }}</strong><br>{{ $profile->address }}</div>

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>Date</th><th>Type</th><th>Number</th><th class="text-end">Charges</th><th class="text-end">Credits</th><th class="text-end">Balance</th></tr></thead>
        <tbody>
            <tr class="font-semibold"><td colspan="5">Opening balance</td><td class="text-end">{{ Money::format($report['opening'], '') }}</td></tr>
            @forelse($report['lines'] as $line)
                <tr><td>{{ $line['date']->toInstitutionalDate() }}</td><td>{{ $line['type'] }}</td><td>{{ $line['number'] }}</td><td class="text-end">{{ $line['debit'] ? Money::format($line['debit'], '') : '' }}</td><td class="text-end">{{ $line['credit'] ? Money::format($line['credit'], '') : '' }}</td><td class="text-end">{{ Money::format($line['balance'], '') }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted-foreground py-6">Nothing in this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr class="font-semibold"><td colspan="5">Amount due ({{ $currency }})</td><td class="text-end">{{ Money::format($report['closing'], '') }}</td></tr></tfoot>
    </table></div></div>
</div>
@endsection
