@extends('layouts.metronic.app')

@section('title', 'Employee Profile - ' . $employee->fullName())

@section('content')
<div class="flex flex-col gap-6" x-data="{ activeTab: window.location.hash ? window.location.hash.replace('#tab_', '') : 'overview' }">
    <x-metronic.page-header
        title="{{ $employee->fullName() }}"
        description="Staff record {{ $employee->employee_staff_id ?: 'unassigned' }} &bull; {{ $employee->grade?->name ?: 'General Staff' }}"
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline">
                Back to Directory
            </a>
            @can('hrms.leaves.create')
                <a href="{{ route('hrms-core.leave-requests.create') }}" class="kt-btn kt-btn-outline flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Apply Leave
                </a>
            @endcan
            @can('hrms.employees.update')
                <a href="{{ route('hrms-core.employees.edit', $employee) }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Profile
                </a>
            @endcan
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

    @if($errors->any())
        <div class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-rose-800 dark:text-rose-300 text-sm">
            <div class="font-semibold mb-1">Please correct the errors below:</div>
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Dossier Tab Navigation --}}
    <div class="border-b border-border">
        <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Employee Dossier Tabs">
            <button
                type="button"
                @click="activeTab = 'overview'; window.location.hash = 'tab_overview'"
                :class="activeTab === 'overview' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                class="whitespace-nowrap py-3 px-4 border-b-2 text-sm font-medium transition-colors"
            >
                Overview
            </button>
            <button
                type="button"
                @click="activeTab = 'banking'; window.location.hash = 'tab_banking'"
                :class="activeTab === 'banking' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                class="whitespace-nowrap py-3 px-4 border-b-2 text-sm font-medium transition-colors"
            >
                Banking Details
            </button>
            <button
                type="button"
                @click="activeTab = 'family'; window.location.hash = 'tab_family'"
                :class="activeTab === 'family' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                class="whitespace-nowrap py-3 px-4 border-b-2 text-sm font-medium transition-colors"
            >
                Family &amp; Next of Kin
            </button>
            <button
                type="button"
                @click="activeTab = 'emergency'; window.location.hash = 'tab_emergency'"
                :class="activeTab === 'emergency' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                class="whitespace-nowrap py-3 px-4 border-b-2 text-sm font-medium transition-colors"
            >
                Emergency Contacts
                @if($employee->emergencyContacts->count())
                    <span class="ml-1.5 py-0.5 px-2 text-xs rounded-full bg-muted font-normal text-muted-foreground">{{ $employee->emergencyContacts->count() }}</span>
                @endif
            </button>
            <button
                type="button"
                @click="activeTab = 'education'; window.location.hash = 'tab_education'"
                :class="activeTab === 'education' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                class="whitespace-nowrap py-3 px-4 border-b-2 text-sm font-medium transition-colors"
            >
                Education &amp; Qualifications
            </button>
            <button
                type="button"
                @click="activeTab = 'experience'; window.location.hash = 'tab_experience'"
                :class="activeTab === 'experience' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                class="whitespace-nowrap py-3 px-4 border-b-2 text-sm font-medium transition-colors"
            >
                Work Experience
                @if($employee->previousWorkExperiences->count())
                    <span class="ml-1.5 py-0.5 px-2 text-xs rounded-full bg-muted font-normal text-muted-foreground">{{ $employee->previousWorkExperiences->count() }}</span>
                @endif
            </button>
            <button
                type="button"
                @click="activeTab = 'status'; window.location.hash = 'tab_status'"
                :class="activeTab === 'status' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                class="whitespace-nowrap py-3 px-4 border-b-2 text-sm font-medium transition-colors"
            >
                Employment Status
            </button>
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Dossier Tab Content (8 cols) --}}
        <div class="lg:col-span-8 flex flex-col gap-6">

            {{-- 1. OVERVIEW TAB --}}
            <div x-show="activeTab === 'overview'" class="flex flex-col gap-6">
                <x-metronic.card title="Personal & Professional Information" subtitle="Official identity and contact records">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                            <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Full Name</span>
                            <span class="mt-1 font-semibold text-foreground text-base">{{ $employee->fullName() }}</span>
                            @if($employee->title)
                                <span class="text-xs text-muted-foreground">{{ $employee->title }}</span>
                            @endif
                        </div>

                        <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                            <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Staff ID Number</span>
                            <div class="mt-1 flex items-center gap-2">
                                @if($employee->employee_staff_id)
                                    <span class="font-mono text-sm px-2.5 py-1 rounded-md bg-muted text-foreground border border-border font-semibold">
                                        {{ $employee->employee_staff_id }}
                                    </span>
                                @else
                                    <span class="text-sm text-muted-foreground italic">Not Assigned</span>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                            <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Email Address</span>
                            <span class="mt-1 text-sm text-foreground font-medium">
                                {{ $employee->email ?: 'Not provided' }}
                            </span>
                        </div>

                        <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                            <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Mobile Contact</span>
                            <span class="mt-1 text-sm text-foreground font-medium">
                                {{ $employee->mobile ?: 'Not provided' }}
                            </span>
                        </div>

                        <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                            <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Job Grade</span>
                            <span class="mt-1 text-sm font-semibold text-foreground">
                                {{ $employee->grade?->name ?: 'Not Mapped' }}
                            </span>
                        </div>

                        <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                            <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Center Location</span>
                            <span class="mt-1 text-sm font-semibold text-foreground">
                                {{ $employee->center?->name ?: 'Main Campus' }}
                            </span>
                        </div>
                    </div>
                </x-metronic.card>

                <x-metronic.card title="Organizational Hierarchy & Structure" subtitle="Reporting units, directorates, and departments">
                    <div class="space-y-5">
                        <div>
                            <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground block mb-2">Departments</span>
                            <div class="flex flex-wrap gap-2">
                                @forelse($employee->departments as $department)
                                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium bg-primary/10 text-primary border border-primary/20">
                                        <svg class="h-3.5 w-3.5 mr-1.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        {{ $department->name }}
                                    </span>
                                @empty
                                    <span class="text-sm text-muted-foreground italic">No primary department assigned.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="pt-4 border-t border-border">
                            <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground block mb-2">Directorates</span>
                            <div class="flex flex-wrap gap-2">
                                @forelse($employee->directorates as $directorate)
                                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium bg-secondary text-secondary-foreground border border-border">
                                        {{ $directorate->name }}
                                    </span>
                                @empty
                                    <span class="text-sm text-muted-foreground italic">No directorate hierarchy mapped.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="pt-4 border-t border-border">
                            <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground block mb-2">Operational Units</span>
                            <div class="flex flex-wrap gap-2">
                                @forelse($employee->units as $unit)
                                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium bg-muted text-foreground border border-border">
                                        {{ $unit->name }}
                                    </span>
                                @empty
                                    <span class="text-sm text-muted-foreground italic">No operational unit assigned.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </x-metronic.card>
            </div>

            {{-- 2. BANKING DETAILS TAB --}}
            <div x-show="activeTab === 'banking'" class="flex flex-col gap-6" style="display: none;">
                <x-metronic.card title="Bank & Salary Disbursement Details" subtitle="Primary bank account records on file for salary and allowance reference">
                    <form action="{{ route('hrms-core.employees.dossier.bank-details.update', $employee) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Bank Name *</label>
                                <input type="text" name="bank_name" required value="{{ old('bank_name', $employee->bankDetails?->bank_name) }}" class="kt-input w-full text-sm" placeholder="e.g. Ghana Commercial Bank">
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Branch Name</label>
                                <input type="text" name="branch_name" value="{{ old('branch_name', $employee->bankDetails?->branch_name) }}" class="kt-input w-full text-sm" placeholder="e.g. Legon Branch">
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Account Number *</label>
                                <input type="text" name="account_number" required value="{{ old('account_number', $employee->bankDetails?->account_number) }}" class="kt-input w-full text-sm" placeholder="e.g. 1029384756">
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Account Type</label>
                                <input type="text" name="account_type" value="{{ old('account_type', $employee->bankDetails?->account_type) }}" class="kt-input w-full text-sm" placeholder="e.g. Savings or Current">
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Sort Code / Swift</label>
                                <input type="text" name="sort_code" value="{{ old('sort_code', $employee->bankDetails?->sort_code) }}" class="kt-input w-full text-sm" placeholder="e.g. GCBGHAC">
                            </div>
                        </div>
                        @can('hrms.employees.update')
                            <div class="flex justify-end pt-3 border-t border-border">
                                <button type="submit" class="kt-btn kt-btn-primary text-xs">Save Bank Details</button>
                            </div>
                        @endcan
                    </form>
                </x-metronic.card>
            </div>

            {{-- 3. FAMILY & NEXT OF KIN TAB --}}
            <div x-show="activeTab === 'family'" class="flex flex-col gap-6" style="display: none;">
                {{-- Next of Kin --}}
                <x-metronic.card title="Next of Kin" subtitle="Designated official legal contact">
                    <form action="{{ route('hrms-core.employees.dossier.next-of-kin.update', $employee) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Full Name *</label>
                                <input type="text" name="name" required value="{{ old('name', $employee->nextOfKin?->name) }}" class="kt-input w-full text-sm">
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Relationship</label>
                                <input type="text" name="relationship" value="{{ old('relationship', $employee->nextOfKin?->relationship) }}" class="kt-input w-full text-sm" placeholder="e.g. Spouse, Sibling">
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Telephone</label>
                                <input type="text" name="phone" value="{{ old('phone', $employee->nextOfKin?->phone) }}" class="kt-input w-full text-sm" placeholder="+233...">
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Email Address</label>
                                <input type="email" name="email" value="{{ old('email', $employee->nextOfKin?->email) }}" class="kt-input w-full text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Residential Address</label>
                                <input type="text" name="address" value="{{ old('address', $employee->nextOfKin?->address) }}" class="kt-input w-full text-sm">
                            </div>
                        </div>
                        @can('hrms.employees.update')
                            <div class="flex justify-end pt-3 border-t border-border">
                                <button type="submit" class="kt-btn kt-btn-primary text-xs">Save Next of Kin</button>
                            </div>
                        @endcan
                    </form>
                </x-metronic.card>

                {{-- Parents --}}
                <x-metronic.card title="Parental Information" subtitle="Family background and parental status">
                    <form action="{{ route('hrms-core.employees.dossier.parents.update', $employee) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Father's Full Name</label>
                                <input type="text" name="father_name" value="{{ old('father_name', $employee->parent?->father_name) }}" class="kt-input w-full text-sm">
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Father's Occupation</label>
                                <input type="text" name="father_occupation" value="{{ old('father_occupation', $employee->parent?->father_occupation) }}" class="kt-input w-full text-sm">
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="father_alive" value="1" id="father_alive" {{ old('father_alive', $employee->parent?->father_alive ?? true) ? 'checked' : '' }} class="rounded border-border text-primary focus:ring-primary">
                                <label for="father_alive" class="text-sm text-foreground">Father is Alive</label>
                            </div>
                            <div class="border-t md:border-t-0 md:border-l border-border pt-3 md:pt-0 md:pl-4"></div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Mother's Full Name</label>
                                <input type="text" name="mother_name" value="{{ old('mother_name', $employee->parent?->mother_name) }}" class="kt-input w-full text-sm">
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-muted-foreground block mb-1">Mother's Occupation</label>
                                <input type="text" name="mother_occupation" value="{{ old('mother_occupation', $employee->parent?->mother_occupation) }}" class="kt-input w-full text-sm">
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="mother_alive" value="1" id="mother_alive" {{ old('mother_alive', $employee->parent?->mother_alive ?? true) ? 'checked' : '' }} class="rounded border-border text-primary focus:ring-primary">
                                <label for="mother_alive" class="text-sm text-foreground">Mother is Alive</label>
                            </div>
                        </div>
                        @can('hrms.employees.update')
                            <div class="flex justify-end pt-3 border-t border-border">
                                <button type="submit" class="kt-btn kt-btn-primary text-xs">Save Parental Details</button>
                            </div>
                        @endcan
                    </form>
                </x-metronic.card>

                {{-- Children --}}
                <x-metronic.card title="Children & Dependents" subtitle="Registered dependents for benefit and allowance claims">
                    <div class="space-y-4">
                        @if($employee->children->count())
                            <div class="overflow-x-auto rounded-xl border border-border">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-muted/40 text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th class="py-2.5 px-4 font-semibold">Child Name</th>
                                            <th class="py-2.5 px-4 font-semibold">Date of Birth</th>
                                            <th class="py-2.5 px-4 font-semibold">Gender</th>
                                            <th class="py-2.5 px-4 text-right font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach($employee->children as $child)
                                            <tr>
                                                <td class="py-2.5 px-4 font-medium text-foreground">{{ $child->name }}</td>
                                                <td class="py-2.5 px-4 text-muted-foreground">{{ $child->date_of_birth?->format('d/m/Y') ?: '—' }}</td>
                                                <td class="py-2.5 px-4 capitalize text-muted-foreground">{{ $child->gender?->value ?? $child->gender ?? '—' }}</td>
                                                <td class="py-2.5 px-4 text-right">
                                                    @can('hrms.employees.update')
                                                        <form action="{{ route('hrms-core.employees.dossier.children.destroy', [$employee, $child]) }}" method="POST" onsubmit="return confirm('Remove child record?');" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-medium">Remove</button>
                                                        </form>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-muted-foreground italic">No children or dependents registered.</p>
                        @endif

                        @can('hrms.employees.update')
                            <div class="pt-3 border-t border-border">
                                <h4 class="text-xs uppercase font-semibold text-muted-foreground mb-3">Add Child Record</h4>
                                <form action="{{ route('hrms-core.employees.dossier.children.store', $employee) }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                                    @csrf
                                    <div class="md:col-span-2">
                                        <label class="text-xs text-muted-foreground block mb-1">Child's Name *</label>
                                        <input type="text" name="name" required class="kt-input w-full text-sm" placeholder="Full name">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Date of Birth</label>
                                        <input type="date" name="date_of_birth" class="kt-input w-full text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Gender</label>
                                        <select name="gender" class="kt-input w-full text-sm">
                                            <option value="">Select...</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-4 flex justify-end">
                                        <button type="submit" class="kt-btn kt-btn-outline text-xs">Add Child</button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                </x-metronic.card>
            </div>

            {{-- 4. EMERGENCY CONTACTS TAB --}}
            <div x-show="activeTab === 'emergency'" class="flex flex-col gap-6" style="display: none;">
                <x-metronic.card title="Emergency Contacts" subtitle="Authorized individuals to reach during workplace incidents">
                    <div class="space-y-4">
                        @if($employee->emergencyContacts->count())
                            <div class="overflow-x-auto rounded-xl border border-border">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-muted/40 text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th class="py-2.5 px-4 font-semibold">Contact Name</th>
                                            <th class="py-2.5 px-4 font-semibold">Relationship</th>
                                            <th class="py-2.5 px-4 font-semibold">Phone</th>
                                            <th class="py-2.5 px-4 font-semibold">Primary</th>
                                            <th class="py-2.5 px-4 text-right font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach($employee->emergencyContacts as $contact)
                                            <tr x-data="{ editing: false }">
                                                <td colspan="5" class="p-0">
                                                    <div class="flex items-center justify-between py-2.5 px-4" x-show="!editing">
                                                        <div class="flex items-center gap-6">
                                                            <div class="w-44">
                                                                <span class="font-medium text-foreground block">{{ $contact->name }}</span>
                                                                @if($contact->email)
                                                                    <span class="block text-xs text-muted-foreground">{{ $contact->email }}</span>
                                                                @endif
                                                            </div>
                                                            <span class="text-muted-foreground w-28">{{ $contact->relationship ?: '—' }}</span>
                                                            <span class="font-mono text-xs w-32">{{ $contact->phone }}</span>
                                                            <div>
                                                                @if($contact->is_primary)
                                                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">Primary</span>
                                                                @else
                                                                    <span class="text-xs text-muted-foreground">Secondary</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="text-right whitespace-nowrap">
                                                            @can('hrms.employees.update')
                                                                <button type="button" @click="editing = true" class="text-primary hover:text-primary-active text-xs font-medium mr-2">Edit</button>
                                                                <form action="{{ route('hrms-core.employees.dossier.emergency-contacts.destroy', [$employee, $contact]) }}" method="POST" onsubmit="return confirm('Remove emergency contact?');" class="inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-medium">Remove</button>
                                                                </form>
                                                            @endcan
                                                        </div>
                                                    </div>
                                                    <div x-show="editing" x-cloak class="p-4 bg-muted/20 border-t border-border">
                                                        <form action="{{ route('hrms-core.employees.dossier.emergency-contacts.update', [$employee, $contact]) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                            @csrf
                                                            @method('PUT')
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Full Name *</label>
                                                                <input type="text" name="name" required value="{{ $contact->name }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Relationship</label>
                                                                <input type="text" name="relationship" value="{{ $contact->relationship }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Phone Number *</label>
                                                                <input type="text" name="phone" required value="{{ $contact->phone }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Email Address</label>
                                                                <input type="email" name="email" value="{{ $contact->email }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <label class="text-xs text-muted-foreground block mb-1">Address</label>
                                                                <input type="text" name="address" value="{{ $contact->address }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <input type="checkbox" name="is_primary" value="1" id="is_primary_{{ $contact->id }}" {{ $contact->is_primary ? 'checked' : '' }} class="rounded border-border text-primary focus:ring-primary">
                                                                <label for="is_primary_{{ $contact->id }}" class="text-xs text-foreground">Mark as Primary Contact</label>
                                                            </div>
                                                            <div class="flex justify-end gap-2">
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
                        @else
                            <p class="text-sm text-muted-foreground italic">No emergency contacts registered.</p>
                        @endif

                        @can('hrms.employees.update')
                            <div class="pt-3 border-t border-border">
                                <h4 class="text-xs uppercase font-semibold text-muted-foreground mb-3">Add Emergency Contact</h4>
                                <form action="{{ route('hrms-core.employees.dossier.emergency-contacts.store', $employee) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @csrf
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Full Name *</label>
                                        <input type="text" name="name" required class="kt-input w-full text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Relationship</label>
                                        <input type="text" name="relationship" class="kt-input w-full text-sm" placeholder="e.g. Sibling, Friend">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Phone Number *</label>
                                        <input type="text" name="phone" required class="kt-input w-full text-sm" placeholder="+233...">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Email Address</label>
                                        <input type="email" name="email" class="kt-input w-full text-sm">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="text-xs text-muted-foreground block mb-1">Address</label>
                                        <input type="text" name="address" class="kt-input w-full text-sm">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" name="is_primary" value="1" id="is_primary_contact" class="rounded border-border text-primary focus:ring-primary">
                                        <label for="is_primary_contact" class="text-sm text-foreground">Mark as Primary Contact</label>
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="submit" class="kt-btn kt-btn-outline text-xs">Add Contact</button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                </x-metronic.card>
            </div>

            {{-- 5. EDUCATION & QUALIFICATIONS TAB --}}
            <div x-show="activeTab === 'education'" class="flex flex-col gap-6" style="display: none;">
                {{-- Educational Background --}}
                <x-metronic.card title="Educational Background" subtitle="Academic degrees, diplomas, and institutions attended">
                    <div class="space-y-4">
                        @if($employee->educationalBackgrounds->count())
                            <div class="overflow-x-auto rounded-xl border border-border">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-muted/40 text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th class="py-2.5 px-4 font-semibold">Institution</th>
                                            <th class="py-2.5 px-4 font-semibold">Qualification</th>
                                            <th class="py-2.5 px-4 font-semibold">Field of Study</th>
                                            <th class="py-2.5 px-4 font-semibold">Period</th>
                                            <th class="py-2.5 px-4 text-right font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach($employee->educationalBackgrounds as $edu)
                                            <tr x-data="{ editing: false }">
                                                <td colspan="5" class="p-0">
                                                    <div class="flex items-center justify-between py-2.5 px-4" x-show="!editing">
                                                        <div class="flex items-center gap-6">
                                                            <span class="font-medium text-foreground w-48">{{ $edu->institution_name }}</span>
                                                            <span class="text-muted-foreground w-40">{{ $edu->qualification }}</span>
                                                            <span class="text-muted-foreground w-36">{{ $edu->field_of_study ?: '—' }}</span>
                                                            <span class="text-xs font-mono text-muted-foreground w-28">
                                                                {{ $edu->start_date?->format('Y') ?: '—' }} &ndash; {{ $edu->end_date?->format('Y') ?: 'Present' }}
                                                            </span>
                                                        </div>
                                                        <div class="text-right whitespace-nowrap">
                                                            @can('hrms.employees.update')
                                                                <button type="button" @click="editing = true" class="text-primary hover:text-primary-active text-xs font-medium mr-2">Edit</button>
                                                                <form action="{{ route('hrms-core.employees.dossier.education.destroy', [$employee, $edu]) }}" method="POST" onsubmit="return confirm('Remove education record?');" class="inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-medium">Remove</button>
                                                                </form>
                                                            @endcan
                                                        </div>
                                                    </div>
                                                    <div x-show="editing" x-cloak class="p-4 bg-muted/20 border-t border-border">
                                                        <form action="{{ route('hrms-core.employees.dossier.education.update', [$employee, $edu]) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                            @csrf
                                                            @method('PUT')
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Institution Name *</label>
                                                                <input type="text" name="institution_name" required value="{{ $edu->institution_name }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Qualification *</label>
                                                                <input type="text" name="qualification" required value="{{ $edu->qualification }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Field of Study</label>
                                                                <input type="text" name="field_of_study" value="{{ $edu->field_of_study }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Grade / Classification</label>
                                                                <input type="text" name="grade" value="{{ $edu->grade }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Start Date</label>
                                                                <input type="date" name="start_date" value="{{ $edu->start_date?->format('Y-m-d') }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">End Date</label>
                                                                <input type="date" name="end_date" value="{{ $edu->end_date?->format('Y-m-d') }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div class="md:col-span-2 flex justify-end gap-2">
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
                        @else
                            <p class="text-sm text-muted-foreground italic">No academic qualifications registered.</p>
                        @endif

                        @can('hrms.employees.update')
                            <div class="pt-3 border-t border-border">
                                <h4 class="text-xs uppercase font-semibold text-muted-foreground mb-3">Add Educational Background</h4>
                                <form action="{{ route('hrms-core.employees.dossier.education.store', $employee) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @csrf
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Institution Name *</label>
                                        <input type="text" name="institution_name" required class="kt-input w-full text-sm" placeholder="e.g. University of Ghana">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Qualification *</label>
                                        <input type="text" name="qualification" required class="kt-input w-full text-sm" placeholder="e.g. BSc Computer Science">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Field of Study</label>
                                        <input type="text" name="field_of_study" class="kt-input w-full text-sm" placeholder="e.g. Computer Science">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Grade / Classification</label>
                                        <input type="text" name="grade" class="kt-input w-full text-sm" placeholder="e.g. First Class Honours">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Start Date</label>
                                        <input type="date" name="start_date" class="kt-input w-full text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">End Date</label>
                                        <input type="date" name="end_date" class="kt-input w-full text-sm">
                                    </div>
                                    <div class="md:col-span-2 flex justify-end">
                                        <button type="submit" class="kt-btn kt-btn-outline text-xs">Add Education</button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                </x-metronic.card>

                {{-- Professional Qualifications --}}
                <x-metronic.card title="Professional Certifications & Licenses" subtitle="Chartered certifications, medical and professional registrations">
                    <div class="space-y-4">
                        @if($employee->professionalQualifications->count())
                            <div class="overflow-x-auto rounded-xl border border-border">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-muted/40 text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th class="py-2.5 px-4 font-semibold">Certification</th>
                                            <th class="py-2.5 px-4 font-semibold">Issuing Body</th>
                                            <th class="py-2.5 px-4 font-semibold">License #</th>
                                            <th class="py-2.5 px-4 font-semibold">Valid Thru</th>
                                            <th class="py-2.5 px-4 text-right font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach($employee->professionalQualifications as $qual)
                                            <tr x-data="{ editing: false }">
                                                <td colspan="5" class="p-0">
                                                    <div class="flex items-center justify-between py-2.5 px-4" x-show="!editing">
                                                        <div class="flex items-center gap-6">
                                                            <span class="font-medium text-foreground w-48">{{ $qual->certification_name }}</span>
                                                            <span class="text-muted-foreground w-36">{{ $qual->issuing_body }}</span>
                                                            <span class="font-mono text-xs w-32">{{ $qual->certificate_number ?: '—' }}</span>
                                                            <div class="text-xs font-mono w-36">
                                                                @if($qual->expiry_date)
                                                                    @if($qual->isExpired())
                                                                        <span class="text-rose-600 font-semibold">{{ $qual->expiry_date->format('d/m/Y') }} (Expired)</span>
                                                                    @else
                                                                        <span class="text-emerald-600">{{ $qual->expiry_date->format('d/m/Y') }}</span>
                                                                    @endif
                                                                @else
                                                                    <span class="text-muted-foreground">Lifetime / No Expiry</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="text-right whitespace-nowrap">
                                                            @can('hrms.employees.update')
                                                                <button type="button" @click="editing = true" class="text-primary hover:text-primary-active text-xs font-medium mr-2">Edit</button>
                                                                <form action="{{ route('hrms-core.employees.dossier.qualifications.destroy', [$employee, $qual]) }}" method="POST" onsubmit="return confirm('Remove qualification?');" class="inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-medium">Remove</button>
                                                                </form>
                                                            @endcan
                                                        </div>
                                                    </div>
                                                    <div x-show="editing" x-cloak class="p-4 bg-muted/20 border-t border-border">
                                                        <form action="{{ route('hrms-core.employees.dossier.qualifications.update', [$employee, $qual]) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                            @csrf
                                                            @method('PUT')
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Certification Name *</label>
                                                                <input type="text" name="certification_name" required value="{{ $qual->certification_name }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Issuing Body *</label>
                                                                <input type="text" name="issuing_body" required value="{{ $qual->issuing_body }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Certificate / License #</label>
                                                                <input type="text" name="certificate_number" value="{{ $qual->certificate_number }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Date Obtained</label>
                                                                <input type="date" name="date_obtained" value="{{ $qual->date_obtained?->format('Y-m-d') }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Expiry Date</label>
                                                                <input type="date" name="expiry_date" value="{{ $qual->expiry_date?->format('Y-m-d') }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div class="flex items-end justify-end gap-2">
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
                        @else
                            <p class="text-sm text-muted-foreground italic">No professional certifications registered.</p>
                        @endif

                        @can('hrms.employees.update')
                            <div class="pt-3 border-t border-border">
                                <h4 class="text-xs uppercase font-semibold text-muted-foreground mb-3">Add Professional Qualification</h4>
                                <form action="{{ route('hrms-core.employees.dossier.qualifications.store', $employee) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @csrf
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Certification Name *</label>
                                        <input type="text" name="certification_name" required class="kt-input w-full text-sm" placeholder="e.g. PMP or ACCA">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Issuing Body *</label>
                                        <input type="text" name="issuing_body" required class="kt-input w-full text-sm" placeholder="e.g. PMI / ICAG">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Certificate / License Number</label>
                                        <input type="text" name="certificate_number" class="kt-input w-full text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Date Obtained</label>
                                        <input type="date" name="date_obtained" class="kt-input w-full text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Expiry Date (if applicable)</label>
                                        <input type="date" name="expiry_date" class="kt-input w-full text-sm">
                                    </div>
                                    <div class="flex items-end justify-end">
                                        <button type="submit" class="kt-btn kt-btn-outline text-xs">Add Qualification</button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                </x-metronic.card>
            </div>

            {{-- 6. WORK EXPERIENCE TAB --}}
            <div x-show="activeTab === 'experience'" class="flex flex-col gap-6" style="display: none;">
                <x-metronic.card title="Previous Work Experience" subtitle="Historical employment records prior to joining the institution">
                    <div class="space-y-4">
                        @if($employee->previousWorkExperiences->count())
                            <div class="overflow-x-auto rounded-xl border border-border">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-muted/40 text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th class="py-2.5 px-4 font-semibold">Company / Employer</th>
                                            <th class="py-2.5 px-4 font-semibold">Position</th>
                                            <th class="py-2.5 px-4 font-semibold">Tenure</th>
                                            <th class="py-2.5 px-4 text-right font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach($employee->previousWorkExperiences as $exp)
                                            <tr x-data="{ editing: false }">
                                                <td colspan="4" class="p-0">
                                                    <div class="flex items-center justify-between py-2.5 px-4" x-show="!editing">
                                                        <div class="flex items-center gap-6">
                                                            <div class="w-56">
                                                                <span class="font-medium text-foreground block">{{ $exp->company_name }}</span>
                                                                @if($exp->reason_for_leaving)
                                                                    <span class="block text-xs text-muted-foreground">Reason: {{ $exp->reason_for_leaving }}</span>
                                                                @endif
                                                            </div>
                                                            <span class="text-muted-foreground w-40">{{ $exp->position }}</span>
                                                            <span class="text-xs font-mono text-muted-foreground w-36">
                                                                {{ $exp->start_date?->format('M Y') ?: '—' }} &ndash; {{ $exp->end_date?->format('M Y') ?: 'Present' }}
                                                            </span>
                                                        </div>
                                                        <div class="text-right whitespace-nowrap">
                                                            @can('hrms.employees.update')
                                                                <button type="button" @click="editing = true" class="text-primary hover:text-primary-active text-xs font-medium mr-2">Edit</button>
                                                                <form action="{{ route('hrms-core.employees.dossier.work-experiences.destroy', [$employee, $exp]) }}" method="POST" onsubmit="return confirm('Remove experience?');" class="inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-medium">Remove</button>
                                                                </form>
                                                            @endcan
                                                        </div>
                                                    </div>
                                                    <div x-show="editing" x-cloak class="p-4 bg-muted/20 border-t border-border">
                                                        <form action="{{ route('hrms-core.employees.dossier.work-experiences.update', [$employee, $exp]) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                            @csrf
                                                            @method('PUT')
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Company / Organization *</label>
                                                                <input type="text" name="company_name" required value="{{ $exp->company_name }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Position / Role *</label>
                                                                <input type="text" name="position" required value="{{ $exp->position }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">Start Date</label>
                                                                <input type="date" name="start_date" value="{{ $exp->start_date?->format('Y-m-d') }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="text-xs text-muted-foreground block mb-1">End Date</label>
                                                                <input type="date" name="end_date" value="{{ $exp->end_date?->format('Y-m-d') }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <label class="text-xs text-muted-foreground block mb-1">Reason for Leaving</label>
                                                                <input type="text" name="reason_for_leaving" value="{{ $exp->reason_for_leaving }}" class="kt-input w-full text-sm">
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <label class="text-xs text-muted-foreground block mb-1">Responsibilities / Scope</label>
                                                                <textarea name="responsibilities" rows="2" class="kt-input w-full text-sm">{{ $exp->responsibilities }}</textarea>
                                                            </div>
                                                            <div class="md:col-span-2 flex justify-end gap-2">
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
                        @else
                            <p class="text-sm text-muted-foreground italic">No previous employment history recorded.</p>
                        @endif

                        @can('hrms.employees.update')
                            <div class="pt-3 border-t border-border">
                                <h4 class="text-xs uppercase font-semibold text-muted-foreground mb-3">Add Prior Employment Record</h4>
                                <form action="{{ route('hrms-core.employees.dossier.work-experiences.store', $employee) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @csrf
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Company / Organization *</label>
                                        <input type="text" name="company_name" required class="kt-input w-full text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Position / Role *</label>
                                        <input type="text" name="position" required class="kt-input w-full text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Start Date</label>
                                        <input type="date" name="start_date" class="kt-input w-full text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">End Date</label>
                                        <input type="date" name="end_date" class="kt-input w-full text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Reason for Leaving</label>
                                        <input type="text" name="reason_for_leaving" class="kt-input w-full text-sm">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="text-xs text-muted-foreground block mb-1">Key Responsibilities</label>
                                        <textarea name="responsibilities" rows="2" class="kt-input w-full text-sm"></textarea>
                                    </div>
                                    <div class="md:col-span-2 flex justify-end">
                                        <button type="submit" class="kt-btn kt-btn-outline text-xs">Add Work Experience</button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                </x-metronic.card>
            </div>

            {{-- 7. EMPLOYMENT STATUS TAB --}}
            <div x-show="activeTab === 'status'" class="flex flex-col gap-6" style="display: none;">
                <x-metronic.card title="Employment Status Timeline" subtitle="Lifecycle transitions (active, leave, suspension, retirement)">
                    <div class="space-y-4">
                        @if($employee->employmentStatuses->count())
                            <div class="overflow-x-auto rounded-xl border border-border">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-muted/40 text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th class="py-2.5 px-4 font-semibold">Status</th>
                                            <th class="py-2.5 px-4 font-semibold">Effective Date</th>
                                            <th class="py-2.5 px-4 font-semibold">End Date</th>
                                            <th class="py-2.5 px-4 font-semibold">Reason</th>
                                            <th class="py-2.5 px-4 font-semibold">Current</th>
                                            <th class="py-2.5 px-4 text-right font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach($employee->employmentStatuses->sortByDesc('effective_date') as $status)
                                            <tr>
                                                <td class="py-2.5 px-4 font-medium capitalize text-foreground">
                                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold {{ $status->status === 'active' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-amber-500/10 text-amber-600' }}">
                                                        {{ str_replace('_', ' ', $status->status) }}
                                                    </span>
                                                </td>
                                                <td class="py-2.5 px-4 font-mono text-xs">{{ $status->effective_date->format('d/m/Y') }}</td>
                                                <td class="py-2.5 px-4 font-mono text-xs">{{ $status->end_date?->format('d/m/Y') ?: '—' }}</td>
                                                <td class="py-2.5 px-4 text-xs text-muted-foreground">{{ $status->reason ?: '—' }}</td>
                                                <td class="py-2.5 px-4">
                                                    @if($status->is_current)
                                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary">Active Current</span>
                                                    @endif
                                                </td>
                                                <td class="py-2.5 px-4 text-right">
                                                    @can('hrms.employees.update')
                                                        <form action="{{ route('hrms-core.employees.dossier.employment-statuses.destroy', [$employee, $status]) }}" method="POST" onsubmit="return confirm('Remove status entry?');" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-medium">Remove</button>
                                                        </form>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-muted-foreground italic">No status timeline events recorded.</p>
                        @endif

                        @can('hrms.employees.update')
                            <div class="pt-3 border-t border-border">
                                <h4 class="text-xs uppercase font-semibold text-muted-foreground mb-3">Record Status Transition</h4>
                                <form action="{{ route('hrms-core.employees.dossier.employment-statuses.store', $employee) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @csrf
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">New Status *</label>
                                        <select name="status" required class="kt-input w-full text-sm">
                                            @foreach(\App\Modules\HrmsCore\Models\Employees\EmploymentStatus::statuses() as $key => $label)
                                                <option value="{{ $key }}" {{ $employee->currentEmploymentStatus?->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">Effective Date *</label>
                                        <input type="date" name="effective_date" required value="{{ date('Y-m-d') }}" class="kt-input w-full text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted-foreground block mb-1">End Date (optional)</label>
                                        <input type="date" name="end_date" class="kt-input w-full text-sm">
                                    </div>
                                    <div class="flex items-center gap-2 pt-5">
                                        <input type="checkbox" name="is_current" value="1" id="is_current_status" checked class="rounded border-border text-primary focus:ring-primary">
                                        <label for="is_current_status" class="text-sm text-foreground">Set as Current Active Status</label>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="text-xs text-muted-foreground block mb-1">Reason / Notes</label>
                                        <textarea name="reason" rows="2" class="kt-input w-full text-sm" placeholder="Official reason or documentation references"></textarea>
                                    </div>
                                    <div class="md:col-span-2 flex justify-end">
                                        <button type="submit" class="kt-btn kt-btn-outline text-xs">Record Status Change</button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                </x-metronic.card>
            </div>

        </div>

        {{-- Side Cards (4 cols) --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            <x-metronic.card title="Status & Record" subtitle="Personnel system lifecycle">
                <div class="space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-sm text-muted-foreground">Employment Status</span>
                        <x-metronic.badge :variant="$employee->is_active ? 'success' : 'secondary'">
                            {{ $employee->is_active ? 'Active' : 'Inactive' }}
                        </x-metronic.badge>
                    </div>

                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-sm text-muted-foreground">Portal Account</span>
                        @if($employee->user_id)
                            <x-metronic.badge variant="primary" size="sm">
                                User #{{ $employee->user_id }}
                            </x-metronic.badge>
                        @else
                            <span class="text-xs text-muted-foreground italic">Unlinked</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-sm text-muted-foreground">Enrolled Date</span>
                        <span class="text-xs font-medium text-foreground">
                            {{ $employee->created_at?->format('d/m/Y') ?: '—' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Last Updated</span>
                        <span class="text-xs font-medium text-foreground">
                            {{ $employee->updated_at?->diffForHumans() ?: '—' }}
                        </span>
                    </div>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Dossier Summary">
                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between py-1 border-b border-border">
                        <span class="text-muted-foreground">Bank Account:</span>
                        <span class="font-medium text-foreground">{{ $employee->bankDetails ? $employee->bankDetails->bank_name : 'Pending' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-border">
                        <span class="text-muted-foreground">Next of Kin:</span>
                        <span class="font-medium text-foreground">{{ $employee->nextOfKin ? $employee->nextOfKin->name : 'Not Set' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-border">
                        <span class="text-muted-foreground">Emergency Contacts:</span>
                        <span class="font-medium text-foreground">{{ $employee->emergencyContacts->count() }} listed</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-border">
                        <span class="text-muted-foreground">Academic Records:</span>
                        <span class="font-medium text-foreground">{{ $employee->educationalBackgrounds->count() }} records</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-border">
                        <span class="text-muted-foreground">Certifications:</span>
                        <span class="font-medium text-foreground">{{ $employee->professionalQualifications->count() }} listed</span>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-muted-foreground">Prior Work History:</span>
                        <span class="font-medium text-foreground">{{ $employee->previousWorkExperiences->count() }} employers</span>
                    </div>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Quick Actions">
                <div class="flex flex-col gap-2.5">
                    @can('hrms.leaves.create')
                        <a href="{{ route('hrms-core.leave-requests.create') }}" class="kt-btn kt-btn-outline w-full justify-start text-xs gap-2">
                            <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Submit Leave on Behalf
                        </a>
                    @endcan
                    @can('hrms.employees.update')
                        <a href="{{ route('hrms-core.employees.edit', $employee) }}" class="kt-btn kt-btn-outline w-full justify-start text-xs gap-2">
                            <svg class="h-4 w-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            Modify Employee Details
                        </a>
                    @endcan
                    <a href="{{ route('hrms-core.leave-requests.index') }}" class="kt-btn kt-btn-outline w-full justify-start text-xs gap-2">
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        View All Leave Records
                    </a>
                </div>
            </x-metronic.card>
        </div>
    </div>
</div>
@endsection
