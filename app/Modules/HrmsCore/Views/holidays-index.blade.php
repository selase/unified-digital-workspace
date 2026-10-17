@extends('layouts.metronic.app')

@section('title', 'Public Holidays Management')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Public Holidays Management"
        description="Configure institutional and statutory public holidays that are excluded from employee leave day deductions."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.leave-requests.index') }}" class="kt-btn kt-btn-outline">
                Leave Requests
            </a>
            <a href="{{ route('hrms-core.index') }}" class="kt-btn kt-btn-outline">
                HRMS Hub
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('message'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
            <svg class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Filter Toolbar --}}
    <x-metronic.card>
        <form method="GET" action="{{ route('hrms-core.holidays.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="text-xs text-muted-foreground block mb-1">Search Holiday</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Holiday name..." class="kt-input w-full text-sm">
            </div>
            <div>
                <label class="text-xs text-muted-foreground block mb-1">Year</label>
                <select name="year" class="kt-input w-full text-sm">
                    @for($y = (int) date('Y') + 1; $y >= (int) date('Y') - 3; $y--)
                        <option value="{{ $y }}" {{ (string) request('year', date('Y')) === (string) $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="kt-btn kt-btn-primary text-xs">Filter</button>
                <a href="{{ route('hrms-core.holidays.index') }}" class="kt-btn kt-btn-light text-xs">Reset</a>
            </div>
        </form>
    </x-metronic.card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Holidays Table (2 Cols) --}}
        <div class="lg:col-span-2">
            <x-metronic.card title="Recognized Holidays" subtitle="Public and organizational non-working holidays">
                @if($holidays->count())
                    <div class="overflow-x-auto rounded-xl border border-border">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted/40 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="py-2.5 px-4 font-semibold">Holiday Name</th>
                                    <th class="py-2.5 px-4 font-semibold">Date</th>
                                    <th class="py-2.5 px-4 font-semibold">Cadence</th>
                                    <th class="py-2.5 px-4 font-semibold">Status</th>
                                    <th class="py-2.5 px-4 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($holidays as $holiday)
                                    <tr x-data="{ editing: false }">
                                        <td colspan="5" class="p-0">
                                            <div class="flex items-center justify-between py-2.5 px-4" x-show="!editing">
                                                <div class="flex items-center gap-4">
                                                    <div class="w-48">
                                                        <span class="font-medium text-foreground block">{{ $holiday->name }}</span>
                                                        @if($holiday->description)
                                                            <span class="text-xs text-muted-foreground block truncate max-w-xs">{{ $holiday->description }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="w-32">
                                                        <span class="font-mono text-xs text-foreground block">{{ $holiday->date->format('d/m/Y') }}</span>
                                                        <span class="text-xs text-muted-foreground block">{{ $holiday->date->format('l') }}</span>
                                                    </div>
                                                    <div class="w-24">
                                                        @if($holiday->is_recurring)
                                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-primary/10 text-primary">Annual</span>
                                                        @else
                                                            <span class="text-xs text-muted-foreground">Fixed</span>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        @if($holiday->is_active)
                                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-500/10 text-emerald-600">Active</span>
                                                        @else
                                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-muted text-muted-foreground">Inactive</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="text-right whitespace-nowrap">
                                                    @can('hrms.leave.manage_holidays')
                                                        <button type="button" @click="editing = true" class="text-primary hover:text-primary-active text-xs font-medium mr-2">Edit</button>
                                                        <form action="{{ route('hrms-core.holidays.destroy', $holiday) }}" method="POST" onsubmit="return confirm('Remove public holiday?');" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-medium">Remove</button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </div>
                                            <div x-show="editing" x-cloak class="p-4 bg-muted/20 border-t border-border">
                                                <form action="{{ route('hrms-core.holidays.update', $holiday) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    @csrf
                                                    @method('PUT')
                                                    <div>
                                                        <label class="text-xs text-muted-foreground block mb-1">Holiday Name *</label>
                                                        <input type="text" name="name" required value="{{ $holiday->name }}" class="kt-input w-full text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="text-xs text-muted-foreground block mb-1">Date *</label>
                                                        <input type="date" name="date" required value="{{ $holiday->date->format('Y-m-d') }}" class="kt-input w-full text-sm">
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label class="text-xs text-muted-foreground block mb-1">Description</label>
                                                        <input type="text" name="description" value="{{ $holiday->description }}" class="kt-input w-full text-sm">
                                                    </div>
                                                    <div class="flex items-center gap-4 sm:col-span-2">
                                                        <label class="flex items-center gap-1.5 text-xs text-foreground cursor-pointer">
                                                            <input type="checkbox" name="is_recurring" value="1" {{ $holiday->is_recurring ? 'checked' : '' }} class="rounded border-border text-primary focus:ring-primary">
                                                            Repeats Annually
                                                        </label>
                                                        <label class="flex items-center gap-1.5 text-xs text-foreground cursor-pointer">
                                                            <input type="checkbox" name="is_active" value="1" {{ $holiday->is_active ? 'checked' : '' }} class="rounded border-border text-primary focus:ring-primary">
                                                            Active
                                                        </label>
                                                    </div>
                                                    <div class="sm:col-span-2 flex justify-end gap-2">
                                                        <button type="button" @click="editing = false" class="kt-btn kt-btn-light text-xs">Cancel</button>
                                                        <button type="submit" class="kt-btn kt-btn-primary text-xs">Save</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="pt-4">
                        {{ $holidays->links() }}
                    </div>
                @else
                    <p class="text-sm text-muted-foreground italic py-6 text-center">No recognized public holidays recorded for this filter.</p>
                @endif
            </x-metronic.card>
        </div>

        {{-- Add Holiday Form (1 Col) --}}
        <div>
            @can('hrms.leave.manage_holidays')
                <x-metronic.card title="Add Public Holiday" subtitle="Register a statutory or organizational holiday">
                    <form action="{{ route('hrms-core.holidays.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="text-xs text-muted-foreground block mb-1">Holiday Name *</label>
                            <input type="text" name="name" required placeholder="e.g. Independence Day" class="kt-input w-full text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground block mb-1">Holiday Date *</label>
                            <input type="date" name="date" required value="{{ date('Y-m-d') }}" class="kt-input w-full text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground block mb-1">Description</label>
                            <textarea name="description" rows="2" placeholder="Official description..." class="kt-input w-full text-sm"></textarea>
                        </div>
                        <div class="space-y-2 pt-2">
                            <label class="flex items-center gap-2 text-xs text-foreground cursor-pointer">
                                <input type="checkbox" name="is_recurring" value="1" checked class="rounded border-border text-primary focus:ring-primary">
                                Repeats Annually on this Month &amp; Day
                            </label>
                            <label class="flex items-center gap-2 text-xs text-foreground cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" checked class="rounded border-border text-primary focus:ring-primary">
                                Active (Exclude from Leave Deductions)
                            </label>
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="kt-btn kt-btn-primary w-full text-xs">Create Holiday</button>
                        </div>
                    </form>
                </x-metronic.card>
            @endcan
        </div>
    </div>
</div>
@endsection

