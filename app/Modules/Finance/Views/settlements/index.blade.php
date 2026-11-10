@extends('layouts.metronic.app')

@section('title', 'Gateway payouts')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Gateway payouts</h1>
            <p class="text-xs text-muted-foreground">Online payments wait in the Paystack clearing account until Paystack pays them out. Recording a payout moves the money into your bank and books Paystack's fees as a charge.</p>
        </div>
        @can('finance.bank.manage')
            <form method="POST" action="{{ route('finance.settlements.import') }}">@csrf<button class="kt-btn kt-btn-primary" type="submit">Fetch payouts from Paystack</button></form>
        @endcan
    </div>

    @include('finance::partials.nav')

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>Paid out</th><th>Reference</th><th class="text-end">Collected</th><th class="text-end">Fees</th><th class="text-end">To bank</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($settlements as $item)
            <tr>
                <td>{{ $item->settled_on->toInstitutionalDate() }}</td>
                <td>{{ $item->external_id }}</td>
                <td class="text-end">{{ Money::format($item->gross, $currency) }}</td>
                <td class="text-end">{{ Money::format($item->fees, $currency) }}</td>
                <td class="text-end">{{ Money::format($item->net, $currency) }}</td>
                <td>{{ $item->status === 'posted' ? 'In the books' : 'Not recorded yet' }}</td>
                <td class="text-end">@can('finance.bank.manage')@if($item->status !== 'posted')<form method="POST" action="{{ route('finance.settlements.post', $item->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-primary" type="submit">Record payout</button></form>@endif @endcan</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted-foreground py-6">No payouts fetched yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    {{ $settlements->links() }}
</div>
@endsection
