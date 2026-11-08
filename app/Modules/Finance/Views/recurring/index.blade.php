@extends('layouts.metronic.app')

@section('title', 'Recurring invoices')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6" x-data="{ lines: [{description: '', quantity: 1, unit_price: '', tax_id: ''}] }">
    <div>
        <h1 class="text-xl font-bold text-foreground">Recurring invoices</h1>
        <p class="text-xs text-muted-foreground">A schedule that creates an invoice for a customer each period. Runs daily.</p>
    </div>

    @include('finance::partials.nav')

    @can('finance.invoices.manage')
    <form method="POST" action="{{ route('finance.recurring.store') }}" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">New schedule</h3></div>
        <div class="kt-card-content flex flex-col gap-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input class="kt-input" name="name" value="{{ old('name') }}" placeholder="Name, e.g. Monthly hosting" required>
                <select class="kt-select" name="contact_id" required>
                    <option value="">Customer</option>
                    @foreach($contacts as $contact)<option value="{{ $contact->id }}" @selected((int) old('contact_id') === $contact->id)>{{ $contact->name }}</option>@endforeach
                </select>
                <select class="kt-select" name="frequency">
                    @foreach($frequencies as $key => $label)<option value="{{ $key }}" @selected(old('frequency', 'monthly') === $key)>{{ $label }}</option>@endforeach
                </select>
                <input class="kt-input" type="date" name="next_run_date" value="{{ old('next_run_date', today()->toDateString()) }}" title="First invoice date" required>
                <input class="kt-input" type="date" name="end_date" value="{{ old('end_date') }}" title="Stop after (optional)">
                <input class="kt-input" type="number" min="0" max="365" name="terms_days" value="{{ old('terms_days', $settings->default_payment_terms_days) }}" title="Days until due" required>
            </div>
            <template x-for="(line, index) in lines" :key="index">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
                    <input class="kt-input md:col-span-6" :name="`lines[${index}][description]`" x-model="line.description" placeholder="Description" required>
                    <input class="kt-input md:col-span-2" type="number" step="0.001" min="0.001" :name="`lines[${index}][quantity]`" x-model="line.quantity" required>
                    <input class="kt-input md:col-span-2" type="number" step="0.01" min="0" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" placeholder="Price" required>
                    @if($taxes->isNotEmpty())
                    <select class="kt-select md:col-span-1" :name="`lines[${index}][tax_id]`" x-model="line.tax_id">
                        <option value="">No tax</option>
                        @foreach($taxes as $tax)<option value="{{ $tax->id }}">{{ $tax->name }}</option>@endforeach
                    </select>
                    @endif
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" x-show="lines.length > 1" @click="lines.splice(index, 1)">×</button>
                </div>
            </template>
            <div class="flex items-center justify-between">
                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="lines.push({description: '', quantity: 1, unit_price: '', tax_id: ''})">Add line</button>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="auto_send" value="1" class="kt-checkbox"> Issue automatically (otherwise it is created as a draft)</label>
            </div>
            <textarea class="kt-input" name="notes" rows="2" placeholder="Note to the customer">{{ old('notes') }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Schedule</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Name</th><th>Customer</th><th>Every</th><th>Next invoice</th><th>Issue</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($templates as $template)
                    <tr>
                        <td>{{ $template->name }}</td>
                        <td>{{ $template->contact->name }}</td>
                        <td>{{ $frequencies[$template->frequency] ?? $template->frequency }}</td>
                        <td>{{ $template->next_run_date->toInstitutionalDate() }}</td>
                        <td>{{ $template->auto_send ? 'Automatic' : 'As draft' }}</td>
                        <td>{{ $template->is_active ? 'Active' : 'Paused' }}</td>
                        <td class="text-end">
                            @can('finance.invoices.manage')
                            <form method="POST" action="{{ route('finance.recurring.toggle', $template->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">{{ $template->is_active ? 'Pause' : 'Resume' }}</button></form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-foreground py-6">No schedules yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
