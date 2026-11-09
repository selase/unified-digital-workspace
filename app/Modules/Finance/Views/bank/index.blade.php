@extends('layouts.metronic.app')

@section('title', 'Banking')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Banking</h1>
        <p class="text-xs text-muted-foreground">Import your bank or mobile-money statement and match each line to what the books say.</p>
    </div>

    @include('finance::partials.nav')

    @can('finance.bank.manage')
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Add a bank account</h3></div>
        <form method="POST" action="{{ route('finance.bank.store') }}" class="kt-card-content grid grid-cols-1 md:grid-cols-3 gap-3">
            @csrf
            <input class="kt-input" name="name" value="{{ old('name') }}" placeholder="Name, e.g. GCB current account" required>
            <select class="kt-select" name="account_id" required>
                <option value="">Ledger account that tracks it</option>
                @foreach($linkableAccounts as $account)<option value="{{ $account->id }}" @selected((int) old('account_id') === $account->id)>{{ $account->code }} {{ $account->name }}</option>@endforeach
            </select>
            <input class="kt-input" name="institution" value="{{ old('institution') }}" placeholder="Bank or provider">
            <input class="kt-input" name="account_reference" value="{{ old('account_reference') }}" placeholder="Account reference (last digits only)">
            <input class="kt-input" type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance') }}" placeholder="Statement balance before the first imported line">
            <div><button class="kt-btn kt-btn-primary" type="submit">Add account</button></div>
        </form>
    </div>
    @endcan

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Account</th><th>Ledger account</th><th class="text-end">Books</th><th class="text-end">Statement</th><th class="text-end">Difference</th><th>To review</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($banks as $bank)
                    @php $summary = $summaries[$bank->id]; @endphp
                    <tr>
                        <td><a class="text-primary" href="{{ route('finance.bank.show', $bank->uuid) }}">{{ $bank->name }}</a><div class="text-xs text-muted-foreground">{{ $bank->institution }} {{ $bank->account_reference }}</div></td>
                        <td>{{ $bank->account->code }} {{ $bank->account->name }}</td>
                        <td class="text-end">{{ Money::format($summary['ledger_balance'], $currency) }}</td>
                        <td class="text-end">{{ Money::format($summary['statement_balance'], $currency) }}</td>
                        <td class="text-end">{{ Money::format($summary['difference'], $currency) }}</td>
                        <td>{{ $summary['unmatched_statement'] }} statement · {{ $summary['outstanding_ledger'] }} in books</td>
                        <td>{{ $summary['is_reconciled'] ? 'Reconciled' : 'Needs attention' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-foreground py-6">No bank accounts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
