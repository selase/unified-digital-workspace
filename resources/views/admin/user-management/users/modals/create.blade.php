<x-modal id="createUserModal" title="Add User" type="xl">
    <form id="createUserForm" class="space-y-6" action="{{ request()->route('subdomain') ? route('tenant.users.store', ['subdomain' => request()->route('subdomain')]) : route('users.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-2 lg:col-span-2">
                <label class="kt-label font-semibold text-foreground">Avatar</label>
                <div class="kt-image-input size-32" data-kt-image-input="true">
                    <input type="file" name="photo" id="photo" accept=".png, .jpg, .jpeg" />
                    <input type="hidden" name="avatar_remove" />
                    <button class="kt-image-input-remove" data-kt-image-input-remove="true" data-kt-tooltip="true" data-kt-tooltip-placement="right" data-kt-tooltip-trigger="hover" type="button">
                        <i class="ki-filled ki-cross"></i>
                    </button>
                    <div class="kt-image-input-placeholder border-2 border-input kt-image-input-empty:border-input rounded-xl" data-kt-image-input-placeholder="true" style="background-image:url('{{ asset('assets/metronic/media/avatars/blank.png') }}')">
                        <div class="kt-image-input-preview rounded-xl" data-kt-image-input-preview="true" style="background-image:url('{{ asset('assets/media/avatars/300-6.jpg') }}')"></div>
                        <div class="absolute inset-x-0 bottom-0 flex h-5 cursor-pointer items-center justify-center bg-black/25">
                            <i class="ki-filled ki-camera text-white text-xs"></i>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">Allowed file types: png, jpg, jpeg.</p>
            </div>

            <div class="space-y-2">
                <label class="kt-label font-semibold text-foreground" for="create-first-name">First Name</label>
                <input id="create-first-name" type="text" name="first_name" class="kt-input" placeholder="First name" value="{{ old('first_name') }}" />
            </div>

            <div class="space-y-2">
                <label class="kt-label font-semibold text-foreground" for="create-last-name">Last Name</label>
                <input id="create-last-name" type="text" name="last_name" class="kt-input" placeholder="Last name" value="{{ old('last_name') }}" />
            </div>

            <div class="space-y-2">
                <label class="kt-label font-semibold text-foreground" for="create-phone-no">Phone No.</label>
                <input id="create-phone-no" type="text" name="phone_no" class="kt-input" placeholder="e.g. 0540000000 or +233540000000" value="{{ old('phone_no') }}" />
            </div>

            <div class="space-y-2">
                <label class="kt-label font-semibold text-foreground" for="create-email">Email</label>
                <input id="create-email" type="email" name="email" class="kt-input" placeholder="example@domain.com" />
            </div>

            <div class="space-y-3 lg:col-span-2">
                <label class="kt-label font-semibold text-foreground">Roles</label>
                <div class="rounded-lg border border-border bg-muted/30 p-4 space-y-4 max-h-56 overflow-y-auto">
                    @foreach ($roles as $role)
                        <div class="space-y-1">
                            <label for="role_{{ $role->id }}" class="inline-flex items-start gap-3 cursor-pointer">
                                <input class="kt-checkbox mt-1" name="roles[]" type="checkbox" value="{{ $role->id }}" id="role_{{ $role->id }}" />
                                <span>
                                    <span class="block text-sm font-semibold text-foreground">{{ $role->name }}</span>
                                    <span class="block text-xs text-muted-foreground">Permissions: {{ $role->permissions->pluck('name')->implode(', ') ?: 'None' }}</span>
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            @hasrole('Superadmin')
                <div class="space-y-2 lg:col-span-2">
                    <label class="kt-label font-semibold text-foreground" for="create-tenant-id">Tenant</label>
                    <select id="create-tenant-id" class="kt-select" name="tenant_id">
                        <option value="">Select an option</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="tenant_id" value="{{ session()->get('tenant_id') }}">
            @endhasrole

            <div class="space-y-2 lg:col-span-2">
                <label class="kt-label font-semibold text-foreground" for="create-status">Status</label>
                <select id="create-status" class="kt-select" name="status">
                    <option value="">Select an option</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" {{ $status === \App\Models\User::STATUS_ACTIVE ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-border pt-6">
            <button type="button" class="kt-btn kt-btn-outline" data-kt-modal-dismiss="true">Discard</button>
            <button type="submit" class="kt-btn kt-btn-primary" id="createUserButton">
                <span class="indicator-label">Submit</span>
                <span class="indicator-progress">
                    Please wait...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
        </div>
    </form>
</x-modal>
