@extends('layouts.metronic.app')

@section('title', 'Trial balance')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Trial balance</h1>
        <p class="text-xs text-muted-foreground">Debits and credits per account. The two totals must always match.</p>
    </div>

    @include('finance::partials.nav')
    @include('finance::reports.tabs')

    <form method="GET" class="flex gap-2">
        <input class="kt-input w-48" type="date" name="as_of" value="{{ $asOf }}">
        <button class="kt-btn kt-btn-outline" type="submit">As at date</button>
    </form>

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['account']->code }} · {{ $row['account']->name }}</td>
                        <td class="text-end">{{ $row['debit'] ? Money::format($row['debit'], '') : '' }}</td>
                        <td class="text-end">{{ $row['credit'] ? Money::format($row['credit'], '') : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted-foreground py-6">No postings yet.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-semibold"><td>Total ({{ $currency }})</td><td class="text-end">{{ Money::format($report['debit'], '') }}</td><td class="text-end">{{ Money::format($report['credit'], '') }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
