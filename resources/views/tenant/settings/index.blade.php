@extends('layouts.metronic.app')

@section('title', __('Organization Settings'))

@section('content')
    @php
        $profileCompletion = collect([
            filled($tenant->name),
            filled($tenant->email),
            filled($tenant->phone_number),
            filled($tenant->logo),
        ])->filter()->count();
        $profileCompletionPercent = (int) round(($profileCompletion / 4) * 100);
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Settings</p>
                <h1 class="mt-2 text-2xl font-semibold text-foreground">Organization Settings</h1>
                <p class="mt-2 text-sm text-muted-foreground">Update your organization profile, security, and branding.</p>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Profile Completion</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $profileCompletionPercent }}%</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Security Policy</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $tenant->require_2fa ? '2FA Required' : 'Optional' }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Custom Domain</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $tenant->custom_domain ? 'Configured' : 'Not Set' }}</p>
                </div>
            </div>
        </div>

        <form action="{{ route('tenant.settings.update') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 rounded-xl border border-border bg-background p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Organization Profile</h2>
                            <p class="text-xs text-muted-foreground">Update your organization information and branding.</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-6">
                        <div class="flex flex-col items-center text-center">
                            <div class="size-24 rounded-full bg-muted overflow-hidden">
                                @if($tenant->logo)
                                    <img src="{{ Storage::url($tenant->logo) }}" alt="Logo" class="size-24 object-cover" />
                                @else
                                    <img src="{{ $tenant->gravatar }}" alt="Logo" class="size-24 object-cover" />
                                @endif
                            </div>
                            <div class="mt-4 w-full max-w-md">
                                <label for="logo" class="kt-form-label">Change Logo</label>
                                <input type="file" name="logo" class="kt-input @error('logo') !border-destructive @enderror" />
                                @error('logo')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-2">
                            <div class="kt-form-item">
                                <label for="name" class="kt-form-label">Organization Name</label>
                                <div class="kt-form-control">
                                    <input type="text" name="name" class="kt-input @error('name') !border-destructive @enderror" value="{{ old('name', $tenant->name) }}" />
                                    @error('name')
                                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="kt-form-item">
                                <label for="primary_color" class="kt-form-label">Primary Branding Color</label>
                                <div class="kt-form-control">
                                    <input type="color" name="primary_color" class="kt-input h-12 w-24 @error('primary_color') !border-destructive @enderror" value="{{ old('primary_color', $tenant->meta['primary_color'] ?? '#009EF7') }}" />
                                    @error('primary_color')
                                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-2">
                            <div class="kt-form-item">
                                <label for="email" class="kt-form-label">Support Email</label>
                                <div class="kt-form-control">
                                    <input type="email" name="email" class="kt-input @error('email') !border-destructive @enderror" value="{{ old('email', $tenant->email) }}" />
                                    @error('email')
                                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="kt-form-item">
                                <label for="phone_number" class="kt-form-label">Support Phone</label>
                                <div class="kt-form-control">
                                    <input type="text" name="phone_number" class="kt-input @error('phone_number') !border-destructive @enderror" value="{{ old('phone_number', $tenant->phone_number) }}" />
                                    @error('phone_number')
                                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="kt-btn kt-btn-primary">Save Changes</button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6">
                    <div class="rounded-xl border border-border bg-background p-6">
                        <h3 class="text-base font-semibold text-foreground">Plan Information</h3>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase text-muted-foreground">Current Plan</span>
                                <span class="kt-badge kt-badge-primary">{{ $tenant->package->name ?? 'None' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase text-muted-foreground">Status</span>
                                <span class="kt-badge {{ $tenant->status->value === 'active' ? 'kt-badge-success' : 'kt-badge-warning' }}">
                                    {{ ucfirst($tenant->status->value) }}
                                </span>
                            </div>
                        </div>
                        <div class="mt-4 text-xs text-muted-foreground">
                            Want more features? Upgrade your plan to unlock custom domains, commerce, and higher usage limits.
                        </div>
                        <a href="{{ route('tenant.pricing') }}" class="kt-btn kt-btn-sm kt-btn-outline mt-4 w-full">Choose Plan</a>
                    </div>

                    <div class="rounded-xl border border-border bg-background p-6">
                        <h3 class="text-base font-semibold text-foreground">Security Enforcement</h3>
                        <div class="mt-4 flex items-center justify-between gap-4">
                            <div>
                                <div class="text-sm text-foreground">Require 2FA for all members</div>
                                <div class="text-xs text-muted-foreground">All users must configure Two-Factor Authentication.</div>
                            </div>
                            <input class="kt-switch" type="checkbox" name="require_2fa" value="1" id="require_2fa" {{ $tenant->require_2fa ? 'checked' : '' }} />
                        </div>
                    </div>

                    @feature(\App\Services\Tenancy\FeatureService::FEATURE_CUSTOM_DOMAINS)
                        <div class="rounded-xl border border-border bg-background p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-foreground">Custom Domain</h3>
                                    <p class="text-xs text-muted-foreground">Attach a custom domain to this tenant.</p>
                                </div>
                                <span class="kt-badge {{ $tenant->custom_domain_status === 'active' ? 'kt-badge-success' : 'kt-badge-warning' }}">
                                    {{ ucfirst($tenant->custom_domain_status) }}
                                </span>
                            </div>
                            <div class="mt-4">
                                <label class="kt-form-label">Your Domain</label>
                                <input type="text" name="custom_domain" class="kt-input" placeholder="app.yourdomain.com" value="{{ old('custom_domain', $tenant->custom_domain) }}" />
                                <div class="mt-2 text-xs text-muted-foreground">
                                    Point a <code>CNAME</code> record for this domain to <code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code>
                                </div>
                            </div>

                            @if($tenant->custom_domain && $tenant->custom_domain_status !== 'active')
                                <div class="mt-4 rounded-lg border border-border bg-muted p-4 text-xs text-muted-foreground">
                                    Domain pending verification. DNS changes can take up to 24 hours to propagate.
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-xl border border-border bg-background p-6 opacity-70">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-foreground">Custom Domain</h3>
                                    <p class="text-xs text-muted-foreground">Available on the Scale plan and above.</p>
                                </div>
                                <span class="kt-badge kt-badge-primary">PRO</span>
                            </div>
                            <a href="#" class="kt-btn kt-btn-sm kt-btn-outline mt-4 w-full opacity-60 pointer-events-none">Upgrade to Unlock</a>
                        </div>
                    @endfeature

                    <div class="rounded-xl border border-border bg-background p-6">
                        <h3 class="text-base font-semibold text-foreground">Data Isolation</h3>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase text-muted-foreground">Mode</span>
                                <code class="text-xs">{{ strtoupper($tenant->isolation_mode) }}</code>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase text-muted-foreground">Driver</span>
                                <code class="text-xs">{{ strtoupper($tenant->db_driver) }}</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </section>
@endsection
