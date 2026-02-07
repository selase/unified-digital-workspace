@php
    /** @var \App\Mail\Incidents\IncidentEscalated $mailable */
    $incident = $mailable->incident;
@endphp

<h2>Incident Escalated</h2>

<p>Incident <strong>{{ $incident->reference_code }}</strong> has been escalated.</p>

<ul>
    <li><strong>Title:</strong> {{ $incident->title }}</li>
    <li><strong>Description:</strong> {{ $incident->description }}</li>
    <li><strong>New Priority:</strong> {{ optional($incident->priority)->name ?? 'N/A' }}</li>
</ul>
