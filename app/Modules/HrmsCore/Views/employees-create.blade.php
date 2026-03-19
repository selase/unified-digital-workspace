@extends('layouts.metronic.app')

@section('title', 'Create Employee')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Create Employee Profile</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Register a new staff member and map them to grade and center assignments.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.employees.index') }}">Back to Directory</a>
            </div>
        </div>

        <div class="kt-card p-6">
            <form action="{{ route('hrms-core.employees.store') }}" class="kt-form" method="POST">
                @csrf
                @include('hrms-core::partials.employee-form', ['employee' => null, 'grades' => $grades, 'centers' => $centers])

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.employees.index') }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">Create Employee</button>
                </div>
            </form>
        </div>
    </section>
@endsection
