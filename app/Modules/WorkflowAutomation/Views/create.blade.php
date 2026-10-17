@extends('layouts.metronic.app')

@section('title', 'Create Automation Rule')

@section('content')
<div class="d-flex flex-column gap-7">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Create Automation Rule"
        description="Set up an automated action triggered when specific events occur in the system.">
        <x-slot:actions>
            <a href="{{ route('workflow-automation.index') }}" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-arrow-left fs-2"></i> Back to Rules
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if ($errors->any())
        <div class="alert alert-danger p-5 mb-5">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('workflow-automation.store') }}" method="POST">
        @csrf

        <div class="row g-7">
            {{-- 1. Trigger Event Card --}}
            <div class="col-lg-6">
                <x-metronic.card title="1. Trigger Event">
                    <div class="mb-5">
                        <label class="form-label required fw-bold">Rule Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Escalate Critical Incident to Directors" required value="{{ old('name') }}">
                    </div>

                    <div class="mb-5">
                        <label class="form-label fw-bold">Description (Optional)</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Explain what business process this automates...">{{ old('description') }}</textarea>
                    </div>

                    <div class="mb-5">
                        <label class="form-label required fw-bold">When this Event occurs</label>
                        <select name="trigger_event" class="form-select" required>
                            <option value="">-- Choose System Event --</option>
                            @foreach ($availableEvents as $evtKey => $evtLabel)
                                <option value="{{ $evtKey }}" {{ old('trigger_event') === $evtKey ? 'selected' : '' }}>
                                    {{ $evtLabel }} ({{ $evtKey }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-4">
                        <i class="ki-duotone ki-shield-tick fs-2x text-primary me-3"></i>
                        <div class="fs-7 text-gray-700">
                            <strong>Recursion Circuit Breaker:</strong> The workflow engine enforces a maximum cascading depth of 3 to guarantee infinite loops or cyclic event chains cannot crash the system.
                        </div>
                    </div>
                </x-metronic.card>
            </div>

            {{-- 2. Condition Evaluation Card --}}
            <div class="col-lg-6">
                <x-metronic.card title="2. If Conditions Match (Optional)">
                    <p class="text-muted fs-7 mb-4">Leave blank to always execute whenever the event occurs.</p>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Payload Field</label>
                        <input type="text" name="condition_field" class="form-control" placeholder="e.g. impact, status, amount_cents" value="{{ old('condition_field') }}">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Comparison Operator</label>
                        <select name="condition_operator" class="form-select">
                            <option value="=" {{ old('condition_operator') === '=' ? 'selected' : '' }}>Equals (=)</option>
                            <option value="!=" {{ old('condition_operator') === '!=' ? 'selected' : '' }}>Not Equals (!=)</option>
                            <option value=">" {{ old('condition_operator') === '>' ? 'selected' : '' }}>Greater Than (&gt;)</option>
                            <option value="<" {{ old('condition_operator') === '<' ? 'selected' : '' }}>Less Than (&lt;)</option>
                            <option value="contains" {{ old('condition_operator') === 'contains' ? 'selected' : '' }}>Contains (text)</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Expected Value</label>
                        <input type="text" name="condition_value" class="form-control" placeholder="e.g. critical, high, 1000" value="{{ old('condition_value') }}">
                    </div>
                </x-metronic.card>
            </div>

            {{-- 3. Action Execution Card --}}
            <div class="col-12">
                <x-metronic.card title="3. Then Execute Action">
                    <div class="row g-5">
                        <div class="col-md-4">
                            <label class="form-label required fw-bold">Action Type</label>
                            <select name="action_type" class="form-select" required>
                                <option value="dispatch_notification">Dispatch Topbar In-App Notification</option>
                                <option value="log_audit_alert">Log Institutional Audit Alert</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Target Role / Recipients</label>
                            <input type="text" name="target_role" class="form-control" placeholder="e.g. Super Admin, Org Admin" value="{{ old('target_role', 'Super Admin') }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Notification Title Template</label>
                            <input type="text" name="title_template" class="form-control" placeholder="e.g. Critical Safety Alert: {title}" value="{{ old('title_template', 'Urgent Alert: {title}') }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Message Body Template</label>
                            <textarea name="body_template" class="form-control" rows="2" placeholder="e.g. A critical incident was logged with impact {impact}. Please review immediately.">{{ old('body_template', 'Automated workflow notification from {rule_name}') }}</textarea>
                            <div class="form-text">Placeholders like <code>{field}</code>, <code>{rule_name}</code>, and <code>{event}</code> are dynamically replaced with event context.</div>
                        </div>
                    </div>
                </x-metronic.card>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-3 mt-7">
            <a href="{{ route('workflow-automation.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="ki-duotone ki-check fs-2"></i> Save Automation Rule
            </button>
        </div>
    </form>
</div>
@endsection

