@extends('layouts.metronic.app')

@section('title', 'Create Incident')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Incident Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Log New Incident</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Capture operational issues, assign owners, and trigger SLA monitoring.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.incidents.index') }}">Back to Register</a>
            </div>
        </div>

        <div class="kt-card p-6">
            <form action="{{ route('incident-management.incidents.store') }}" class="kt-form" method="POST">
                @csrf
                @include('incident-management::partials.incident-form', ['incident' => null])

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.incidents.index') }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">Create Incident</button>
                </div>
            </form>
        </div>
    </section>
@endsection
