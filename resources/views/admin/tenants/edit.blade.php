@extends('layouts.metronic.app')

@section('title', __('locale.labels.edit_tenant'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Tenants</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Edit Tenant</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Update organization settings, rates, and data isolation.</p>
                </div>
                <a href="{{ route('tenants.show', $tenant->uuid) }}" class="kt-btn kt-btn-outline">View Tenant</a>
            </div>
        </div>

        <form action="{{ route('tenants.update', $tenant->uuid) }}" method="post" enctype="multipart/form-data" class="kt-form">
            @csrf
            @method('PUT')

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-border bg-background p-6">
                    <h2 class="text-lg font-semibold text-foreground">Tenant Profile</h2>
                    <p class="mt-2 text-sm text-muted-foreground">Core details and LLM restrictions.</p>

                    <div class="mt-6 grid gap-6 lg:grid-cols-2">
                        <div class="kt-form-item">
                            <label class="kt-form-label">{{ __('locale.labels.name') }} <span class="text-destructive">*</span></label>
                            <div class="kt-form-control">
                                <input type="text" name="name" class="kt-input" value="{{ old('name', $tenant->name) }}" @error('name') aria-invalid="true" @enderror />
                                @error('name')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item">
                            <label class="kt-form-label">{{ __('locale.labels.email') }} <span class="text-destructive">*</span></label>
                            <div class="kt-form-control">
                                <input type="text" name="email" class="kt-input" value="{{ old('email', $tenant->email) }}" @error('email') aria-invalid="true" @enderror />
                                @error('email')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item">
                            <label class="kt-form-label">{{ __('locale.labels.phone_number') }} <span class="text-destructive">*</span></label>
                            <div class="kt-form-control">
                                <input type="text" name="phone_number" class="kt-input" value="{{ old('phone_number', $tenant->phone_number) }}" @error('phone_number') aria-invalid="true" @enderror />
                                @error('phone_number')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item">
                            <label class="kt-form-label">{{ __('locale.labels.subdomain') }} <span class="text-destructive">*</span></label>
                            <div class="kt-form-control">
                                <input type="text" name="subdomain" class="kt-input" value="{{ old('subdomain', $tenant->slug) }}" @error('subdomain') aria-invalid="true" @enderror />
                                @error('subdomain')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item lg:col-span-2">
                            <label class="kt-form-label">{{ __('locale.labels.address') }} <span class="text-destructive">*</span></label>
                            <div class="kt-form-control">
                                <input type="text" name="address" class="kt-input" value="{{ old('address', $tenant->address) }}" @error('address') aria-invalid="true" @enderror />
                                @error('address')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item">
                            <label class="kt-form-label">{{ __('locale.labels.country') }} <span class="text-destructive">*</span></label>
                            <div class="kt-form-control">
                                <input type="text" name="country" class="kt-input" value="{{ old('country', $tenant->country) }}" @error('country') aria-invalid="true" @enderror />
                                @error('country')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item">
                            <label class="kt-form-label">{{ __('locale.labels.city') }}</label>
                            <div class="kt-form-control">
                                <input type="text" name="city" class="kt-input" value="{{ old('city', $tenant->city) }}" @error('city') aria-invalid="true" @enderror />
                                @error('city')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item">
                            <label class="kt-form-label">{{ __('locale.labels.state_or_region') }}</label>
                            <div class="kt-form-control">
                                <input type="text" name="state_or_region" class="kt-input" value="{{ old('state_or_region', $tenant->state) }}" @error('state_or_region') aria-invalid="true" @enderror />
                                @error('state_or_region')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item">
                            <label class="kt-form-label">{{ __('locale.labels.zipcode') }}</label>
                            <div class="kt-form-control">
                                <input type="text" name="zipcode" class="kt-input" value="{{ old('zipcode', $tenant->zipcode) }}" @error('zipcode') aria-invalid="true" @enderror />
                                @error('zipcode')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item">
                            <label class="kt-form-label">{{ __('locale.labels.status') }} <span class="text-destructive">*</span></label>
                            <div class="kt-form-control">
                                    <select name="status" id="status" class="kt-select" @error('status') aria-invalid="true" @enderror>
                                        <option value="">{{ __('locale.labels.select_an_option') }}</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->value }}" {{ old('status', $tenant->status?->value) == $status->value ? 'selected' : '' }}>
                                                {{ ucfirst($status->value) }}
                                            </option>
                                        @endforeach
                                    </select>
                                @error('status')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item">
                            <label class="kt-form-label">{{ __('locale.labels.logo') }}</label>
                            <div class="kt-form-control">
                                <input type="file" name="logo" class="kt-input" @error('logo') aria-invalid="true" @enderror />
                                @error('logo')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="kt-form-item lg:col-span-2">
                            <label class="kt-form-label">Subscription Plan</label>
                            <div class="kt-form-control">
                                <select name="package_id" id="package_id" class="kt-select" @error('package_id') aria-invalid="true" @enderror>
                                    <option value="">Select a Plan (Optional)</option>
                                    @foreach ($packages as $pkg)
                                        <option value="{{ $pkg->id }}" {{ old('package_id', $tenant->package_id) == $pkg->id ? 'selected' : '' }}>
                                            {{ $pkg->name }} ({{ $pkg->interval }} - ${{ $pkg->price }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('package_id')
                                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="flex items-center justify-between gap-4 rounded-lg border border-border p-3 text-sm text-foreground">
                                <span>{{ __('Enable Bring Your Own Key (BYOK)') }}</span>
                                <input class="kt-switch" type="checkbox" name="llm_byok" value="1" {{ $tenant->featureEnabled('llm_byok') ? 'checked' : '' }} />
                            </label>
                            <p class="mt-2 text-xs text-muted-foreground">Allows the tenant to configure their own LLM API keys.</p>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-border pt-6">
                        <h3 class="text-base font-semibold text-foreground">LLM Restrictions & Rate Limiting</h3>
                        <div class="mt-4 grid gap-6">
                            <div class="kt-form-item">
                                <label class="kt-form-label">Allowed LLM Models</label>
                                <div class="kt-form-control">
                                    <select name="llm_models_whitelist[]" class="kt-select" data-control="select2" data-placeholder="Select allowed models" data-allow-clear="true" multiple="multiple">
                                        @foreach($availableModels as $model)
                                            <option value="{{ $model }}" {{ in_array($model, $tenant->llm_models_whitelist ?? []) ? 'selected' : '' }}>{{ $model }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-2 text-xs text-muted-foreground">Leave empty to allow all system-wide models.</p>
                                </div>
                            </div>

                            <div class="kt-form-item">
                                <label class="kt-form-label">Allowed IP Addresses</label>
                                <div class="kt-form-control">
                                    <textarea name="allowed_ips" class="kt-textarea" rows="2" placeholder="e.g. 192.168.1.1, 10.0.0.1">{{ is_array($tenant->allowed_ips) ? implode(', ', $tenant->allowed_ips) : $tenant->allowed_ips }}</textarea>
                                    <p class="mt-2 text-xs text-muted-foreground">Comma-separated list of IPs allowed to use the API. Leave empty for no restriction.</p>
                                </div>
                            </div>

                            <div class="kt-form-item">
                                <label class="kt-form-label">Custom LLM Rate Limit (RPM)</label>
                                <div class="kt-form-control">
                                    <input type="number" name="custom_llm_limit" class="kt-input" value="{{ old('custom_llm_limit', $tenant->custom_llm_limit) }}" placeholder="60" min="1" />
                                    <p class="mt-2 text-xs text-muted-foreground">Requests Per Minute. Default is 60 RPM if not set.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-border pt-6">
                        <h3 class="text-base font-semibold text-foreground">Isolation & Database</h3>
                        <div class="mt-4 grid gap-6 lg:grid-cols-2">
                            <div class="kt-form-item">
                                <label class="kt-form-label">Isolation Mode <span class="text-destructive">*</span></label>
                                <div class="kt-form-control">
                                    <select name="isolation_mode" id="isolation_mode" class="kt-select" @error('isolation_mode') aria-invalid="true" @enderror>
                                        <option value="shared" {{ old('isolation_mode', $tenant->isolation_mode) == 'shared' ? 'selected' : '' }}>Shared Database</option>
                                        <option value="db_per_tenant" {{ old('isolation_mode', $tenant->isolation_mode) == 'db_per_tenant' ? 'selected' : '' }}>Dedicated Database</option>
                                        <option value="byo" {{ old('isolation_mode', $tenant->isolation_mode) == 'byo' ? 'selected' : '' }}>BYO Strategy</option>
                                    </select>
                                    @error('isolation_mode')
                                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="kt-form-item">
                                <label class="kt-form-label">Database Driver <span class="text-destructive">*</span></label>
                                <div class="kt-form-control">
                                    <select name="db_driver" id="db_driver" class="kt-select" @error('db_driver') aria-invalid="true" @enderror>
                                        <option value="mysql" {{ old('db_driver', $tenant->db_driver) == 'mysql' ? 'selected' : '' }}>MySQL</option>
                                        <option value="pgsql" {{ old('db_driver', $tenant->db_driver) == 'pgsql' ? 'selected' : '' }}>PostgreSQL</option>
                                    </select>
                                    @error('db_driver')
                                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mt-4" id="db_secret_field" style="{{ old('isolation_mode', $tenant->isolation_mode) === 'shared' ? 'display: none;' : '' }}">
                            <div class="kt-form-item">
                                <label class="kt-form-label">DB Secret Reference (Optional)</label>
                                <div class="kt-form-control">
                                    <input type="text" name="db_secret_ref" class="kt-input" value="{{ old('db_secret_ref', $tenant->db_secret_ref) }}" placeholder="e.g. AWS Secret Manager ARN or path" @error('db_secret_ref') aria-invalid="true" @enderror />
                                    <p class="mt-2 text-xs text-muted-foreground">If Dedicated or BYO is selected, leave blank to use default isolation settings.</p>
                                    @error('db_secret_ref')
                                        <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-border bg-background p-6">
                    <h2 class="text-lg font-semibold text-foreground">Billing Strategy & Custom Rates</h2>
                    <p class="mt-2 text-sm text-muted-foreground">Override pricing and customize tenant markup.</p>

                    <div class="mt-6 grid gap-6">
                        <div class="kt-form-item">
                            <label class="kt-form-label">Tenant Specific Markup (%)</label>
                            <div class="kt-form-control">
                                <input type="number" step="0.01" name="markup_percentage" class="kt-input" value="{{ old('markup_percentage', $tenant->markup_percentage) }}" />
                                <p class="mt-2 text-xs text-muted-foreground">Added cumulatively to Global + Package markups.</p>
                            </div>
                        </div>

                        <div class="border-t border-border pt-6">
                            <h3 class="text-base font-semibold text-foreground">Negotiated Unit Rates (Overrides)</h3>
                            <p class="mt-2 text-xs text-muted-foreground">Leave blank to use Package or Global defaults.</p>

                            <div class="mt-4 overflow-x-auto">
                                <table class="kt-table">
                                    <thead>
                                        <tr class="text-xs uppercase text-muted-foreground">
                                            <th>Metric</th>
                                            <th>Unit Price ($)</th>
                                            <th>Per X Units</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm text-foreground">
                                        @foreach($metrics as $metric)
                                            @php
                                                $price = $tenant->usagePrices->where('metric', $metric)->first();
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <span class="font-medium text-foreground">{{ $metric->name }}</span>
                                                        <span class="text-xs text-muted-foreground">Unit: {{ $metric->unit() }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.000001" name="usage_prices[{{ $metric->value }}][unit_price]"
                                                           class="kt-input kt-input-sm"
                                                           value="{{ $price?->unit_price }}" placeholder="Inherit">
                                                </td>
                                                <td>
                                                    <input type="number" step="1" name="usage_prices[{{ $metric->value }}][unit_quantity]"
                                                           class="kt-input kt-input-sm"
                                                           value="{{ $price?->unit_quantity }}" placeholder="Inherit">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="kt-btn kt-btn-primary">{{ __('locale.buttons.save_changes') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </section>
@endsection

@push('styles')
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
@endpush

@push('vendor-scripts')
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
@endpush

@push('custom-scripts')
    <script>
        document.getElementById('isolation_mode')?.addEventListener('change', function () {
            const secretField = document.getElementById('db_secret_field');
            if (!secretField) {
                return;
            }

            if (this.value === 'shared') {
                secretField.style.display = 'none';
            } else {
                secretField.style.display = 'block';
            }
        });
    </script>
    <script src="{{ asset('js/scripts.js') }}"></script>
@endpush
