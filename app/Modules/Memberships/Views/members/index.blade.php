@extends('layouts.metronic.app')

@section('title', 'Members')

@section('content')
@php use App\Modules\Memberships\Models\MemMember; @endphp
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Members</h1><p class="text-xs text-muted-foreground">Adding a member with a paid type raises their dues invoice straight away.</p></div>

    @include('memberships::partials.nav')

    @can('memberships.manage')
    <form method="POST" action="{{ route('memberships.members.store') }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Add a member</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input" name="name" value="{{ old('name') }}" placeholder="Name" required>
            <input class="kt-input" type="email" name="email" value="{{ old('email') }}" placeholder="Email">
            <input class="kt-input" name="phone" value="{{ old('phone') }}" placeholder="Phone">
            <select class="kt-select" name="type_id" required><option value="">Membership type…</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected((int) old('type_id') === $type->id)>{{ $type->name }}</option>@endforeach</select>
            <div><button class="kt-btn kt-btn-primary" type="submit">Add member</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-header"><form method="GET" class="flex flex-wrap gap-2">
            <input class="kt-input" name="search" value="{{ $search }}" placeholder="Search name, email or number">
            <select class="kt-select w-56" name="status"><option value="">All</option>@foreach(MemMember::STATUSES as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select>
            <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
        </form></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Member</th><th>Type</th><th>Status</th><th class="text-end">Paid until</th></tr></thead>
            <tbody>
            @forelse($members as $member)
                <tr><td><a class="text-primary" href="{{ route('memberships.members.show', $member->uuid) }}">{{ $member->name }}</a><div class="text-xs text-muted-foreground">{{ $member->number }} · {{ $member->email }}</div></td><td>{{ $member->type->name }}</td><td>{{ MemMember::STATUSES[$member->status] ?? $member->status }}</td><td class="text-end">{{ $member->expires_on?->toInstitutionalDate() ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted-foreground py-6">No members found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    {{ $members->links() }}
</div>
@endsection
