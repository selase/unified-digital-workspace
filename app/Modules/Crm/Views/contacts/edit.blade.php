@extends('layouts.metronic.app')

@section('title', "Edit Contact: {$contact->fullName()}")

@section('content')
<div class="max-w-2xl mx-auto flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-foreground">Edit Contact Person</h1>
            <p class="text-xs text-muted-foreground">
                Updating details for {{ $contact->fullName() }}
                @if($account)
                    associated with <span class="font-semibold text-foreground">{{ $account->name }}</span> ({{ $account->code }})
                @endif
            </p>
        </div>
        <a href="{{ $account ? route('crm.accounts.show', $account) : route('crm.accounts.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Cancel</a>
    </div>

    <div class="kt-card p-6">
        <form action="{{ route('crm.contacts.update', $contact) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="kt-form-label text-xs">First Name *</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $contact->first_name) }}" class="kt-input" required>
                    @error('first_name')
                        <span class="text-xs text-destructive">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label class="kt-form-label text-xs">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $contact->last_name) }}" class="kt-input">
                    @error('last_name')
                        <span class="text-xs text-destructive">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="kt-form-label text-xs">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', $contact->email) }}" class="kt-input">
                    @error('email')
                        <span class="text-xs text-destructive">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label class="kt-form-label text-xs">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone', $contact->phone) }}" class="kt-input">
                    @error('phone')
                        <span class="text-xs text-destructive">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div>
                <label class="kt-form-label text-xs">Job Title / Designation</label>
                <input type="text" name="job_title" value="{{ old('job_title', $contact->job_title) }}" class="kt-input" placeholder="e.g. Managing Director">
                @error('job_title')
                    <span class="text-xs text-destructive">{{ $message }}</span>
                @enderror
            </div>

            <div class="pt-2">
                <label class="inline-flex items-center gap-2 text-xs text-foreground cursor-pointer">
                    <input type="checkbox" name="is_primary" value="1" class="kt-checkbox" @checked(old('is_primary', $contact->is_primary))>
                    <span>Designate as primary contact for this client account</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                <a href="{{ $account ? route('crm.accounts.show', $account) : route('crm.accounts.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Cancel</a>
                <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Save Contact Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

