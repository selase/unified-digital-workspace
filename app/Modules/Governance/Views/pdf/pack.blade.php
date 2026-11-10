<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meeting->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        h2 { font-size: 14px; margin: 22px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        .pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="muted">{{ $organisation }}</div>
    <h1>{{ $meeting->title }}</h1>
    <div class="muted">{{ $meeting->body }} · {{ $meeting->meets_at->format('j F Y, H:i') }}{{ $meeting->venue ? ' · '.$meeting->venue : '' }}</div>

    <h2>Agenda</h2>
    <div class="pre">{{ $meeting->agenda ?: 'No agenda recorded.' }}</div>

    <h2>Attendance</h2>
    <table>
        @forelse($attendance as $member)<tr><td>{{ $member->name }}</td><td>{{ $member->position }}</td><td>{{ ucfirst($member->pivot->status) }}</td></tr>@empty<tr><td>Not recorded.</td></tr>@endforelse
    </table>
    <div class="muted" style="margin-top:4px;">{{ $present }} present{{ $meeting->quorum ? '; quorum of '.$meeting->quorum.' '.($quorumMet ? 'met' : 'not met') : '' }}.</div>

    @if($meeting->status === 'held')
        <h2>Minutes {{ $meeting->minutes_status === 'approved' ? '(approved '.$meeting->minutes_approved_on->format('j F Y').')' : '(draft, not yet approved)' }}</h2>
        <div class="pre">{{ $meeting->minutes ?: 'Not yet written.' }}</div>

        <h2>Resolutions</h2>
        <table>@forelse($resolutions as $resolution)<tr><td>{{ $resolution->reference }}</td><td>{{ $resolution->text }}</td><td>{{ ucfirst($resolution->outcome) }}</td></tr>@empty<tr><td>None.</td></tr>@endforelse</table>

        <h2>Actions</h2>
        <table>@forelse($actions as $action)<tr><td>{{ $action->description }}</td><td>{{ $action->owner_name }}</td><td>{{ $action->due_on?->format('j M Y') }}</td><td>{{ ucfirst($action->status) }}</td></tr>@empty<tr><td>None.</td></tr>@endforelse</table>
    @endif
</body>
</html>
