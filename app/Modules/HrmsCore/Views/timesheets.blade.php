@extends('layouts.metronic.app')

@section('title', 'Timesheets')

@section('content')
@php
    $fmt = fn (int $minutes): string => intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    $rows = $days->map(function ($day) use ($entriesByDay, $clockedMinutes) {
        $entry = ($entriesByDay[$day->toDateString()] ?? collect())->first();
        $clocked = (int) ($clockedMinutes[$day->toDateString()] ?? 0);
        return [
            'work_date' => $day->toDateString(),
            'label' => $day->format('D d M'),
            'hours' => $entry ? round($entry->minutes / 60, 2) : '',
            'project' => $entry?->project ?? '',
            'description' => $entry?->description ?? '',
            'clocked' => $clocked,
        ];
    })->values();
@endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Timesheet</h1>
            <p class="text-xs text-muted-foreground">Week of {{ $weekStart->format('d M Y') }}. Hours you clocked are shown as a guide.</p>
        </div>
        <div class="flex items-center gap-2">
            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('hrms-core.timesheets.index', ['week' => $weekStart->copy()->subWeek()->toDateString()]) }}">Previous week</a>
            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('hrms-core.timesheets.index', ['week' => $weekStart->copy()->addWeek()->toDateString()]) }}">Next week</a>
            <a href="{{ route('hrms-core.attendance.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Attendance</a>
        </div>
    </div>

    @if(session('success'))<div class="rounded-lg bg-success/10 p-3 text-sm text-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif

    @if($me === null)
        <div class="rounded-lg bg-muted/30 p-4 text-sm text-muted-foreground">Your account is not linked to an employee record, so you have no timesheet. Ask HR to link it.</div>
    @else
        <form method="POST" action="{{ route('hrms-core.timesheets.save') }}" class="kt-card">
            @csrf
            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
            <div class="kt-card-header">
                <h3 class="kt-card-title">My hours · {{ ucfirst($sheet->status) }} · {{ $fmt($sheet->total_minutes) }}</h3>
            </div>
            @if($sheet->review_comment)
                <div class="px-5 pb-2 text-sm {{ $sheet->status === 'rejected' ? 'text-destructive' : 'text-muted-foreground' }}">Reviewer: {{ $sheet->review_comment }}</div>
            @endif
            <div class="kt-card-content flex flex-col gap-2">
                @foreach($rows as $i => $row)
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center">
                        <input type="hidden" name="entries[{{ $i }}][work_date]" value="{{ $row['work_date'] }}">
                        <div class="md:col-span-2 text-sm font-medium">{{ $row['label'] }}</div>
                        <input class="kt-input md:col-span-2" type="number" step="0.25" min="0" max="24" name="entries[{{ $i }}][hours]" value="{{ old("entries.$i.hours", $row['hours']) }}" placeholder="Hours" @disabled(! $sheet->isEditable())>
                        <input class="kt-input md:col-span-3" name="entries[{{ $i }}][project]" value="{{ old("entries.$i.project", $row['project']) }}" placeholder="Project" @disabled(! $sheet->isEditable())>
                        <input class="kt-input md:col-span-3" name="entries[{{ $i }}][description]" value="{{ old("entries.$i.description", $row['description']) }}" placeholder="What you worked on" @disabled(! $sheet->isEditable())>
                        <div class="md:col-span-2 text-xs text-muted-foreground">{{ $row['clocked'] ? 'Clocked '.$fmt($row['clocked']) : '' }}</div>
                    </div>
                @endforeach
                @if($sheet->isEditable())
                    <div class="flex gap-2 pt-2">
                        <button class="kt-btn kt-btn-outline" type="submit">Save draft</button>
                        <button class="kt-btn kt-btn-primary" type="submit" name="submit" value="1">Save and submit</button>
                    </div>
                @endif
            </div>
        </form>
    @endif

    @if($canApprove)
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Waiting for your approval</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead><tr><th>Employee</th><th>Week of</th><th class="text-end">Hours</th><th></th></tr></thead>
                    <tbody>
                    @forelse($pending as $item)
                        <tr>
                            <td>{{ $item->employee->first_name }} {{ $item->employee->last_name }}</td>
                            <td>{{ $item->week_start->format('d M Y') }}</td>
                            <td class="text-end">{{ $fmt($item->total_minutes) }}</td>
                            <td>
                                <form method="POST" action="{{ route('hrms-core.timesheets.review', $item->uuid) }}" class="flex gap-2 justify-end">
                                    @csrf
                                    <input class="kt-input" name="comment" placeholder="Comment (needed to return)">
                                    <button class="kt-btn kt-btn-sm kt-btn-primary" name="decision" value="approve" type="submit">Approve</button>
                                    <button class="kt-btn kt-btn-sm kt-btn-outline" name="decision" value="reject" type="submit">Return</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted-foreground py-6">Nothing waiting.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
