@extends('layouts.metronic.app')

@section('title', 'Actions')

@section('content')
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Actions</h1><p class="text-xs text-muted-foreground">What people agreed to do, and by when.</p></div>

    @include('governance::partials.nav')

    @can('governance.manage')
    <form method="POST" action="{{ route('governance.actions.store') }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">New action</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input md:col-span-2" name="description" value="{{ old('description') }}" placeholder="What has to be done?" required>
            <select class="kt-select" name="member_id"><option value="">Owner: choose a member</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select>
            <input class="kt-input" name="owner_name" value="{{ old('owner_name') }}" placeholder="…or type a name">
            <input class="kt-input" type="date" name="due_on" value="{{ old('due_on') }}" title="Due date">
            <select class="kt-select" name="meeting_id"><option value="">From a meeting (optional)</option>@foreach($meetings as $meeting)<option value="{{ $meeting->id }}">{{ $meeting->meets_at->format('d/m/Y') }} {{ $meeting->title }}</option>@endforeach</select>
            <div><button class="kt-btn kt-btn-primary" type="submit">Add action</button></div>
        </div>
    </form>
    @endcan

    <div class="flex gap-2">
        @foreach(['open' => 'Open', 'done' => 'Done', 'all' => 'All'] as $key => $label)<a class="kt-btn kt-btn-sm {{ $status === $key ? 'kt-btn-primary' : 'kt-btn-outline' }}" href="{{ route('governance.actions.index', ['status' => $key]) }}">{{ $label }}</a>@endforeach
    </div>

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>Action</th><th>Owner</th><th>From meeting</th><th class="text-end">Due</th><th></th></tr></thead>
        <tbody>
        @forelse($actions as $action)
            <tr class="{{ $action->status === 'done' ? 'text-muted-foreground' : '' }}">
                <td>{{ $action->description }}</td><td>{{ $action->owner_name }}</td><td>{{ $action->meeting?->title }}</td>
                <td class="text-end {{ $action->isOverdue() ? 'text-destructive' : '' }}">{{ $action->due_on?->toInstitutionalDate() ?? '—' }}</td>
                <td class="text-end">@can('governance.manage')<form method="POST" action="{{ route('governance.actions.toggle', $action->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">{{ $action->status === 'open' ? 'Mark done' : 'Reopen' }}</button></form>@endcan</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted-foreground py-6">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
