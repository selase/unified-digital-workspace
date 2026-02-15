<div>
    @if (session()->has('success'))
        <div class="mb-5 rounded-lg border border-success/40 bg-success/10 px-4 py-3 text-sm text-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-xl border border-border bg-background p-6">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Two-Factor Authentication</h2>
            <p class="mt-1 text-sm text-muted-foreground">Extra security for your account using TOTP.</p>
        </div>

        <div class="mt-6">
            @if (!$user->two_factor_secret)
                @if (!$showQrCode)
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs uppercase text-muted-foreground">Status</p>
                            <p class="text-sm font-semibold text-destructive">Disabled</p>
                        </div>
                        <button wire:click="enableTwoFactor" class="kt-btn kt-btn-primary kt-btn-sm">Enable 2FA</button>
                    </div>
                @else
                    <div class="rounded-lg border border-border bg-muted/30 p-5 text-center">
                        <h3 class="text-base font-semibold text-foreground">Configure Authenticator App</h3>
                        <div class="my-5 flex justify-center">
                            {!! (new \PragmaRX\Google2FALaravel\Support\Authenticator(request()))->getQRCodeInline(
                                config('app.name'),
                                $user->email,
                                $secret
                            ) !!}
                        </div>
                        <p class="mx-auto mb-5 max-w-xl text-sm text-muted-foreground">
                            Scan the QR code with your authenticator app (like Google Authenticator or Authy) and enter the 6-digit code below.
                        </p>
                        <div class="mx-auto max-w-56">
                            <input type="text" wire:model="code" class="kt-input text-center" placeholder="000000">
                            @error('code')
                                <span class="mt-2 block text-xs text-destructive">{{ $message }}</span>
                            @enderror
                            <button wire:click="confirmTwoFactor" class="kt-btn kt-btn-primary mt-3 w-full">Confirm & Enable</button>
                            <button wire:click="$set('showQrCode', false)" class="kt-btn kt-btn-outline kt-btn-sm mt-2">Cancel</button>
                        </div>
                    </div>
                @endif
            @else
                <div x-data="{ confirmDisable: false }">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs uppercase text-muted-foreground">Status</p>
                            <p class="text-sm font-semibold text-success">Enabled</p>
                            <p class="text-xs text-muted-foreground">Activated on {{ $user->two_factor_confirmed_at?->format('M d, Y H:i') }}</p>
                        </div>
                        <button type="button" class="kt-btn kt-btn-outline kt-btn-sm" x-on:click="confirmDisable = !confirmDisable">
                            Disable 2FA
                        </button>
                    </div>

                    <div class="mt-5" x-show="confirmDisable" x-cloak>
                        <div class="rounded-lg border border-destructive/50 bg-destructive/5 p-5">
                            <h4 class="text-sm font-semibold text-destructive">Confirm Deactivation</h4>
                            <p class="mt-2 text-xs text-muted-foreground">By disabling two-factor authentication, your account will only be protected by your password.</p>
                            <div class="mt-4 flex flex-wrap items-end gap-3">
                                <div class="min-w-64 grow space-y-2">
                                    <label class="kt-label text-xs font-semibold text-foreground">Enter Password to Confirm</label>
                                    <input type="password" wire:model="password" class="kt-input">
                                    @error('password')
                                        <span class="block text-xs text-destructive">{{ $message }}</span>
                                    @enderror
                                </div>
                                <button wire:click="disableTwoFactor" class="kt-btn kt-btn-danger kt-btn-sm">Confirm Disable</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
