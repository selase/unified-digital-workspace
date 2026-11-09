@extends('layouts.metronic.app')

@section('title', 'Expenses')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Expenses</h1>
        <p class="text-xs text-muted-foreground">Costs paid straight away, such as card, cash or mobile money. For supplier invoices you pay later, use Bills.</p>
    </div>

    @include('finance::partials.nav')

    @can('finance.expenses.manage')
    <form method="POST" action="{{ route('finance.expenses.store') }}" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Record an expense</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input" type="date" name="date" value="{{ old('date', today()->toDateString()) }}" required>
            <input class="kt-input" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" placeholder="Amount" required>
            <select class="kt-select" name="account_id" required>
                <option value="">Expense account</option>
                @foreach($expenseAccounts as $account)<option value="{{ $account->id }}" @selected((int) old('account_id') === $account->id)>{{ $account->code }} {{ $account->name }}</option>@endforeach
            </select>
            <select class="kt-select" name="paid_from_account_id" required>
                <option value="">Paid from</option>
                @foreach($paidFromAccounts as $account)<option value="{{ $account->id }}" @selected((int) old('paid_from_account_id') === $account->id)>{{ $account->name }}</option>@endforeach
            </select>
            <select class="kt-select" name="contact_id">
                <option value="">Supplier (optional)</option>
                @foreach($vendors as $vendor)<option value="{{ $vendor->id }}" @selected((int) old('contact_id') === $vendor->id)>{{ $vendor->name }}</option>@endforeach
            </select>
            @if($funds->isNotEmpty())
            <select class="kt-select" name="fund_id">
                <option value="">No fund</option>
                @foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }}</option>@endforeach
            </select>
            @endif
            <input class="kt-input" name="description" value="{{ old('description') }}" placeholder="What was it for?">
            <input class="kt-input" name="reference" value="{{ old('reference') }}" placeholder="Receipt / reference no.">
            <div><button class="kt-btn kt-btn-primary" type="submit">Record expense</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Number</th><th>Date</th><th>Account</th><th>Paid from</th><th>Supplier</th><th>Details</th><th class="text-end">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse($expenses as $expense)
                    <tr class="{{ $expense->voided_at ? 'text-muted-foreground line-through' : '' }}">
                        <td>{{ $expense->number }}</td>
                        <td>{{ $expense->date->toInstitutionalDate() }}</td>
                        <td>{{ $expense->account->name }}</td>
                        <td>{{ $expense->paidFrom->name }}</td>
                        <td>{{ $expense->contact?->name }}</td>
                        <td>{{ $expense->description }} {{ $expense->reference ? '('.$expense->reference.')' : '' }}</td>
                        <td class="text-end">{{ Money::format($expense->amount, $currency) }}</td>
                        <td class="text-end">
                            @can('finance.expenses.manage')
                            @unless($expense->voided_at)
                            <form method="POST" action="{{ route('finance.expenses.void', $expense->uuid) }}" onsubmit="return confirm('Void this expense?')">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Void</button></form>
                            @endunless
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted-foreground py-6">No expenses yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $expenses->links() }}
</div>
@endsection
