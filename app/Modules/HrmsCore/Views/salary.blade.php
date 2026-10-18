@extends('layouts.metronic.app')

@section('title', 'Salary Structure & Allowances')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Salary Structure & Allowances"
        description="Grade-linked salary levels, step increments, allowance catalog, and confidential employee compensation assignments."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.index') }}" class="kt-btn kt-btn-outline">HRMS Hub</a>
            <a href="{{ route('hrms-core.promotions.index') }}" class="kt-btn kt-btn-outline">Staff Promotions</a>
            <a href="{{ route('hrms-core.salary.index', ['scope' => 'mine']) }}" class="kt-btn {{ $scope === 'mine' ? 'kt-btn-primary' : 'kt-btn-outline' }}">
                My Compensation
            </a>
            @if($scope === 'mine')
                <a href="{{ route('hrms-core.salary.index') }}" class="kt-btn kt-btn-outline">All Visible</a>
            @endif
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('status'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-rose-800 dark:text-rose-300 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- My Compensation Summary Card --}}
    @if($myCompensation !== null)
        <x-metronic.card
            title="My Compensation & Active Allowances ({{ $myCompensation->displayName() }})"
            subtitle="Personal grade, salary scale placement, and active allowance entitlements"
        >
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div class="rounded-xl border border-border p-3">
                    <span class="text-[11px] uppercase font-semibold text-muted-foreground">Current Grade</span>
                    <p class="mt-1 text-sm font-bold text-foreground">{{ $myCompensation->grade?->name ?: 'Unassigned' }}</p>
                </div>
                <div class="rounded-xl border border-border p-3">
                    <span class="text-[11px] uppercase font-semibold text-muted-foreground">Salary Level</span>
                    <p class="mt-1 text-sm font-bold text-foreground">
                        {{ $myCompensation->salaryLevel?->name ?: 'Unassigned' }}
                        @if($myCompensation->salaryLevel)
                            <span class="text-xs font-mono text-muted-foreground">(GHS {{ number_format((float) $myCompensation->salaryLevel->base_salary, 2) }})</span>
                        @endif
                    </p>
                </div>
                <div class="rounded-xl border border-border p-3">
                    <span class="text-[11px] uppercase font-semibold text-muted-foreground">Salary Step</span>
                    <p class="mt-1 text-sm font-bold text-foreground">
                        {{ $myCompensation->salaryStep?->name ?: 'Unassigned' }}
                        @if($myCompensation->salaryStep)
                            <span class="text-xs font-mono text-muted-foreground">(+GHS {{ number_format((float) $myCompensation->salaryStep->step_increment, 2) }})</span>
                        @endif
                    </p>
                </div>
                <div class="rounded-xl border border-border p-3">
                    <span class="text-[11px] uppercase font-semibold text-muted-foreground">Assigned Allowances</span>
                    <p class="mt-1 text-sm font-bold text-primary">{{ $myCompensation->employeeAllowances->count() }}</p>
                </div>
            </div>
        </x-metronic.card>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left 7 Columns: Salary Levels, Steps, and Allowance Catalog --}}
        <div class="lg:col-span-7 flex flex-col gap-6">
            {{-- Salary Levels & Steps --}}
            <x-metronic.card
                title="Grade Salary Levels ({{ $salaryLevels->count() }})"
                subtitle="Institutional base salary scales and step increments"
            >
                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left text-xs">
                        <thead>
                            <tr class="uppercase text-muted-foreground border-b border-border">
                                <th class="py-2.5 px-3">Level</th>
                                <th class="py-2.5 px-3">Grade</th>
                                <th class="py-2.5 px-3 text-right">Base Salary</th>
                                <th class="py-2.5 px-3 text-center">Steps</th>
                                @if($canManageSalary)
                                    <th class="py-2.5 px-3 text-right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($salaryLevels as $level)
                                <tr>
                                    <td class="py-2.5 px-3">
                                        <p class="font-semibold text-foreground">{{ $level->name }}</p>
                                        @if($level->description)
                                            <p class="text-[11px] text-muted-foreground">{{ $level->description }}</p>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-muted-foreground">{{ $level->grade?->name ?: 'All Grades' }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold text-foreground">
                                        GHS {{ number_format((float) $level->base_salary, 2) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono">{{ $level->salary_steps_count }}</td>
                                    @if($canManageSalary)
                                        <td class="py-2.5 px-3 text-right">
                                            <form method="POST" action="{{ route('hrms-core.salary.levels.destroy', $level) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-rose-600 hover:underline text-[11px]">Delete</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-muted-foreground">No salary levels defined yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($salarySteps->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-border">
                        <h4 class="text-xs font-semibold uppercase text-muted-foreground mb-2">Configured Salary Steps ({{ $salarySteps->count() }})</h4>
                        <div class="overflow-x-auto">
                            <table class="kt-table table-auto kt-table-border w-full text-left text-xs">
                                <thead>
                                    <tr class="uppercase text-muted-foreground border-b border-border">
                                        <th class="py-2 px-3">Step</th>
                                        <th class="py-2 px-3">Level</th>
                                        <th class="py-2 px-3 text-center">No.</th>
                                        <th class="py-2 px-3 text-right">Increment</th>
                                        <th class="py-2 px-3 text-right">Total Scale</th>
                                        @if($canManageSalary)
                                            <th class="py-2 px-3 text-right">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach($salarySteps as $step)
                                        <tr>
                                            <td class="py-2 px-3 font-medium text-foreground">{{ $step->name }}</td>
                                            <td class="py-2 px-3 text-muted-foreground">{{ $step->salaryLevel?->name ?: '—' }}</td>
                                            <td class="py-2 px-3 text-center font-mono">#{{ $step->step_number }}</td>
                                            <td class="py-2 px-3 text-right font-mono">+GHS {{ number_format((float) $step->step_increment, 2) }}</td>
                                            <td class="py-2 px-3 text-right font-mono font-semibold text-foreground">
                                                GHS {{ number_format($step->calculateTotalSalary(), 2) }}
                                            </td>
                                            @if($canManageSalary)
                                                <td class="py-2 px-3 text-right">
                                                    <form method="POST" action="{{ route('hrms-core.salary.steps.destroy', $step) }}" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-rose-600 hover:underline text-[11px]">Delete</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </x-metronic.card>

            {{-- Allowance Catalog --}}
            <x-metronic.card
                title="Allowance Catalog ({{ $allowances->count() }})"
                subtitle="Standardized allowance categories and amounts"
            >
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach($allowanceTypes as $aType)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/30 px-3 py-1 text-xs">
                            <span class="font-semibold text-foreground">{{ $aType->name }}</span>
                            <span class="text-[10px] text-muted-foreground">({{ $aType->is_taxable ? 'Taxable' : 'Non-taxable' }})</span>
                        </span>
                    @endforeach
                </div>

                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left text-xs">
                        <thead>
                            <tr class="uppercase text-muted-foreground border-b border-border">
                                <th class="py-2.5 px-3">Allowance</th>
                                <th class="py-2.5 px-3">Category</th>
                                <th class="py-2.5 px-3">Grade</th>
                                <th class="py-2.5 px-3">Frequency</th>
                                <th class="py-2.5 px-3 text-right">Default Amount</th>
                                @if($canManageAllowances)
                                    <th class="py-2.5 px-3 text-right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($allowances as $allowance)
                                <tr>
                                    <td class="py-2.5 px-3 font-semibold text-foreground">{{ $allowance->name }}</td>
                                    <td class="py-2.5 px-3 text-muted-foreground">{{ $allowance->allowanceType?->name ?: '—' }}</td>
                                    <td class="py-2.5 px-3 text-muted-foreground">{{ $allowance->grade?->name ?: 'All Grades' }}</td>
                                    <td class="py-2.5 px-3 uppercase font-mono text-[11px]">{{ $allowance->frequency }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold text-foreground">
                                        GHS {{ number_format((float) $allowance->amount, 2) }}
                                    </td>
                                    @if($canManageAllowances)
                                        <td class="py-2.5 px-3 text-right">
                                            <form method="POST" action="{{ route('hrms-core.salary.allowances.destroy', $allowance) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-rose-600 hover:underline text-[11px]">Delete</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-muted-foreground">No allowances configured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            {{-- Confidential Employee Compensation & Allowances --}}
            <x-metronic.card
                title="Employee Compensation & Allowance Register ({{ $employeeAllowances->total() }})"
                subtitle="Restricted to HR compensation managers and the employee's own record"
            >
                <div class="overflow-x-auto mb-4">
                    <table class="kt-table table-auto kt-table-border w-full text-left text-xs">
                        <thead>
                            <tr class="uppercase text-muted-foreground border-b border-border">
                                <th class="py-2.5 px-3">Employee</th>
                                <th class="py-2.5 px-3">Allowance</th>
                                <th class="py-2.5 px-3 text-right">Effective Amount</th>
                                <th class="py-2.5 px-3">Effective Period</th>
                                <th class="py-2.5 px-3">Status</th>
                                @if($canManageAllowances)
                                    <th class="py-2.5 px-3 text-right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($employeeAllowances as $empAllowance)
                                <tr>
                                    <td class="py-2.5 px-3">
                                        <p class="font-semibold text-foreground">{{ $empAllowance->employee?->displayName() ?: '—' }}</p>
                                        <p class="text-[11px] font-mono text-muted-foreground">{{ $empAllowance->employee?->employee_staff_id ?: '—' }}</p>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <p class="font-medium text-foreground">{{ $empAllowance->allowance?->name ?: '—' }}</p>
                                        @if($empAllowance->notes)
                                            <p class="text-[11px] text-muted-foreground">{{ $empAllowance->notes }}</p>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold text-foreground">
                                        GHS {{ number_format($empAllowance->getEffectiveAmount(), 2) }}
                                    </td>
                                    <td class="py-2.5 px-3 font-mono text-[11px] text-muted-foreground">
                                        {{ $empAllowance->effective_from?->format('Y-m-d') }}
                                        &rarr;
                                        {{ $empAllowance->effective_to?->format('Y-m-d') ?: 'Ongoing' }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $empAllowance->is_active ? 'bg-emerald-500/10 text-emerald-700' : 'bg-muted text-muted-foreground' }}">
                                            {{ $empAllowance->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    @if($canManageAllowances)
                                        <td class="py-2.5 px-3 text-right">
                                            <form method="POST" action="{{ route('hrms-core.salary.employee-allowances.destroy', $empAllowance) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-rose-600 hover:underline text-[11px]">Remove</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-muted-foreground">No employee allowances visible in this scope.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $employeeAllowances->links() }}
            </x-metronic.card>
        </div>

        {{-- Right 5 Columns: Management Forms --}}
        <div class="lg:col-span-5 flex flex-col gap-6">
            @if($canManageSalary)
                <x-metronic.card title="Create Salary Level" subtitle="Define a base salary scale for a grade">
                    <form method="POST" action="{{ route('hrms-core.salary.levels.store') }}" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Level Name *</label>
                            <input type="text" name="name" required class="kt-input w-full text-xs" placeholder="e.g. Level 12 Senior Scale" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Grade</label>
                                <select name="grade_id" class="kt-select w-full text-xs">
                                    <option value="">All / Unlinked</option>
                                    @foreach($grades as $grade)
                                        <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Base Salary (GHS) *</label>
                                <input type="number" name="base_salary" step="0.01" min="0" required class="kt-input w-full text-xs" placeholder="5500.00" />
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Description</label>
                            <input type="text" name="description" class="kt-input w-full text-xs" />
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Create Salary Level</button>
                    </form>
                </x-metronic.card>

                <x-metronic.card title="Add Salary Step" subtitle="Define a step increment within a salary level">
                    <form method="POST" action="{{ route('hrms-core.salary.steps.store') }}" class="space-y-3 text-xs">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Salary Level *</label>
                                <select name="salary_level_id" required class="kt-select w-full text-xs">
                                    <option value="">Select level...</option>
                                    @foreach($salaryLevels as $level)
                                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Step Name *</label>
                                <input type="text" name="name" required class="kt-input w-full text-xs" placeholder="Step 1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Step Number *</label>
                                <input type="number" name="step_number" min="1" value="1" required class="kt-input w-full text-xs" />
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Step Increment (GHS) *</label>
                                <input type="number" name="step_increment" step="0.01" min="0" value="0" required class="kt-input w-full text-xs" />
                            </div>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Add Salary Step</button>
                    </form>
                </x-metronic.card>

                <x-metronic.card title="Assign Employee Salary Scale" subtitle="Bind an employee to a grade, salary level, and step">
                    <form method="POST" action="{{ route('hrms-core.salary.employee-compensation.update') }}" class="space-y-3 text-xs">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Employee *</label>
                            <select name="employee_id" required class="kt-select w-full text-xs">
                                <option value="">Select employee...</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->displayName() }} ({{ $emp->employee_staff_id ?: 'No ID' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Grade</label>
                                <select name="grade_id" class="kt-select w-full text-xs">
                                    <option value="">Keep current</option>
                                    @foreach($grades as $grade)
                                        <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Salary Level</label>
                                <select name="salary_level_id" class="kt-select w-full text-xs">
                                    <option value="">None</option>
                                    @foreach($salaryLevels as $level)
                                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Salary Step</label>
                                <select name="salary_step_id" class="kt-select w-full text-xs">
                                    <option value="">None</option>
                                    @foreach($salarySteps as $step)
                                        <option value="{{ $step->id }}">{{ $step->name }} ({{ $step->salaryLevel?->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Update Employee Salary Scale</button>
                    </form>
                </x-metronic.card>
            @endif

            @if($canManageAllowances)
                <x-metronic.card title="Allowance Category & Definition" subtitle="Configure allowance types and standard amounts">
                    <form method="POST" action="{{ route('hrms-core.salary.allowance-types.store') }}" class="space-y-3 text-xs pb-4 mb-4 border-b border-border">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Category Name *</label>
                                <input type="text" name="name" required class="kt-input w-full text-xs" placeholder="e.g. Housing Allowance" />
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="inline-flex items-center gap-1.5">
                                    <input type="checkbox" name="is_taxable" value="1" checked />
                                    <span>Taxable category</span>
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Add Allowance Category</button>
                    </form>

                    <form method="POST" action="{{ route('hrms-core.salary.allowances.store') }}" class="space-y-3 text-xs">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Allowance Name *</label>
                                <input type="text" name="name" required class="kt-input w-full text-xs" placeholder="e.g. Senior Rent Supplement" />
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Category *</label>
                                <select name="allowance_type_id" required class="kt-select w-full text-xs">
                                    <option value="">Select category...</option>
                                    @foreach($allowanceTypes as $aType)
                                        <option value="{{ $aType->id }}">{{ $aType->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Amount *</label>
                                <input type="number" name="amount" step="0.01" min="0" required class="kt-input w-full text-xs" />
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Frequency *</label>
                                <select name="frequency" required class="kt-select w-full text-xs">
                                    @foreach($frequencyOptions as $fKey => $fLabel)
                                        <option value="{{ $fKey }}">{{ $fLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Grade</label>
                                <select name="grade_id" class="kt-select w-full text-xs">
                                    <option value="">All</option>
                                    @foreach($grades as $grade)
                                        <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Create Allowance Definition</button>
                    </form>
                </x-metronic.card>

                <x-metronic.card title="Assign Allowance to Employee" subtitle="Grant an allowance with optional custom override amount">
                    <form method="POST" action="{{ route('hrms-core.salary.employee-allowances.store') }}" class="space-y-3 text-xs">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Employee *</label>
                                <select name="employee_id" required class="kt-select w-full text-xs">
                                    <option value="">Select employee...</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Allowance *</label>
                                <select name="allowance_id" required class="kt-select w-full text-xs">
                                    <option value="">Select allowance...</option>
                                    @foreach($allowances as $allowance)
                                        <option value="{{ $allowance->id }}">{{ $allowance->name }} (GHS {{ number_format((float) $allowance->amount, 2) }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Override Amount</label>
                                <input type="number" name="amount" step="0.01" min="0" class="kt-input w-full text-xs" placeholder="Default" />
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Effective From *</label>
                                <input type="date" name="effective_from" value="{{ date('Y-m-d') }}" required class="kt-input w-full text-xs" />
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Effective To</label>
                                <input type="date" name="effective_to" class="kt-input w-full text-xs" />
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Notes</label>
                            <input type="text" name="notes" class="kt-input w-full text-xs" />
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Assign Allowance</button>
                    </form>
                </x-metronic.card>
            @endif
        </div>
    </div>
</div>
@endsection

