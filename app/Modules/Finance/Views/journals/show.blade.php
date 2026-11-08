@extends('layouts.metronic.app')

@section('title', $journal->number)

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $journal->number }}</h1>
            <p class="text-xs text-muted-foreground">{{ $journal->date->toInstitutionalDate() }} · {{ ucfirst($journal->status) }} · {{ $journal->memo }}</p>
        </div>
        @can('finance.journals.post')
            @if($journal->source_type === 'manual' && $journal->status === 'posted' && $journal->reverses_journal_id === null)
                <form method="POST" action="{{ route('finance.journals.reverse', $journal->uuid) }}" onsubmit="return confirm('Reverse this journal?')">
                    @csrf
                    <button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Reverse</button>
                </form>
            @endif
        @endcan
    </div>

    @include('finance::partials.nav')

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                <tbody>
                @foreach($journal->lines as $line)
                    <tr>
                        <td>{{ $line->account->code }} · {{ $line->account->name }}</td>
                        <td class="text-end">{{ $line->debit ? Money::format($line->debit, '') : '' }}</td>
                        <td class="text-end">{{ $line->credit ? Money::format($line->credit, '') : '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
