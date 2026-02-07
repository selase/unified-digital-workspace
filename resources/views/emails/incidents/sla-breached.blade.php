@php
    /** @var \App\Mail\Incidents\IncidentSlaBreached $mailable */
    $incident = $mailable->incident;
@endphp

<h2>Incident SLA Breached</h2>

<p>The SLA for incident <strong>{{ $incident->reference_code }}</strong> has been breached.</p>

<p>
    <strong>Title:</strong> {{ $incident->title }}<br>
    <strong>Description:</strong> {{ $incident->description }}<br>
    <strong>Priority:</strong> {{ optional($incident->priority)->name ?? 'N/A' }}<br>
    <strong>Reported:</strong> {{ $incident->created_at?->toDayDateTimeString() }}
</p>

<p>Please review and take corrective action.</p>
