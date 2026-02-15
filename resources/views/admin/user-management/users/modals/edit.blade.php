<x-modal id="updateUserModal" title="{{ __('locale.labels.edit_user') }}" type="xl">
    <form id="updateUserForm" class="space-y-6" action="#" enctype="multipart/form-data">
        @csrf

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-2 lg:col-span-2">
                <label class="kt-label font-semibold text-foreground">Avatar</label>
                <div class="kt-image-input size-32" data-kt-image-input="true">
                    <input type="file" name="photo" id="ephoto" accept=".png, .jpg, .jpeg" />
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
                <label class="kt-label font-semibold text-foreground" for="efirst_name">First Name</label>
                <input type="text" name="first_name" class="kt-input" id="efirst_name" placeholder="First name" value="{{ old('first_name') }}" />
            </div>

            <div class="space-y-2">
                <label class="kt-label font-semibold text-foreground" for="elast_name">Last Name</label>
                <input type="text" name="last_name" id="elast_name" class="kt-input" placeholder="Last name" value="{{ old('last_name') }}" />
            </div>

            <div class="space-y-2">
                <label class="kt-label font-semibold text-foreground" for="ephone_no">Phone No.</label>
                <input type="text" name="phone_no" class="kt-input" id="ephone_no" placeholder="233500000000" value="{{ old('phone_no') }}" />
            </div>

            <div class="space-y-2">
                <label class="kt-label font-semibold text-foreground" for="eemail">Email</label>
                <input type="email" name="email" id="eemail" class="kt-input" placeholder="example@domain.com" />
            </div>

            <div class="space-y-2 lg:col-span-2">
                <label class="kt-label font-semibold text-foreground" for="eroles">Roles</label>
                <select class="kt-select" name="roles[]" id="eroles" multiple="multiple" data-control="select2" data-placeholder="Select roles" data-dropdown-parent="#updateUserModal">
                    <option value=""></option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-2 lg:col-span-2">
                <label class="kt-label font-semibold text-foreground" for="estatus">Status</label>
                <select class="kt-select status" name="status" id="estatus">
                    <option value="">Select an Option</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-border pt-6">
            <button type="button" class="kt-btn kt-btn-outline" data-kt-modal-dismiss="true">Discard</button>
            <button type="submit" class="kt-btn kt-btn-primary" id="updateUserButton">
                <span class="indicator-label">Submit</span>
                <span class="indicator-progress">
                    Please wait...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
        </div>
    </form>
</x-modal>
