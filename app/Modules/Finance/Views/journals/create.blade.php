@extends('layouts.metronic.app')

@section('title', 'New journal')

@section('content')
<div class="flex flex-col gap-6" x-data="{
    lines: [{account_id: '', debit: '', credit: '', fund_id: ''}, {account_id: '', debit: '', credit: '', fund_id: ''}],
    sum(key) { return this.lines.reduce((total, line) => total + (parseFloat(line[key]) || 0), 0); },
}">
    <div>
        <h1 class="text-xl font-bold text-foreground">New manual journal</h1>
        <p class="text-xs text-muted-foreground">Debits must equal credits before it can be posted.</p>
    </div>

    @include('finance::partials.nav')

    <form method="POST" action="{{ route('finance.journals.store') }}" class="kt-card">
        @csrf
        <div class="kt-card-content flex flex-col gap-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input class="kt-input" type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required>
                <input class="kt-input md:col-span-2" name="memo" value="{{ old('memo') }}" placeholder="Memo (what is this for?)">
            </div>

            <template x-for="(line, index) in lines" :key="index">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
                    <select class="kt-select md:col-span-5" :name="`lines[${index}][account_id]`" x-model="line.account_id" required>
                        <option value="">Account</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <input class="kt-input md:col-span-2" type="number" step="0.01" min="0" :name="`lines[${index}][debit]`" x-model="line.debit" placeholder="Debit">
                    <input class="kt-input md:col-span-2" type="number" step="0.01" min="0" :name="`lines[${index}][credit]`" x-model="line.credit" placeholder="Credit">
                    <select class="kt-select md:col-span-2" :name="`lines[${index}][fund_id]`" x-model="line.fund_id">
                        <option value="">No fund</option>
                        @foreach($funds as $fund)
                            <option value="{{ $fund->id }}">{{ $fund->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" x-show="lines.length > 2" @click="lines.splice(index, 1)">×</button>
                </div>
            </template>

            <div class="flex items-center justify-between">
                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="lines.push({account_id: '', debit: '', credit: '', fund_id: ''})">Add line</button>
                <div class="text-sm" :class="Math.abs(sum('debit') - sum('credit')) < 0.005 ? 'text-success' : 'text-destructive'">
                    Debits <span x-text="sum('debit').toFixed(2)"></span> · Credits <span x-text="sum('credit').toFixed(2)"></span>
                </div>
            </div>
            <div><button class="kt-btn kt-btn-primary" type="submit">Post journal</button></div>
        </div>
    </form>
</div>
@endsection
