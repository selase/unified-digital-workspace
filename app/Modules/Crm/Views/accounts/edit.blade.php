@extends('layouts.metronic.app')

@section('title', "Edit Account {$account->code}")

@section('content')
<div class="max-w-2xl mx-auto flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-foreground">Edit Client Account: {{ $account->code }}</h1>
            <p class="text-xs text-muted-foreground">Modify client organizational details, classification, and contact channel.</p>
        </div>
        <a href="{{ route('crm.accounts.show', $account) }}" class="kt-btn kt-btn-sm kt-btn-outline">Cancel</a>
    </div>

    <div class="kt-card p-6">
        <form action="{{ route('crm.accounts.update', $account) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="kt-form-label text-xs">Account / Organisation Name *</label>
                <input type="text" name="name" value="{{ old('name', $account->name) }}" class="kt-input" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="kt-form-label text-xs">Account Type *</label>
                    <select name="account_type" class="kt-select" required>
                        @foreach(['corporate' => 'Corporate Entity', 'individual' => 'Individual Investor', 'government' => 'Government / Parastatal', 'partner' => 'Strategic Partner'] as $type => $label)
                            <option value="{{ $type }}" @selected(old('account_type', $account->account_type) === $type)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="kt-form-label text-xs">Industry</label>
                    <input type="text" name="industry" value="{{ old('industry', $account->industry) }}" class="kt-input" placeholder="e.g. Healthcare">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="kt-form-label text-xs">Primary Email</label>
                    <input type="email" name="email" value="{{ old('email', $account->email) }}" class="kt-input">
                </div>
                <div>
                    <label class="kt-form-label text-xs">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone', $account->phone) }}" class="kt-input">
                </div>
            </div>

            <div>
                <label class="kt-form-label text-xs">Physical / Office Address</label>
                <input type="text" name="address" value="{{ old('address', $account->address) }}" class="kt-input">
            </div>

            <div>
                <label class="kt-form-label text-xs">Relationship Status *</label>
                <select name="status" class="kt-select" required>
                    <option value="active" @selected(old('status', $account->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $account->status) === 'inactive')>Inactive</option>
                    <option value="suspended" @selected(old('status', $account->status) === 'suspended')>Suspended</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                <a href="{{ route('crm.accounts.show', $account) }}" class="kt-btn kt-btn-outline">Cancel</a>
                <button type="submit" class="kt-btn kt-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

