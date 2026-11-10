@extends('layouts.metronic.app')

@section('title', $volunteer->name)

@section('content')
@php use App\Modules\Volunteers\Models\VolVolunteer; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div><h1 class="text-xl font-bold text-foreground">{{ $volunteer->name }}</h1><p class="text-xs text-muted-foreground">{{ VolVolunteer::STATUSES[$volunteer->status] }} · {{ $volunteer->email ?: 'no email' }} · {{ $volunteer->phone }}</p></div>
        <div class="flex gap-2">
            @if($approvedMinutes > 0)<a class="kt-btn kt-btn-outline" href="{{ route('volunteers.volunteers.certificate', $volunteer->uuid) }}">Certificate</a>@endif
        @can('volunteers.manage')
            @if($volunteer->status !== 'active')<form method="POST" action="{{ route('volunteers.volunteers.status', $volunteer->uuid) }}">@csrf<input type="hidden" name="status" value="active"><button class="kt-btn kt-btn-primary" type="submit">{{ $volunteer->status === 'pending' ? 'Approve' : 'Reactivate' }}</button></form>@endif
            @if($volunteer->status === 'active')<form method="POST" action="{{ route('volunteers.volunteers.status', $volunteer->uuid) }}">@csrf<input type="hidden" name="status" value="inactive"><button class="kt-btn kt-btn-outline" type="submit">Make inactive</button></form>@endif
        @endcan
        </div>
    </div>

    @include('volunteers::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Approved hours</div><div class="mt-1 text-lg font-semibold">{{ number_format($approvedMinutes / 60, 1) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Since</div><div class="mt-1 text-lg font-semibold">{{ $volunteer->joined_on?->toInstitutionalDate() ?? '—' }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Skills</div><div class="mt-1 text-sm">{{ $volunteer->skills ?: '—' }}</div></div>
    </div>

    @can('volunteers.manage')
    @if($volunteer->status === 'active')
    <form method="POST" action="{{ route('volunteers.volunteers.signup', $volunteer->uuid) }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Sign up for an opportunity</h3></div>
        <div class="kt-card-content flex gap-2"><select class="kt-select w-80" name="opportunity_id" required><option value="">Choose…</option>@foreach($opportunities as $op)<option value="{{ $op->id }}">{{ $op->starts_on->toInstitutionalDate() }} · {{ $op->title }}</option>@endforeach</select><button class="kt-btn kt-btn-outline" type="submit">Sign up</button></div>
    </form>
    @endif
    @endcan

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Hours</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Date</th><th>Activity</th><th>Status</th><th class="text-end">Hours</th></tr></thead>
            <tbody>
            @forelse($hours as $hour)
                <tr><td>{{ $hour->worked_on->toInstitutionalDate() }}</td><td>{{ $hour->activity }} {{ $hour->opportunity ? '('.$hour->opportunity->title.')' : '' }}</td><td>{{ ucfirst($hour->status) }}</td><td class="text-end">{{ number_format($hour->hoursDecimal(), 2) }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted-foreground py-6">No hours logged yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
