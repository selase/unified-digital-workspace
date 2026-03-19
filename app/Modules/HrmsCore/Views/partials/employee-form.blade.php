@php
    /** @var \App\Modules\HrmsCore\Models\Employees\Employee|null $employee */
    $employee = $employee ?? null;
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div class="kt-form-item">
        <label class="kt-form-label">First Name <span class="text-destructive">*</span></label>
        <div class="kt-form-control">
            <input class="kt-input" name="first_name" required type="text" value="{{ old('first_name', $employee?->first_name) }}">
        </div>
        @error('first_name') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Last Name <span class="text-destructive">*</span></label>
        <div class="kt-form-control">
            <input class="kt-input" name="last_name" required type="text" value="{{ old('last_name', $employee?->last_name) }}">
        </div>
        @error('last_name') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Staff ID</label>
        <div class="kt-form-control">
            <input class="kt-input" name="employee_staff_id" type="text" value="{{ old('employee_staff_id', $employee?->employee_staff_id) }}">
        </div>
        @error('employee_staff_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Title</label>
        <div class="kt-form-control">
            <input class="kt-input" name="title" type="text" value="{{ old('title', $employee?->title) }}">
        </div>
        @error('title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Email</label>
        <div class="kt-form-control">
            <input class="kt-input" name="email" type="email" value="{{ old('email', $employee?->email) }}">
        </div>
        @error('email') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Mobile</label>
        <div class="kt-form-control">
            <input class="kt-input" name="mobile" type="text" value="{{ old('mobile', $employee?->mobile) }}">
        </div>
        @error('mobile') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Grade</label>
        <div class="kt-form-control">
            <select class="kt-select" name="grade_id">
                <option value="">Select grade</option>
                @foreach($grades as $grade)
                    <option value="{{ $grade->id }}" @selected((int) old('grade_id', $employee?->grade_id) === $grade->id)>
                        {{ $grade->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('grade_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Center</label>
        <div class="kt-form-control">
            <select class="kt-select" name="center_id">
                <option value="">Select center</option>
                @foreach($centers as $center)
                    <option value="{{ $center->id }}" @selected((int) old('center_id', $employee?->center_id) === $center->id)>
                        {{ $center->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('center_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-5">
    <label class="flex items-center gap-2 text-sm text-foreground">
        <input class="kt-checkbox" name="is_active" type="checkbox" value="1" @checked((bool) old('is_active', $employee?->is_active ?? true))>
        <span>Active employee profile</span>
    </label>
    @error('is_active') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
</div>
