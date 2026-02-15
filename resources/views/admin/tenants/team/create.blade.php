@extends('layouts.metronic.app')

@section('title', __('locale.buttons.create'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Tenant Team</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Add Team Member</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Invite a user and assign roles for this tenant.</p>
                </div>
                <a href="{{ route('tenants.show', $tenant->uuid) }}" class="kt-btn kt-btn-outline">Back to Tenant</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <form action="{{ route('tenants.team.store', $tenant->uuid) }}" enctype="multipart/form-data" method="post" class="kt-form">
                @csrf

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">{{ __('locale.labels.first_name') }} <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="first_name" class="kt-input" value="{{ old('first_name') }}" @error('first_name') aria-invalid="true" @enderror />
                            @error('first_name')
                                <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">{{ __('locale.labels.last_name') }} <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="last_name" class="kt-input" value="{{ old('last_name') }}" @error('last_name') aria-invalid="true" @enderror />
                            @error('last_name')
                                <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">{{ __('locale.labels.email') }} <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="email" class="kt-input" value="{{ old('email') }}" @error('email') aria-invalid="true" @enderror />
                            @error('email')
                                <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">{{ __('locale.labels.phone_number') }}</label>
                        <div class="kt-form-control">
                            <input type="text" name="phone_number" class="kt-input" value="{{ old('phone_number') }}" @error('phone_number') aria-invalid="true" @enderror />
                            @error('phone_number')
                                <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">{{ __('Roles') }} <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <select name="roles[]" id="roles" class="kt-select" data-control="select2" data-placeholder="Select options" multiple="multiple" @error('roles') aria-invalid="true" @enderror>
                                <option></option>
                                @foreach ($roles as $role)
                                    @if ($role->name !== 'Superadmin')
                                        <option value="{{ $role->id }}" {{ in_array($role->id, old('roles', [])) ? 'selected' : '' }}>{{ $role->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('roles')
                                <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">{{ __('locale.labels.status') }} <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <select name="status" id="status" class="kt-select" data-control="select2" data-placeholder="Select an option" @error('status') aria-invalid="true" @enderror>
                                <option></option>
                                <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>{{ __('locale.labels.active') }}</option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>{{ __('locale.labels.inactive') }}</option>
                            </select>
                            @error('status')
                                <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">{{ __('locale.labels.profile_photo') }}</label>
                        <div class="kt-form-control">
                            <input type="file" name="photo" class="kt-input" @error('photo') aria-invalid="true" @enderror />
                            @error('photo')
                                <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">

                <div class="kt-form-actions mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('tenants.show', $tenant->uuid) }}" class="kt-btn kt-btn-outline">Cancel</a>
                    <button type="submit" class="kt-btn kt-btn-primary">{{ __('locale.buttons.create_team') }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('styles')
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
@endpush

@push('vendor-scripts')
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
@endpush

@push('custom-scripts')
    <script src="{{ asset('js/scripts.js') }}"></script>
@endpush
