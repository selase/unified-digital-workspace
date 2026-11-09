@extends('layouts.metronic.app')

@section('title', 'Volunteer hours')

@section('content')
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Hours</h1><p class="text-xs text-muted-foreground">Log time volunteers gave, then approve it so it counts.</p></div>

    @include('volunteers::partials.nav')

    @can('volunteers.manage')
    <form method="POST" action="{{ route('volunteers.hours.store') }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Log hours</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <select class="kt-select" name="volunteer_id" required><option value="">Volunteer</option>@foreach($volunteers as $v)<option value="{{ $v->id }}" @selected((int) old('volunteer_id') === $v->id)>{{ $v->name }}</option>@endforeach</select>
            <input class="kt-input" type="date" name="worked_on" value="{{ old('worked_on', today()->toDateString()) }}" required>
            <input class="kt-input" type="number" step="0.25" min="0.25" max="24" name="hours" value="{{ old('hours') }}" placeholder="Hours" required>
            <select class="kt-select" name="opportunity_id"><option value="">No opportunity</option>@foreach($opportunities as $op)<option value="{{ $op->id }}">{{ $op->title }}</option>@endforeach</select>
            <input class="kt-input md:col-span-3" name="activity" value="{{ old('activity') }}" placeholder="What did they do?">
            <div><button class="kt-btn kt-btn-primary" type="submit">Log hours</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-header"><div class="flex gap-2">@foreach(['pending' => 'To approve', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)<a class="kt-btn kt-btn-sm {{ $status === $key ? 'kt-btn-primary' : 'kt-btn-outline' }}" href="{{ route('volunteers.hours.index', ['status' => $key]) }}">{{ $label }}</a>@endforeach</div></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Date</th><th>Volunteer</th><th>Activity</th><th>Status</th><th class="text-end">Hours</th><th></th></tr></thead>
            <tbody>
            @forelse($hours as $hour)
                <tr>
                    <td>{{ $hour->worked_on->toInstitutionalDate() }}</td>
                    <td><a class="text-primary" href="{{ route('volunteers.volunteers.show', $hour->volunteer->uuid) }}">{{ $hour->volunteer->name }}</a></td>
                    <td>{{ $hour->activity }} {{ $hour->opportunity ? '('.$hour->opportunity->title.')' : '' }}</td>
                    <td>{{ ucfirst($hour->status) }}</td>
                    <td class="text-end">{{ number_format($hour->hoursDecimal(), 2) }}</td>
                    <td class="text-end">@can('volunteers.hours.approve')@if($hour->status === 'pending')<div class="flex justify-end gap-1">
                        <form method="POST" action="{{ route('volunteers.hours.review', $hour->uuid) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="kt-btn kt-btn-xs kt-btn-primary" type="submit">Approve</button></form>
                        <form method="POST" action="{{ route('volunteers.hours.review', $hour->uuid) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Reject</button></form>
                    </div>@endif @endcan</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted-foreground py-6">Nothing here.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    {{ $hours->links() }}
</div>
@endsection
