@extends('layouts.metronic.app')

@section('title', 'Edit Employee - ' . $employee->fullName())

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Edit Employee Profile"
        description="Update profile records for {{ $employee->fullName() }} ({{ $employee->employee_staff_id ?: 'No Staff ID' }})."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.employees.show', $employee) }}" class="kt-btn kt-btn-outline">
                View Profile
            </a>
            <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline">
                Back to Directory
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    <x-metronic.card title="Employee Details" subtitle="Edit personnel enrollment attributes">
        <form action="{{ route('hrms-core.employees.update', $employee) }}" class="kt-form" method="POST">
            @csrf
            @method('PUT')
            @include('hrms-core::partials.employee-form', ['employee' => $employee, 'grades' => $grades, 'centers' => $centers])

            <div class="mt-8 pt-5 border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('hrms-core.employees.show', $employee) }}" class="kt-btn kt-btn-outline">Cancel</a>
                <button type="submit" class="kt-btn kt-btn-primary">Save Changes</button>
            </div>
        </form>
    </x-metronic.card>
</div>
@endsection
