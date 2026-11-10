@extends('layouts.metronic.app')

@section('title', 'Create Automation Rule')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Create Automation Rule</h1>
            <p class="text-xs text-muted-foreground">Set up an automatic action for when something happens in the workspace.</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('workflow-automation.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Back to rules</a></div>
    </div>

    @if ($errors->any())
        <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ route('workflow-automation.store') }}" method="POST" class="flex flex-col gap-6">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">1. When this happens</h3></div>
                <div class="kt-card-content flex flex-col gap-4">
                    <label class="flex flex-col gap-1 text-sm font-medium">Rule name<input type="text" name="name" class="kt-input" placeholder="e.g. Escalate critical incident to directors" required value="{{ old('name') }}"></label>
                    <label class="flex flex-col gap-1 text-sm font-medium">Description (optional)<textarea name="description" class="kt-input" rows="2" placeholder="What this automates">{{ old('description') }}</textarea></label>
                    <label class="flex flex-col gap-1 text-sm font-medium">Event
                        <select name="trigger_event" class="kt-select" required>
                            <option value="">Choose an event</option>
                            @foreach ($availableEvents as $evtKey => $evtLabel)<option value="{{ $evtKey }}" {{ old('trigger_event') === $evtKey ? 'selected' : '' }}>{{ $evtLabel }} ({{ $evtKey }})</option>@endforeach
                        </select>
                    </label>
                    <p class="rounded-lg bg-muted/30 p-3 text-xs text-muted-foreground">Safety limit: rules can trigger other rules only 3 levels deep, so a loop can never run away.</p>
                </div>
            </div>

            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">2. Only if (optional)</h3></div>
                <div class="kt-card-content flex flex-col gap-4">
                    <p class="text-xs text-muted-foreground">Leave blank to run every time the event happens.</p>
                    <label class="flex flex-col gap-1 text-sm font-medium">Field<input type="text" name="condition_field" class="kt-input" placeholder="e.g. impact, status, amount_cents" value="{{ old('condition_field') }}"></label>
                    <label class="flex flex-col gap-1 text-sm font-medium">Comparison
                        <select name="condition_operator" class="kt-select">
                            <option value="=" {{ old('condition_operator') === '=' ? 'selected' : '' }}>Equals (=)</option>
                            <option value="!=" {{ old('condition_operator') === '!=' ? 'selected' : '' }}>Not equals (!=)</option>
                            <option value=">" {{ old('condition_operator') === '>' ? 'selected' : '' }}>Greater than (&gt;)</option>
                            <option value="<" {{ old('condition_operator') === '<' ? 'selected' : '' }}>Less than (&lt;)</option>
                            <option value="contains" {{ old('condition_operator') === 'contains' ? 'selected' : '' }}>Contains (text)</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium">Value<input type="text" name="condition_value" class="kt-input" placeholder="e.g. critical, high, 1000" value="{{ old('condition_value') }}"></label>
                </div>
            </div>
        </div>

        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">3. Then do this</h3></div>
            <div class="kt-card-content grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="flex flex-col gap-1 text-sm font-medium">Action
                    <select name="action_type" class="kt-select" required>
                        <option value="dispatch_notification">Send an in-app notification</option>
                        <option value="log_audit_alert">Log an audit alert</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium">Who gets it (role)<input type="text" name="target_role" class="kt-input" placeholder="e.g. Super Admin, Org Admin" value="{{ old('target_role', 'Super Admin') }}"></label>
                <label class="flex flex-col gap-1 text-sm font-medium">Title<input type="text" name="title_template" class="kt-input" placeholder="e.g. Critical safety alert: {title}" value="{{ old('title_template', 'Urgent Alert: {title}') }}"></label>
                <label class="flex flex-col gap-1 text-sm font-medium md:col-span-3">Message
                    <textarea name="body_template" class="kt-input" rows="2" placeholder="e.g. A critical incident was logged with impact {impact}.">{{ old('body_template', 'Automated workflow notification from {rule_name}') }}</textarea>
                    <span class="text-xs font-normal text-muted-foreground">Placeholders like {field}, {rule_name} and {event} are replaced with details of the event.</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('workflow-automation.index') }}" class="kt-btn kt-btn-outline">Cancel</a>
            <button type="submit" class="kt-btn kt-btn-primary">Save rule</button>
        </div>
    </form>
</div>
@endsection
