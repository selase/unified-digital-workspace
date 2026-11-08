@extends('layouts.metronic.app')

@section('title', 'Journals')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Journals</h1>
            <p class="text-xs text-muted-foreground">Every posting in the ledger. Posted journals cannot be edited, only reversed.</p>
        </div>
        @can('finance.journals.post')
            <a href="{{ route('finance.journals.create') }}" class="kt-btn kt-btn-sm kt-btn-primary">New manual journal</a>
        @endcan
    </div>

    @include('finance::partials.nav')

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Number</th><th>Date</th><th>Memo</th><th>Source</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($journals as $journal)
                    <tr>
                        <td><a class="text-primary" href="{{ route('finance.journals.show', $journal->uuid) }}">{{ $journal->number }}</a></td>
                        <td>{{ $journal->date->toInstitutionalDate() }}</td>
                        <td>{{ $journal->memo }}</td>
                        <td>{{ ucfirst($journal->source_type ?? 'manual') }}</td>
                        <td>{{ ucfirst($journal->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted-foreground py-6">No journals yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $journals->links() }}
</div>
@endsection
