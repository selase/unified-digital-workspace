@php
    $formAction = $topupRoute ?? (Route::has('tenants.subscriptions.admin-add-credit')
        ? route('tenants.subscriptions.admin-add-credit', $subscription->uuid ?? $subscription->id)
        : null);
@endphp

<x-modal id="admin_topup_tenant_credit" title="Add Credit" type="sm">
    <form action="{{ $formAction ?? '#' }}" method="post" id="adminTopupTenantCreditForm" class="space-y-6">
        @csrf

        <div class="space-y-4">
            <div class="kt-form-item">
                <label for="amount" class="kt-form-label">{{ __('locale.labels.amount') }}</label>
                <div class="kt-form-control">
                    <input
                        type="number"
                        name="amount"
                        id="amount"
                        class="kt-input @error('amount') !border-destructive @enderror"
                        value="{{ old('amount') }}"
                        placeholder="Ex: 10"
                    />
                    @error('amount')
                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="kt-form-item">
                <label for="credit" class="kt-form-label">{{ __('locale.labels.credit') }}</label>
                <div class="kt-form-control">
                    <input
                        type="number"
                        name="credit"
                        id="credit"
                        class="kt-input @error('credit') !border-destructive @enderror"
                        value="{{ old('credit') }}"
                        placeholder="Ex: 1000"
                    />
                    @error('credit')
                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-border pt-4">
            <button type="button" class="kt-btn kt-btn-outline" data-kt-modal-dismiss="true">Cancel</button>
            <button type="submit" class="kt-btn kt-btn-primary" id="adminTopupTenantCreditButton" {{ $formAction ? '' : 'disabled' }}>
                {{ __('locale.labels.save_and_continue') }}
            </button>
        </div>
    </form>
</x-modal>
