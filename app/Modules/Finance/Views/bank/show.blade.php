@extends('layouts.metronic.app')

@section('title', $bank->name)

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $bank->name }}</h1>
            <p class="text-xs text-muted-foreground">{{ $bank->account->code }} {{ $bank->account->name }} · {{ $summary['is_reconciled'] ? 'Reconciled' : 'Needs attention' }}</p>
        </div>
        @can('finance.bank.manage')
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('finance.bank.auto-match', $bank->uuid) }}">@csrf<button class="kt-btn kt-btn-sm kt-btn-primary" type="submit" @disabled(count($suggestions) === 0)>Match {{ count($suggestions) }} suggested</button></form>
        </div>
        @endcan
    </div>

    @include('finance::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Balance in the books</div><div class="mt-1 font-semibold">{{ Money::format($summary['ledger_balance'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Balance on the statement</div><div class="mt-1 font-semibold">{{ Money::format($summary['statement_balance'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Difference</div><div class="mt-1 font-semibold">{{ Money::format($summary['difference'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Still to review</div><div class="mt-1 font-semibold">{{ $summary['unmatched_statement'] }} on statement · {{ $summary['outstanding_ledger'] }} in books</div></div>
    </div>

    @can('finance.bank.manage')
    <form method="POST" action="{{ route('finance.bank.import', $bank->uuid) }}" enctype="multipart/form-data" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Import a statement</h3></div>
        <div class="kt-card-content flex flex-col md:flex-row md:items-center gap-3">
            <input class="kt-input" type="file" name="statement" accept=".csv,.txt" required>
            <button class="kt-btn kt-btn-primary" type="submit">Import CSV</button>
            <p class="text-xs text-muted-foreground">First row must name a Date column and either Amount (negative = money out) or Debit and Credit columns. Description and Reference are optional. Importing the same file twice adds nothing new.</p>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-header">
            <h3 class="kt-card-title">Statement lines</h3>
            <div class="flex gap-2">
                <a class="kt-btn kt-btn-sm {{ $filter === 'open' ? 'kt-btn-primary' : 'kt-btn-outline' }}" href="{{ route('finance.bank.show', [$bank->uuid, 'show' => 'open']) }}">To review</a>
                <a class="kt-btn kt-btn-sm {{ $filter === 'matched' ? 'kt-btn-primary' : 'kt-btn-outline' }}" href="{{ route('finance.bank.show', [$bank->uuid, 'show' => 'matched']) }}">Matched</a>
                <a class="kt-btn kt-btn-sm {{ $filter === 'all' ? 'kt-btn-primary' : 'kt-btn-outline' }}" href="{{ route('finance.bank.show', [$bank->uuid, 'show' => 'all']) }}">All</a>
            </div>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Date</th><th>Description</th><th class="text-end">Amount</th><th>Status</th><th>What to do</th></tr></thead>
                <tbody>
                @forelse($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->date->toInstitutionalDate() }}</td>
                        <td>{{ $transaction->description }}<div class="text-xs text-muted-foreground">{{ $transaction->reference }}</div></td>
                        <td class="text-end">{{ Money::format($transaction->amount, $currency) }}</td>
                        <td>{{ ucfirst($transaction->status) }}@if($transaction->journalLine) <div class="text-xs text-muted-foreground">{{ $transaction->journalLine->journal->number }} · {{ $transaction->journalLine->journal->memo }}</div>@elseif($transaction->matchedLines->isNotEmpty()) <div class="text-xs text-muted-foreground">{{ $transaction->matchedLines->count() }} ledger entries</div>@endif</td>
                        <td>
                            @can('finance.bank.manage')
                            @if($transaction->status === 'unmatched')
                                <div class="flex flex-col gap-2">
                                    @if(($candidates[$transaction->id] ?? collect())->isNotEmpty())
                                    <form method="POST" action="{{ route('finance.bank.match', $transaction->uuid) }}" class="flex gap-2">
                                        @csrf
                                        <select class="kt-select" name="journal_line_id">
                                            @foreach($candidates[$transaction->id] as $line)
                                                <option value="{{ $line->id }}" @selected(isset($suggestions[$transaction->id]) && $suggestions[$transaction->id]->id === $line->id)>{{ $line->journal->date->toInstitutionalDate() }} · {{ $line->journal->number }} · {{ $line->journal->memo }}</option>
                                            @endforeach
                                        </select>
                                        <button class="kt-btn kt-btn-xs kt-btn-primary" type="submit">Match</button>
                                    </form>
                                    @endif
                                    <form method="POST" action="{{ route('finance.bank.record', $transaction->uuid) }}" class="flex gap-2">
                                        @csrf
                                        <select class="kt-select" name="account_id" required>
                                            <option value="">{{ $transaction->amount < 0 ? 'Book as expense…' : 'Book as income…' }}</option>
                                            @foreach($transaction->amount < 0 ? $expenseAccounts : $inflowAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>@endforeach
                                        </select>
                                        <button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Book it</button>
                                    </form>
                                    @php $nearby = $outstanding->filter(fn ($line) => abs($line->journal->date->diffInDays($transaction->date)) <= 45)->values(); @endphp
                                    @if($nearby->count() > 1)
                                    <details class="text-xs">
                                        <summary class="cursor-pointer text-primary">Match to several entries</summary>
                                        <form method="POST" action="{{ route('finance.bank.match-many', $transaction->uuid) }}" class="mt-2 flex flex-col gap-1">@csrf
                                            @foreach($nearby as $line)
                                                <label class="flex gap-2"><input type="checkbox" name="journal_line_ids[]" value="{{ $line->id }}"> {{ $line->journal->date->toInstitutionalDate() }} · {{ $line->journal->number }} · {{ Money::format($line->debit - $line->credit, '') }}</label>
                                            @endforeach
                                            <div><button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Match selected</button></div>
                                        </form>
                                    </details>
                                    @endif
                                    <form method="POST" action="{{ route('finance.bank.exclude', $transaction->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-ghost" type="submit">Exclude</button></form>
                                </div>
                            @elseif($transaction->status === 'matched')
                                <form method="POST" action="{{ route('finance.bank.unmatch', $transaction->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Unmatch</button></form>
                            @else
                                <form method="POST" action="{{ route('finance.bank.restore', $transaction->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Restore</button></form>
                            @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted-foreground py-6">Nothing here. Import a statement to begin.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $transactions->links() }}

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">In the books, not on a statement yet</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Date</th><th>Entry</th><th>Memo</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @forelse($outstanding as $line)
                    <tr>
                        <td>{{ $line->journal->date->toInstitutionalDate() }}</td>
                        <td><a class="text-primary" href="{{ route('finance.journals.show', $line->journal->uuid) }}">{{ $line->journal->number }}</a></td>
                        <td>{{ $line->journal->memo }}</td>
                        <td class="text-end">{{ Money::format($line->debit - $line->credit, $currency) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted-foreground py-6">Everything in the books has been matched.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
