@extends('layouts.metronic.app')

@section('title', 'Edit Incident')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Incident Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Edit Incident {{ $incident->reference_code }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Adjust ownership, classification, and response timeline details.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.incidents.show', $incident) }}">View Incident</a>
                    <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.incidents.index') }}">Back to Register</a>
                </div>
            </div>
        </div>

        <div class="kt-card p-6">
            <form action="{{ route('incident-management.incidents.update', $incident) }}" class="kt-form" method="POST">
                @csrf
                @method('PUT')
                @include('incident-management::partials.incident-form', ['incident' => $incident])

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.incidents.show', $incident) }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </section>
@endsection
