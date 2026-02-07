@php
    /** @var \App\Mail\Incidents\IncidentAssigned $mailable */
    $incident = $mailable->incident;
@endphp

<h2>New Incident Assignment</h2>

<p>You have been assigned to incident <strong>{{ $incident->reference_code }}</strong>.</p>

<ul>
    <li><strong>Title:</strong> {{ $incident->title }}</li>
    <li><strong>Description:</strong> {{ $incident->description }}</li>
    <li><strong>Priority:</strong> {{ optional($incident->priority)->name ?? 'N/A' }}</li>
</ul>
