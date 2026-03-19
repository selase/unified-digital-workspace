@extends('layouts.metronic.app')

@section('title', 'Edit Employee')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Edit Employee Profile</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Update core identity, contact records, and organizational placement.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.employees.show', $employee) }}">View Profile</a>
                    <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.employees.index') }}">Back to Directory</a>
                </div>
            </div>
        </div>

        <div class="kt-card p-6">
            <form action="{{ route('hrms-core.employees.update', $employee) }}" class="kt-form" method="POST">
                @csrf
                @method('PUT')
                @include('hrms-core::partials.employee-form', ['employee' => $employee, 'grades' => $grades, 'centers' => $centers])

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.employees.show', $employee) }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </section>
@endsection
