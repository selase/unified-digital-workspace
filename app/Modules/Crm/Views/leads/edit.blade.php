@extends('layouts.metronic.app')

@section('title', "Edit Lead {$lead->reference_code}")

@section('content')
<div class="max-w-2xl mx-auto flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-foreground">Edit Lead: {{ $lead->reference_code }}</h1>
            <p class="text-xs text-muted-foreground">Update lead contact information, inquiry classification, and owner assignment.</p>
        </div>
        <a href="{{ route('crm.leads.show', $lead) }}" class="kt-btn kt-btn-sm kt-btn-outline">Cancel</a>
    </div>

    <div class="kt-card p-6">
        <form action="{{ route('crm.leads.update', $lead) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="kt-form-label text-xs">Full Name *</label>
                <input type="text" name="full_name" value="{{ old('full_name', $lead->full_name) }}" class="kt-input" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="kt-form-label text-xs">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', $lead->email) }}" class="kt-input">
                </div>
                <div>
                    <label class="kt-form-label text-xs">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone', $lead->phone) }}" class="kt-input">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="kt-form-label text-xs">Organisation</label>
                    <input type="text" name="organisation" value="{{ old('organisation', $lead->organisation) }}" class="kt-input">
                </div>
                <div>
                    <label class="kt-form-label text-xs">Source *</label>
                    <select name="source" class="kt-select" required>
                        @foreach(['walk_in' => 'Office Walk-In', 'website_enquiry' => 'Website Form', 'property_listing' => 'Property Listing', 'referral' => 'Partner Referral', 'direct' => 'Direct Outreach'] as $src => $label)
                            <option value="{{ $src }}" @selected(old('source', $lead->source) === $src)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="kt-form-label text-xs">Enquiry Classification</label>
                    <input type="text" name="enquiry_type" value="{{ old('enquiry_type', $lead->enquiry_type) }}" class="kt-input">
                </div>
                <div>
                    <label class="kt-form-label text-xs">Assigned Consultant</label>
                    <select name="assigned_to_id" class="kt-select">
                        <option value="">Unassigned</option>
                        @foreach($users as $u)
                            <option value="{{ $u->uuid }}" @selected(old('assigned_to_id', $lead->assigned_to_id) === (string)$u->uuid)>
                                {{ $u->first_name }} {{ $u->last_name }} ({{ $u->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="kt-form-label text-xs">Inquiry Details / Message</label>
                <textarea name="message" rows="3" class="kt-textarea">{{ old('message', $lead->message) }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                <a href="{{ route('crm.leads.show', $lead) }}" class="kt-btn kt-btn-outline">Cancel</a>
                <button type="submit" class="kt-btn kt-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

