@extends('layouts.metronic.app')

@section('title', 'Aged payables')

@section('content')
@php
    use App\Modules\Finance\Support\Money;
    $labels = ['current' => 'Not yet due', '1_30' => '1-30 days', '31_60' => '31-60 days', '61_90' => '61-90 days', '90_plus' => 'Over 90 days'];
@endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Aged payables</h1>
        <p class="text-xs text-muted-foreground">What you owe suppliers, by how late it is.</p>
    </div>

    @include('finance::partials.nav')
    @include('finance::reports.tabs')

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        @foreach($labels as $key => $label)
            <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">{{ $label }}</div><div class="mt-1 font-semibold">{{ Money::format($report['totals'][$key], $currency) }}</div></div>
        @endforeach
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Total payable</div><div class="mt-1 font-semibold">{{ Money::format($report['totals']['total'], $currency) }}</div></div>
    </div>

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Bill</th><th>Supplier</th><th>Due</th><th>Days late</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td><a class="text-primary" href="{{ route('finance.bills.show', $row['bill']->uuid) }}">{{ $row['bill']->number }}</a></td>
                        <td>{{ $row['bill']->contact->name }}</td>
                        <td>{{ $row['bill']->due_date?->toInstitutionalDate() }}</td>
                        <td>{{ $row['days_late'] ?: '' }}</td>
                        <td class="text-end">{{ Money::format($row['balance'], $currency) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted-foreground py-6">You owe no one anything.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
