@extends('layouts.metronic.app')

@section('title', 'Chart of accounts')

@section('content')
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Chart of accounts</h1>
        <p class="text-xs text-muted-foreground">Every amount in the ledger belongs to one of these accounts.</p>
    </div>

    @include('finance::partials.nav')

    @can('finance.accounts.manage')
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Add an account</h3></div>
        <form method="POST" action="{{ route('finance.accounts.store') }}" class="kt-card-content grid grid-cols-1 md:grid-cols-5 gap-3">
            @csrf
            <input class="kt-input" name="code" value="{{ old('code') }}" placeholder="Code, e.g. 6150" required>
            <input class="kt-input md:col-span-2" name="name" value="{{ old('name') }}" placeholder="Account name" required>
            <select class="kt-select" name="type" required>
                @foreach($types as $key => $label)
                    <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="kt-btn kt-btn-primary justify-center" type="submit">Add</button>
        </form>
    </div>
    @endcan

    @foreach($types as $key => $label)
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">{{ $label }}</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead><tr><th>Code</th><th>Name</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse(($accounts[$key] ?? collect()) as $account)
                        <tr>
                            <td>{{ $account->code }}</td>
                            <td>{{ $account->name }} @if($account->system_key)<span class="text-xs text-muted-foreground">(system)</span>@endif</td>
                            <td>{{ $account->is_active ? 'Active' : 'Off' }}</td>
                            <td class="text-end">
                                @can('finance.accounts.manage')
                                <form method="POST" action="{{ route('finance.accounts.toggle', $account->uuid) }}">
                                    @csrf
                                    <button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">{{ $account->is_active ? 'Switch off' : 'Switch on' }}</button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted-foreground py-4">No {{ mb_strtolower($label) }} accounts.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
