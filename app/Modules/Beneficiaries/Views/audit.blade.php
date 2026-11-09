@extends('layouts.metronic.app')

@section('title', 'Access log')

@section('content')
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Access log</h1><p class="text-xs text-muted-foreground">Who opened or changed a beneficiary record, and when. Entries cannot be edited or deleted.</p></div>

    @include('beneficiaries::partials.nav')

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Detail</th></tr></thead>
        <tbody>
        @forelse($entries as $entry)
            <tr><td>{{ $entry->created_at->toInstitutionalDateTime() }}</td><td>{{ $users[$entry->user_uuid] ?? '—' }}</td><td>{{ str_replace('_', ' ', $entry->action) }}</td><td>{{ $references[$entry->beneficiary_id] ?? '' }}</td><td>{{ $entry->detail }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted-foreground py-6">Nothing logged yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    {{ $entries->links() }}
</div>
@endsection
