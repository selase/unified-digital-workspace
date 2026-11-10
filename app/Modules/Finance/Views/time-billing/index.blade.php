@extends('layouts.metronic.app')

@section('title', 'Bill project time')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Bill project time</h1>
        <p class="text-xs text-muted-foreground">Choose a project and a period to see logged hours that have not been invoiced. Creating the invoice marks those hours as billed; voiding the invoice frees them again.</p>
    </div>

    @include('finance::partials.nav')

    <form method="GET" class="kt-card">
        <div class="kt-card-content flex flex-wrap gap-2">
            <select class="kt-select w-64" name="project" required><option value="">Project…</option>@foreach($projects as $item)<option value="{{ $item->uuid }}" @selected($project?->id === $item->id)>{{ $item->name }}</option>@endforeach</select>
            <input class="kt-input w-44" type="date" name="from" value="{{ $from->toDateString() }}">
            <input class="kt-input w-44" type="date" name="to" value="{{ $to->toDateString() }}">
            <button class="kt-btn kt-btn-outline" type="submit">Show unbilled time</button>
        </div>
    </form>

    @if($project)
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Unbilled time on {{ $project->name }}</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Task</th><th class="text-end">Entries</th><th class="text-end">Hours</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr><td>{{ $row->title }}</td><td class="text-end">{{ $row->entries }}</td><td class="text-end">{{ number_format($row->minutes / 60, 2) }}</td></tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted-foreground py-6">Nothing to bill in this period.</td></tr>
            @endforelse
            </tbody>
            @if($rows->isNotEmpty())<tfoot><tr><td class="font-semibold">Total</td><td></td><td class="text-end font-semibold">{{ number_format($rows->sum('minutes') / 60, 2) }}</td></tr></tfoot>@endif
        </table></div>
        @can('finance.invoices.manage')
        @if($rows->isNotEmpty())
        <form method="POST" action="{{ route('finance.time-billing.store') }}" class="kt-card-content flex flex-wrap gap-2">@csrf
            <input type="hidden" name="project" value="{{ $project->uuid }}"><input type="hidden" name="from" value="{{ $from->toDateString() }}"><input type="hidden" name="to" value="{{ $to->toDateString() }}">
            <select class="kt-select w-64" name="contact_id" required><option value="">Customer…</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->name }}</option>@endforeach</select>
            <input class="kt-input w-44" type="number" step="0.01" min="0.01" name="hourly_rate" placeholder="Rate per hour ({{ $currency }})" required>
            <select class="kt-select w-64" name="account_id"><option value="">Income account (default)</option>@foreach($incomeAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>@endforeach</select>
            <button class="kt-btn kt-btn-primary" type="submit">Create draft invoice</button>
        </form>
        @endif
        @endcan
    </div>
    @endif
</div>
@endsection
