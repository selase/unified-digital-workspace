@extends('layouts.metronic.app')

@section('title', __('Merchant Payment Settings'))

@section('content')
    @php
        $activeProviders = collect([$stripe?->is_active, $paystack?->is_active])->filter()->count();
        $configuredProviders = collect([$stripe, $paystack])->filter()->count();
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Settings</p>
                <h1 class="mt-2 text-2xl font-semibold text-foreground">Merchant Payment Settings</h1>
                <p class="mt-2 text-sm text-muted-foreground">Configure payment providers and webhook endpoints.</p>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Providers Connected</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $configuredProviders }} / 2</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Providers Active</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $activeProviders }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Webhooks</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">Tenant Scoped</p>
                </div>
            </div>
        </div>

        @if(session('status') === 'success')
            <div class="rounded-lg border border-border bg-muted p-4">
                <div class="flex items-center gap-3">
                    <span class="kt-badge kt-badge-success">Success</span>
                    <span class="text-sm text-foreground">{{ session('message') }}</span>
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-border bg-background p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Stripe Integration</h2>
                        <p class="text-xs text-muted-foreground">Connect your Stripe account to receive payments.</p>
                    </div>
                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/ba/Stripe_Logo%2C_revised_2016.svg" class="h-5" alt="Stripe" />
                </div>

                <form action="{{ route('tenant.settings.payments.update') }}" method="POST" class="mt-6 grid gap-5">
                    @csrf
                    <input type="hidden" name="provider" value="stripe">

                    <div class="kt-form-item">
                        <label class="kt-form-label">Secret Key</label>
                        <div class="kt-form-control">
                            <input type="password" name="api_key" class="kt-input @error('api_key') !border-destructive @enderror" placeholder="sk_live_..." value="{{ $stripe ? '********' : '' }}" />
                            <p class="mt-2 text-xs text-muted-foreground">Your Stripe Secret Key (sk_live_... or sk_test_...).</p>
                            @error('api_key')
                                <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Publishable Key</label>
                        <div class="kt-form-control">
                            <input type="text" name="public_key" class="kt-input" placeholder="pk_live_..." value="{{ $stripe->public_key_encrypted ?? '' }}" />
                            <p class="mt-2 text-xs text-muted-foreground">Used for client-side checkout integrations.</p>
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Webhook Signing Secret</label>
                        <div class="kt-form-control">
                            <input type="password" name="webhook_secret" class="kt-input" placeholder="whsec_..." value="{{ $stripe ? '********' : '' }}" />
                            <p class="mt-2 text-xs text-muted-foreground">Used to verify that events are sent by Stripe.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-sm text-foreground">Active Status</div>
                            <div class="text-xs text-muted-foreground">Allow customers to pay via Stripe.</div>
                        </div>
                        <input class="kt-switch" type="checkbox" name="is_active" value="1" {{ $stripe?->is_active ? 'checked' : '' }} />
                    </div>

                    <div class="border-t border-border pt-4">
                        <label class="kt-form-label">Webhook Endpoint URL</label>
                        <div class="kt-form-control">
                            <div class="flex items-center gap-2">
                                <input type="text" class="kt-input flex-1" readonly value="{{ config('app.url') }}/webhooks/merchant/stripe/{{ $tenant->id }}" />
                                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline copy-webhook-btn">Copy</button>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">Copy this URL to your Stripe Dashboard > Developers > Webhooks.</p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="kt-btn kt-btn-primary">Save Stripe Settings</button>
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-border bg-background p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Paystack Integration</h2>
                        <p class="text-xs text-muted-foreground">Enable Paystack for local and international payments.</p>
                    </div>
                    <img src="https://upload.wikimedia.org/wikipedia/commons/0/0b/Paystack_Logo.png" class="h-5" alt="Paystack" />
                </div>

                <form action="{{ route('tenant.settings.payments.update') }}" method="POST" class="mt-6 grid gap-5">
                    @csrf
                    <input type="hidden" name="provider" value="paystack">

                    <div class="kt-form-item">
                        <label class="kt-form-label">Secret Key</label>
                        <div class="kt-form-control">
                            <input type="password" name="api_key" class="kt-input @error('api_key') !border-destructive @enderror" placeholder="sk_live_..." value="{{ $paystack ? '********' : '' }}" />
                            <p class="mt-2 text-xs text-muted-foreground">Your Paystack Secret Key.</p>
                            @error('api_key')
                                <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Public Key</label>
                        <div class="kt-form-control">
                            <input type="text" name="public_key" class="kt-input" placeholder="pk_live_..." value="{{ $paystack->public_key_encrypted ?? '' }}" />
                            <p class="mt-2 text-xs text-muted-foreground">Used for inline or popup checkout.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-sm text-foreground">Active Status</div>
                            <div class="text-xs text-muted-foreground">Allow customers to pay via Paystack.</div>
                        </div>
                        <input class="kt-switch" type="checkbox" name="is_active" value="1" {{ $paystack?->is_active ? 'checked' : '' }} />
                    </div>

                    <div class="border-t border-border pt-4">
                        <label class="kt-form-label">Webhook Endpoint URL</label>
                        <div class="kt-form-control">
                            <div class="flex items-center gap-2">
                                <input type="text" class="kt-input flex-1" readonly value="{{ config('app.url') }}/webhooks/merchant/paystack/{{ $tenant->id }}" />
                                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline copy-webhook-btn">Copy</button>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">Paste this URL in your Paystack Dashboard > Settings > API Keys & Webhooks.</p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="kt-btn kt-btn-primary">Save Paystack Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@push('custom-scripts')
    <script>
        const copyTextValue = async (value) => {
            if (!value) {
                return false;
            }

            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(value);
                return true;
            }

            return false;
        };

        document.querySelectorAll('.copy-webhook-btn').forEach((button) => {
            button.addEventListener('click', async () => {
                const source = button.previousElementSibling;
                const copied = await copyTextValue(source?.value || '');

                if (typeof toastr !== 'undefined') {
                    toastr[copied ? 'success' : 'error'](copied ? 'Webhook URL copied' : 'Unable to copy webhook URL');
                }
            });
        });
    </script>
@endpush
