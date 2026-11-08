@extends('layouts.metronic.app')

@section('title', 'Attendance')

@section('content')
@php
    $fmt = fn (int $minutes): string => intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
@endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Attendance</h1>
            <p class="text-xs text-muted-foreground">Clock in when you start and out when you finish. Your hours feed your weekly timesheet.</p>
        </div>
        <a href="{{ route('hrms-core.timesheets.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">My timesheet</a>
    </div>

    @if(session('success'))<div class="rounded-lg bg-success/10 p-3 text-sm text-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif

    @if($me === null)
        <div class="rounded-lg bg-muted/30 p-4 text-sm text-muted-foreground">Your account is not linked to an employee record, so you cannot clock in. Ask HR to link it.</div>
    @else
        <div class="kt-card">
            <div class="kt-card-content flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    @if($openSession)
                        <div class="text-sm text-muted-foreground">Clocked in since</div>
                        <div class="text-2xl font-semibold text-foreground">{{ $openSession->clock_in_at->format('H:i') }}</div>
                    @else
                        <div class="text-sm text-muted-foreground">You are not clocked in</div>
                    @endif
                    <div class="mt-1 text-xs text-muted-foreground">Today so far: {{ $fmt($todayMinutes) }}</div>
                </div>
                @if($openSession)
                    <form method="POST" action="{{ route('hrms-core.attendance.clock-out') }}">@csrf<button class="kt-btn kt-btn-primary" type="submit">Clock out</button></form>
                @else
                    <form method="POST" action="{{ route('hrms-core.attendance.clock-in') }}" class="flex gap-2">
                        @csrf
                        <input class="kt-input" name="note" maxlength="255" placeholder="Note (optional)">
                        <button class="kt-btn kt-btn-primary" type="submit">Clock in</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">My last two weeks</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead><tr><th>Date</th><th>Sessions</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    @forelse($recent as $date => $records)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($date)->format('D d M') }}</td>
                            <td>{{ $records->map(fn ($r) => $r->clock_in_at->format('H:i').'–'.($r->clock_out_at?->format('H:i') ?? 'now'))->implode(', ') }}</td>
                            <td class="text-end">{{ $fmt($records->sum(fn ($r) => $r->minutesWorked())) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted-foreground py-6">No attendance yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($canManage)
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title">Everyone on {{ $day->format('D d M Y') }}</h3>
                <form method="GET" class="flex gap-2"><input class="kt-input" type="date" name="date" value="{{ $day->toDateString() }}"><button class="kt-btn kt-btn-outline" type="submit">Go</button></form>
            </div>
            <div class="kt-card-table kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead><tr><th>Employee</th><th>In</th><th>Out</th><th>Source</th><th class="text-end">Worked</th></tr></thead>
                    <tbody>
                    @forelse($team as $record)
                        <tr>
                            <td>{{ $record->employee->first_name }} {{ $record->employee->last_name }}</td>
                            <td>{{ $record->clock_in_at->format('H:i') }}</td>
                            <td>{{ $record->clock_out_at?->format('H:i') ?? 'Still in' }}</td>
                            <td>{{ ucfirst($record->source) }}</td>
                            <td class="text-end">{{ $fmt($record->minutesWorked()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted-foreground py-6">Nobody clocked in on this day.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="{{ route('hrms-core.attendance.manual') }}" class="kt-card">
            @csrf
            <div class="kt-card-header"><h3 class="kt-card-title">Record attendance for someone</h3></div>
            <div class="kt-card-content grid grid-cols-1 md:grid-cols-5 gap-3">
                <select class="kt-select" name="employee_id" required>
                    <option value="">Employee</option>
                    @foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>@endforeach
                </select>
                <input class="kt-input" type="datetime-local" name="clock_in_at" value="{{ old('clock_in_at') }}" required>
                <input class="kt-input" type="datetime-local" name="clock_out_at" value="{{ old('clock_out_at') }}" required>
                <input class="kt-input" name="note" value="{{ old('note') }}" placeholder="Reason">
                <button class="kt-btn kt-btn-primary justify-center" type="submit">Record</button>
            </div>
        </form>
    @endif
</div>
@endsection
