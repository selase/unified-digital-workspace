@extends('layouts.metronic.app')

@section('title', 'General ledger')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">General ledger</h1>
        <p class="text-xs text-muted-foreground">Every posting to one account, with a running balance, from {{ $from->toInstitutionalDate() }} to {{ $to->toInstitutionalDate() }}.</p>
    </div>

    @include('finance::partials.nav')
    @include('finance::reports.tabs')

    <form method="GET" class="flex flex-wrap gap-2 print:hidden">
        <select class="kt-select w-72" name="account_id" required>
            <option value="">Choose an account</option>
            @foreach($accounts as $account)<option value="{{ $account->id }}" @selected($accountId === $account->id)>{{ $account->code }} · {{ $account->name }}</option>@endforeach
        </select>
        <input class="kt-input w-44" type="date" name="from" value="{{ $from->toDateString() }}">
        <input class="kt-input w-44" type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="kt-btn kt-btn-outline" type="submit">Show</button>
    </form>

    @if($report)
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">{{ $report['account']->code }} · {{ $report['account']->name }}</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Date</th><th>Entry</th><th>Memo</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    <tr class="font-semibold"><td colspan="5">Opening balance</td><td class="text-end">{{ Money::format($report['opening'], '') }}</td></tr>
                    @forelse($report['lines'] as $line)
                        <tr>
                            <td>{{ $line['date']->toInstitutionalDate() }}</td>
                            <td><a class="text-primary" href="{{ route('finance.journals.show', $line['journal_uuid']) }}">{{ $line['number'] }}</a></td>
                            <td>{{ $line['memo'] }}</td>
                            <td class="text-end">{{ $line['debit'] ? Money::format($line['debit'], '') : '' }}</td>
                            <td class="text-end">{{ $line['credit'] ? Money::format($line['credit'], '') : '' }}</td>
                            <td class="text-end">{{ Money::format($line['balance'], '') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted-foreground py-6">No postings in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr class="font-semibold"><td colspan="3">Closing balance ({{ $currency }})</td><td class="text-end">{{ Money::format($report['total_debit'], '') }}</td><td class="text-end">{{ Money::format($report['total_credit'], '') }}</td><td class="text-end">{{ Money::format($report['closing'], '') }}</td></tr></tfoot>
            </table>
        </div>
    </div>
    @else
        <p class="text-sm text-muted-foreground">Choose an account to see its postings.</p>
    @endif
</div>
@endsection
