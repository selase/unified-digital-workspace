@extends('layouts.metronic.app')

@section('title', 'Create Employee')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Create Employee Profile"
        description="Register a new institutional staff member, configure identification, and assign grade and center placements."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline">
                Back to Directory
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    <x-metronic.card title="Employee Details" subtitle="Primary personnel enrollment form">
        <form action="{{ route('hrms-core.employees.store') }}" class="kt-form" method="POST">
            @csrf
            @include('hrms-core::partials.employee-form', ['employee' => null, 'grades' => $grades, 'centers' => $centers])

            <div class="mt-8 pt-5 border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline">Cancel</a>
                <button type="submit" class="kt-btn kt-btn-primary">Create Employee Profile</button>
            </div>
        </form>
    </x-metronic.card>
</div>
@endsection
