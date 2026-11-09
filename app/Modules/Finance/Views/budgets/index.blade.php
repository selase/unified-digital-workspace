@extends('layouts.metronic.app')

@section('title', 'Budgets')

@section('content')
<div class="flex flex-col gap-6" x-data="{ lines: @js(old('lines', [['account_id' => '', 'fund_id' => '', 'amount' => '']])) }">
    <div>
        <h1 class="text-xl font-bold text-foreground">Budgets</h1>
        <p class="text-xs text-muted-foreground">Plan what you expect to earn and spend, then compare with what really happened. The plan is spread evenly over the months.</p>
    </div>

    @include('finance::partials.nav')

    @can('finance.budgets.manage')
    <form method="POST" action="{{ route('finance.budgets.store') }}" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">New budget</h3></div>
        <div class="kt-card-content flex flex-col gap-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input class="kt-input" name="name" value="{{ old('name') }}" placeholder="e.g. Budget 2026" required>
                <input class="kt-input" type="date" name="starts_on" value="{{ old('starts_on', $defaultStart) }}" required>
                <input class="kt-input" type="date" name="ends_on" value="{{ old('ends_on', $defaultEnd) }}" required>
            </div>
            <template x-for="(line, index) in lines" :key="index">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
                    <select class="kt-select md:col-span-5" :name="`lines[${index}][account_id]`" x-model="line.account_id" required>
                        <option value="">Income or expense account</option>
                        @foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>@endforeach
                    </select>
                    <select class="kt-select md:col-span-3" :name="`lines[${index}][fund_id]`" x-model="line.fund_id">
                        <option value="">All funds</option>
                        @foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }}</option>@endforeach
                    </select>
                    <input class="kt-input md:col-span-3" type="number" step="0.01" min="0.01" :name="`lines[${index}][amount]`" x-model="line.amount" placeholder="Amount for the whole period" required>
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" x-show="lines.length > 1" @click="lines.splice(index, 1)">×</button>
                </div>
            </template>
            <div class="flex gap-2">
                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="lines.push({account_id: '', fund_id: '', amount: ''})">Add line</button>
                <button class="kt-btn kt-btn-primary" type="submit">Save budget</button>
            </div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Budget</th><th>Period</th><th class="text-end">Lines</th></tr></thead>
                <tbody>
                @forelse($budgets as $budget)
                    <tr><td><a class="text-primary" href="{{ route('finance.budgets.show', $budget->uuid) }}">{{ $budget->name }}</a></td><td>{{ $budget->starts_on->toInstitutionalDate() }} to {{ $budget->ends_on->toInstitutionalDate() }}</td><td class="text-end">{{ $budget->lines_count }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted-foreground py-6">No budgets yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
