@php
    /** @var \App\Modules\IncidentManagement\Models\Incident|null $incident */
    $incident = $incident ?? null;
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div class="kt-form-item lg:col-span-2">
        <label class="kt-form-label">Title <span class="text-destructive">*</span></label>
        <div class="kt-form-control">
            <input class="kt-input" name="title" required type="text" value="{{ old('title', $incident?->title) }}">
        </div>
        @error('title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item lg:col-span-2">
        <label class="kt-form-label">Description <span class="text-destructive">*</span></label>
        <div class="kt-form-control">
            <textarea class="kt-textarea min-h-[180px]" name="description" required>{{ old('description', $incident?->description) }}</textarea>
        </div>
        @error('description') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Category</label>
        <div class="kt-form-control">
            <select class="kt-select" name="category_id">
                <option value="">Select category</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) old('category_id', $incident?->category_id) === $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('category_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Priority</label>
        <div class="kt-form-control">
            <select class="kt-select" name="priority_id">
                <option value="">Select priority</option>
                @foreach($priorities as $priority)
                    <option value="{{ $priority->id }}" @selected((int) old('priority_id', $incident?->priority_id) === $priority->id)>
                        {{ $priority->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('priority_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Status</label>
        <div class="kt-form-control">
            <select class="kt-select" name="status_id">
                <option value="">Use default status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected((int) old('status_id', $incident?->status_id) === $status->id)>
                        {{ $status->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('status_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Assignee</label>
        <div class="kt-form-control">
            <select class="kt-select" name="assigned_to_id">
                <option value="">Unassigned</option>
                @foreach($assignees as $assignee)
                    <option value="{{ $assignee->uuid }}" @selected((string) old('assigned_to_id', $incident?->assigned_to_id) === (string) $assignee->uuid)>
                        {{ $assignee->displayName() }} ({{ $assignee->email }})
                    </option>
                @endforeach
            </select>
        </div>
        @error('assigned_to_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Due At</label>
        <div class="kt-form-control">
            <input
                class="kt-input"
                name="due_at"
                type="datetime-local"
                value="{{ old('due_at', $incident?->due_at?->format('Y-m-d\TH:i')) }}"
            >
        </div>
        @error('due_at') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Source</label>
        <div class="kt-form-control">
            <input class="kt-input" name="source" type="text" value="{{ old('source', $incident?->source) }}">
        </div>
        @error('source') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Impact</label>
        <div class="kt-form-control">
            <input class="kt-input" name="impact" type="text" value="{{ old('impact', $incident?->impact) }}">
        </div>
        @error('impact') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>
</div>
