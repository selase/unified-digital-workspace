@php
    /** @var \App\Mail\Incidents\IncidentReminder $mailable */
    $incident = $mailable->incident;
    $reminder = $mailable->reminder;
@endphp

<h2>Incident Reminder</h2>

<p>This is a reminder for incident <strong>{{ $incident->reference_code }}</strong>.</p>

<ul>
    <li><strong>Type:</strong> {{ $reminder->reminder_type }}</li>
    <li><strong>Title:</strong> {{ $incident->title }}</li>
    <li><strong>Description:</strong> {{ $incident->description }}</li>
    <li><strong>Due:</strong> {{ $incident->due_at?->toDayDateTimeString() ?? 'N/A' }}</li>
</ul>
