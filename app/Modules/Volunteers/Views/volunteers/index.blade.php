@extends('layouts.metronic.app')

@section('title', 'Volunteers')

@section('content')
@php use App\Modules\Volunteers\Models\VolVolunteer; @endphp
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Volunteers</h1><p class="text-xs text-muted-foreground">Everyone who has offered or been added. New public sign-ups wait for approval.</p></div>

    @include('volunteers::partials.nav')

    @can('volunteers.manage')
    <form method="POST" action="{{ route('volunteers.volunteers.store') }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Add a volunteer</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-3 gap-3">
            <input class="kt-input" name="name" value="{{ old('name') }}" placeholder="Name" required>
            <input class="kt-input" type="email" name="email" value="{{ old('email') }}" placeholder="Email">
            <input class="kt-input" name="phone" value="{{ old('phone') }}" placeholder="Phone">
            <input class="kt-input md:col-span-2" name="skills" value="{{ old('skills') }}" placeholder="Skills and interests">
            <input class="kt-input" name="emergency_contact" value="{{ old('emergency_contact') }}" placeholder="Emergency contact">
            <div><button class="kt-btn kt-btn-primary" type="submit">Save volunteer</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-header"><form method="GET" class="flex gap-2">
            <input class="kt-input" name="search" value="{{ $search }}" placeholder="Search name or email">
            <select class="kt-select w-48" name="status"><option value="">All</option>@foreach(VolVolunteer::STATUSES as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select>
            <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
        </form></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Name</th><th>Contact</th><th>Status</th><th class="text-end">Approved hours</th></tr></thead>
            <tbody>
            @forelse($volunteers as $volunteer)
                <tr><td><a class="text-primary" href="{{ route('volunteers.volunteers.show', $volunteer->uuid) }}">{{ $volunteer->name }}</a><div class="text-xs text-muted-foreground">{{ $volunteer->skills }}</div></td><td>{{ $volunteer->email }}<div class="text-xs text-muted-foreground">{{ $volunteer->phone }}</div></td><td>{{ VolVolunteer::STATUSES[$volunteer->status] ?? $volunteer->status }}</td><td class="text-end">{{ number_format(((int) $volunteer->approved_minutes) / 60, 1) }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted-foreground py-6">No volunteers found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    {{ $volunteers->links() }}
</div>
@endsection
