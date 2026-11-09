@extends('layouts.metronic.app')

@section('title', 'Opportunities')

@section('content')
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Opportunities</h1><p class="text-xs text-muted-foreground">Events, shifts and tasks that need volunteers. Set places to 0 for no limit.</p></div>

    @include('volunteers::partials.nav')

    @can('volunteers.manage')
    <form method="POST" action="{{ route('volunteers.opportunities.store') }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">New opportunity</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input md:col-span-2" name="title" value="{{ old('title') }}" placeholder="Title" required>
            <input class="kt-input" type="date" name="starts_on" value="{{ old('starts_on') }}" required>
            <input class="kt-input" type="number" min="0" name="spots" value="{{ old('spots') }}" placeholder="Places (0 = any)">
            <input class="kt-input md:col-span-2" name="location" value="{{ old('location') }}" placeholder="Where">
            <input class="kt-input md:col-span-1" name="description" value="{{ old('description') }}" placeholder="What is involved?">
            <div><button class="kt-btn kt-btn-primary" type="submit">Create</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>Date</th><th>Opportunity</th><th>Signed up</th><th class="text-end">Places</th><th></th></tr></thead>
        <tbody>
        @forelse($opportunities as $op)
            <tr class="{{ $op->status === 'closed' ? 'text-muted-foreground' : '' }}">
                <td>{{ $op->starts_on->toInstitutionalDate() }}</td>
                <td>{{ $op->title }}{{ $op->status === 'closed' ? ' (closed)' : '' }}<div class="text-xs text-muted-foreground">{{ $op->location }} {{ $op->description }}</div></td>
                <td>{{ $op->volunteers->pluck('name')->implode(', ') ?: '—' }}</td>
                <td class="text-end">{{ $op->volunteers_count }}{{ $op->spots > 0 ? ' / '.$op->spots : '' }}</td>
                <td class="text-end">@can('volunteers.manage')<form method="POST" action="{{ route('volunteers.opportunities.toggle', $op->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">{{ $op->status === 'open' ? 'Close' : 'Reopen' }}</button></form>@endcan</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted-foreground py-6">No opportunities yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
